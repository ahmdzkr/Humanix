<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Applicant;
use App\Models\ApplicationStatusHistory;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\NotificationDelivery;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\HrisNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecruitmentService
{
    private ?NotificationDelivery $lastApplicantEmail = null;

    public function __construct(private readonly Notifier $notifier) {}

    /** CV & dokumen pelamar di disk privat; diunduh lewat route berizin. */
    public const CV_DISK = 'local';

    public const SCORE_MIN = 1;

    public const SCORE_MAX = 5;

    /** @return list<string> */
    public function indicators(): array
    {
        return (array) SystemSetting::get('interview_indicators', [
            'Kompetensi teknis', 'Komunikasi', 'Pemecahan masalah', 'Kerja sama tim', 'Kesesuaian budaya kerja',
        ]);
    }

    public function uniqueSlug(string $title, ?int $exceptId = null): string
    {
        $base = Str::slug($title) ?: 'lowongan';
        $slug = $base;
        $i = 2;

        while (JobVacancy::query()->where('slug', $slug)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * Lamaran dari halaman karier publik.
     *
     * @param  array{full_name: string, email: string, phone: string, address?: string|null, last_education?: string|null, cover_letter?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function apply(JobVacancy $vacancy, array $data, UploadedFile $cv, ?string $ip): JobApplication
    {
        if (! $vacancy->isOpen()) {
            throw ValidationException::withMessages(['vacancy' => 'Lowongan ini sudah ditutup.']);
        }

        $email = strtolower(trim($data['email']));
        $path = $cv->store('cv', self::CV_DISK);

        try {
            return DB::transaction(function () use ($vacancy, $data, $email, $path, $ip) {
                $applicant = Applicant::query()->lockForUpdate()->firstOrNew(['email' => $email]);

                if ($applicant->exists && JobApplication::query()
                    ->where('job_vacancy_id', $vacancy->id)->where('applicant_id', $applicant->id)->exists()) {
                    throw ValidationException::withMessages(['email' => 'Anda sudah melamar lowongan ini dengan email tersebut.']);
                }

                $applicant->fill([
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'],
                    'address' => $data['address'] ?? $applicant->address,
                    'last_education' => $data['last_education'] ?? $applicant->last_education,
                ])->save();

                $application = JobApplication::create([
                    'job_vacancy_id' => $vacancy->id,
                    'applicant_id' => $applicant->id,
                    'cv_path' => $path,
                    'cover_letter' => $data['cover_letter'] ?? null,
                    'consent_at' => now(),
                    'consent_ip' => $ip,
                    'status' => ApplicationStatus::Applied,
                ]);

                $this->history($application, null, ApplicationStatus::Applied, 'Melamar lewat halaman karier.');

                return $application;
            });
        } catch (\Throwable $e) {
            Storage::disk(self::CV_DISK)->delete($path);

            throw $e;
        }
    }

    /**
     * Seleksi administrasi: lolos atau tolak.
     *
     * @throws ValidationException
     */
    public function screen(JobApplication $application, bool $pass, ?string $note): JobApplication
    {
        $application = $this->transition($application, [ApplicationStatus::Applied],
            $pass ? ApplicationStatus::Shortlisted : ApplicationStatus::Rejected, $note);

        $this->notifyScreening($application, $pass, $note);

        return $application;
    }

    /**
     * @param  array{scheduled_at: string, duration_minutes: int, mode: string, location: string, interviewer_id: int}  $data
     *
     * @throws ValidationException
     */
    public function scheduleInterview(JobApplication $application, array $data): Interview
    {
        $interview = DB::transaction(function () use ($application, $data) {
            $application = JobApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if (! in_array($application->status, [ApplicationStatus::Shortlisted, ApplicationStatus::Interview], true)) {
                throw ValidationException::withMessages(['status' => "Wawancara hanya bisa dijadwalkan untuk pelamar yang lolos administrasi (status saat ini: {$application->status->label()})."]);
            }

            $interview = Interview::create([
                ...$data,
                'job_application_id' => $application->id,
                'status' => 'scheduled',
                'created_by' => Auth::id(),
            ]);

            if ($application->status === ApplicationStatus::Shortlisted) {
                $this->setStatus($application, ApplicationStatus::Interview, 'Wawancara dijadwalkan.');
            }

            return $interview;
        });

        $this->notifyInterview($interview, cancelled: false);

        return $interview;
    }

    /**
     * Penilaian oleh interviewer yang ditugaskan. Skor harus 1–5 untuk setiap indikator.
     *
     * @param  array<string, int|string>  $scores  indikator => skor
     * @param  array<string, string|null>  $comments
     *
     * @throws ValidationException
     */
    public function submitScores(Interview $interview, User $user, array $scores, array $comments, string $recommendation, ?string $summary): Interview
    {
        if ($interview->interviewer_id !== $user->id) {
            abort(403, 'Hanya interviewer yang ditugaskan yang bisa menilai wawancara ini.');
        }

        return DB::transaction(function () use ($interview, $scores, $comments, $recommendation, $summary) {
            $interview = Interview::query()->whereKey($interview->id)->lockForUpdate()->firstOrFail();
            $application = $interview->application;

            if ($interview->status === 'cancelled' || in_array($application->status, [ApplicationStatus::Accepted, ApplicationStatus::Hired, ApplicationStatus::Rejected], true)) {
                throw ValidationException::withMessages(['status' => 'Penilaian tidak bisa diubah: wawancara dibatalkan atau keputusan akhir sudah dibuat.']);
            }

            $errors = [];

            foreach ($this->indicators() as $i => $indicator) {
                $value = $scores[$indicator] ?? null;

                if (! is_numeric($value) || (int) $value != $value || $value < self::SCORE_MIN || $value > self::SCORE_MAX) {
                    $errors["scores.{$i}"] = sprintf('Skor "%s" harus angka %d–%d.', $indicator, self::SCORE_MIN, self::SCORE_MAX);
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $interview->scores()->delete();

            foreach ($this->indicators() as $indicator) {
                $interview->scores()->create([
                    'indicator' => $indicator,
                    'score' => (int) $scores[$indicator],
                    'comment' => $comments[$indicator] ?? null,
                ]);
            }

            $interview->update([
                'status' => 'completed',
                'recommendation' => $recommendation,
                'summary' => $summary,
                'scored_at' => now(),
            ]);

            return $interview;
        });
    }

    public function cancelInterview(Interview $interview): void
    {
        if ($interview->status !== 'scheduled') {
            throw ValidationException::withMessages(['status' => 'Hanya wawancara yang belum dinilai yang bisa dibatalkan.']);
        }

        $interview->update(['status' => 'cancelled']);
        $this->notifyInterview($interview, cancelled: true);
    }

    /**
     * Keputusan akhir setelah wawancara. Terima dibatasi kuota lowongan.
     *
     * @throws ValidationException
     */
    public function decide(JobApplication $application, bool $accept, ?string $note): JobApplication
    {
        $application = DB::transaction(function () use ($application, $accept, $note) {
            $vacancy = JobVacancy::query()->whereKey($application->job_vacancy_id)->lockForUpdate()->firstOrFail();
            $application = JobApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if ($application->status !== ApplicationStatus::Interview) {
                throw ValidationException::withMessages(['status' => "Keputusan akhir hanya untuk pelamar di tahap wawancara (status saat ini: {$application->status->label()})."]);
            }

            if (! $application->interviews()->where('status', 'completed')->exists()) {
                throw ValidationException::withMessages(['status' => 'Belum ada wawancara yang sudah dinilai.']);
            }

            if ($accept) {
                $taken = $vacancy->applications()->whereIn('status', [ApplicationStatus::Accepted->value, ApplicationStatus::Hired->value])->count();

                if ($taken >= $vacancy->quota) {
                    throw ValidationException::withMessages(['status' => "Kuota lowongan ({$vacancy->quota} orang) sudah terpenuhi."]);
                }
            }

            $this->setStatus($application, $accept ? ApplicationStatus::Accepted : ApplicationStatus::Rejected, $note);
            $application->forceFill(['decision_note' => $note, 'decided_by' => Auth::id(), 'decided_at' => now()])->save();

            if ($accept) {
                $this->closeIfQuotaFilled($vacancy);
            }

            return $application;
        });

        $this->notifyDecision($application, $note);

        return $application;
    }

    /** Jumlah pelamar yang mengisi kuota: keputusan akhir Diterima (termasuk yang sudah jadi karyawan). */
    public function filledCount(JobVacancy $vacancy): int
    {
        return $vacancy->applications()->whereIn('status', [ApplicationStatus::Accepted->value, ApplicationStatus::Hired->value])->count();
    }

    /**
     * Tutup lowongan otomatis bila kuota sudah terpenuhi (dipanggil saat menerima pelamar dan saat kuota diubah).
     * Lowongan hilang dari halaman karier dan tidak menerima lamaran baru; pelamar yang masih diproses tidak diubah.
     * Mengembalikan true bila lowongan baru saja ditutup.
     */
    public function closeIfQuotaFilled(JobVacancy $vacancy): bool
    {
        if ($vacancy->status === 'closed' || $this->filledCount($vacancy) < $vacancy->quota) {
            return false;
        }

        $vacancy->update(['status' => 'closed', 'closed_at' => now(), 'closed_reason' => 'quota']);

        $this->notifier->send($this->notifier->hrdUsers('recruitment.manage'), new HrisNotification(
            'recruitment',
            'Lowongan ditutup otomatis',
            "Kuota lowongan {$vacancy->title} ({$vacancy->quota} orang) sudah terpenuhi, sehingga lowongan ditutup dan tidak lagi menerima lamaran.",
            route('recruitment.vacancies.show', $vacancy, absolute: false),
        ), mail: false);

        return true;
    }

    /** Email terakhir yang diantrekan untuk pelamar pada aksi ini (untuk pesan konfirmasi ke HRD). */
    public function lastApplicantEmail(): ?NotificationDelivery
    {
        return $this->lastApplicantEmail;
    }

    /**
     * Jadwal (atau pembatalan) wawancara: pelamar lewat email (waktu, tempat, catatan); interviewer cukup in-app.
     */
    private function notifyInterview(Interview $interview, bool $cancelled): void
    {
        $interview->loadMissing(['application.applicant', 'application.vacancy', 'interviewer']);
        $application = $interview->application;
        $when = $interview->scheduled_at->setTimezone(config('app.display_timezone'))->locale('id')->translatedFormat('l, j F Y \p\u\k\u\l H:i').' WIB';
        $where = $interview->mode === 'online' ? "online ({$interview->location})" : "di {$interview->location}";
        $vacancy = $application->vacancy->title;
        $applicant = $application->applicant;

        $this->notifier->send($interview->interviewer, new HrisNotification(
            'recruitment',
            $cancelled ? 'Wawancara dibatalkan' : 'Jadwal wawancara baru',
            $cancelled
                ? "Wawancara {$applicant->full_name} untuk {$vacancy} pada {$when} dibatalkan."
                : "Anda dijadwalkan mewawancarai {$applicant->full_name} untuk {$vacancy} pada {$when}, {$where}.",
            route('recruitment.applications.show', $application, absolute: false),
        ), except: Auth::user(), mail: false); // email jadwal hanya untuk pelamar; interviewer cukup notifikasi in-app

        if ($cancelled) {
            $this->lastApplicantEmail = $this->notifier->sendToEmail($applicant->email, $applicant->full_name, new HrisNotification(
                'recruitment',
                "Wawancara {$vacancy} dibatalkan",
                "Wawancara Anda untuk posisi {$vacancy} pada {$when} dibatalkan. Kami akan menghubungi Anda bila ada jadwal pengganti.",
            ));

            return;
        }

        $this->lastApplicantEmail = $this->notifier->sendToEmail($applicant->email, $applicant->full_name, new HrisNotification(
            'recruitment',
            "Undangan wawancara: {$vacancy}",
            "Terima kasih atas lamaran Anda untuk posisi {$vacancy}. Kami mengundang Anda mengikuti wawancara dengan rincian berikut.",
            details: array_filter([
                'Posisi' => $vacancy,
                'Jadwal' => $when,
                'Durasi' => "{$interview->duration_minutes} menit",
                'Jenis' => $interview->mode === 'online' ? 'Online' : 'Tatap muka',
                $interview->mode === 'online' ? 'Tautan' : 'Lokasi' => $interview->location,
                'Pewawancara' => $interview->interviewer->name,
                'Catatan dari HRD' => $interview->notes,
            ], 'filled'),
            closing: 'Mohon hadir 10 menit sebelum jadwal. Bila berhalangan, balas email ini atau hubungi tim HR kami.',
        ));
    }

    /** Hasil seleksi administrasi (lolos/tidak memenuhi syarat) ke email pelamar. */
    private function notifyScreening(JobApplication $application, bool $pass, ?string $note): void
    {
        $application->loadMissing(['applicant', 'vacancy']);
        $vacancy = $application->vacancy->title;

        $this->lastApplicantEmail = $this->notifier->sendToEmail($application->applicant->email, $application->applicant->full_name, new HrisNotification(
            'recruitment',
            $pass ? "Lolos seleksi berkas: {$vacancy}" : "Hasil seleksi berkas: {$vacancy}",
            $pass
                ? "Selamat, berkas lamaran Anda untuk posisi {$vacancy} dinyatakan lolos seleksi administrasi.\n\nTahap berikutnya adalah wawancara. Jadwal wawancara akan kami kirimkan melalui email terpisah."
                : "Terima kasih atas minat Anda pada posisi {$vacancy}. Setelah meninjau berkas Anda, dengan berat hati kami sampaikan bahwa lamaran Anda belum memenuhi persyaratan posisi ini sehingga tidak dapat kami lanjutkan.",
            details: array_filter(['Catatan dari HRD' => $note], 'filled'),
            closing: $pass ? null : 'Kami menghargai waktu Anda dan mendoakan kesuksesan di kesempatan berikutnya.',
        ));
    }

    /** Keputusan akhir setelah wawancara, beserta ringkasan hasil wawancara dan catatan HRD. */
    private function notifyDecision(JobApplication $application, ?string $note): void
    {
        $application->loadMissing(['applicant', 'vacancy']);
        $vacancy = $application->vacancy->title;
        $accepted = $application->status === ApplicationStatus::Accepted;
        $interview = $application->interviews()->where('status', 'completed')->with('scores')->latest('scored_at')->first();
        $average = $interview?->scores->avg('score');

        $this->lastApplicantEmail = $this->notifier->sendToEmail($application->applicant->email, $application->applicant->full_name, new HrisNotification(
            'recruitment',
            $accepted ? "Selamat, Anda diterima: {$vacancy}" : "Hasil akhir lamaran: {$vacancy}",
            $accepted
                ? "Selamat! Berdasarkan hasil wawancara, Anda dinyatakan DITERIMA untuk posisi {$vacancy}.\n\nTim HR akan segera menghubungi Anda untuk proses onboarding dan pembuatan akun karyawan."
                : "Terima kasih telah mengikuti seluruh tahapan seleksi untuk posisi {$vacancy}. Berdasarkan hasil wawancara, dengan berat hati kami sampaikan bahwa Anda belum dapat kami terima untuk posisi ini.",
            details: array_filter([
                'Hasil wawancara' => $interview?->recommendation ? Interview::RECOMMENDATIONS[$interview->recommendation] ?? null : null,
                'Nilai rata-rata' => $average !== null ? number_format((float) $average, 1, ',', '.').' dari '.self::SCORE_MAX : null,
                'Catatan dari HRD' => $note,
            ], 'filled'),
            closing: $accepted ? null : 'Data Anda akan kami simpan untuk peluang lain yang sesuai. Semoga sukses selalu.',
        ));
    }

    /**
     * @param  list<ApplicationStatus>  $from
     *
     * @throws ValidationException
     */
    private function transition(JobApplication $application, array $from, ApplicationStatus $to, ?string $note): JobApplication
    {
        return DB::transaction(function () use ($application, $from, $to, $note) {
            $application = JobApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if (! in_array($application->status, $from, true)) {
                throw ValidationException::withMessages(['status' => "Lamaran berstatus {$application->status->label()} tidak bisa diubah menjadi {$to->label()}."]);
            }

            $this->setStatus($application, $to, $note);

            return $application;
        });
    }

    public function setStatus(JobApplication $application, ApplicationStatus $to, ?string $note): void
    {
        $from = $application->status;
        $application->forceFill(['status' => $to])->save();
        $this->history($application, $from, $to, $note);
    }

    private function history(JobApplication $application, ?ApplicationStatus $from, ApplicationStatus $to, ?string $note): void
    {
        ApplicationStatusHistory::create([
            'job_application_id' => $application->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => $note,
            'user_id' => Auth::id(),
        ]);
    }
}
