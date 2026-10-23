<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\NotificationDelivery;
use App\Models\OnboardingRecord;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\HrisNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mengubah pelamar yang diterima menjadi karyawan + akun login dalam SATU transaksi.
 * Lamaran ditautkan ke karyawan (kolom unik) sehingga tidak bisa dikonversi dua kali.
 */
class OnboardingService
{
    /** Kata sandi awal akun karyawan dari onboarding (disimpan ter-hash, wajib diganti saat login pertama). */
    public const DEFAULT_PASSWORD = 'karyawan123';

    private ?NotificationDelivery $accountEmail = null;

    public function __construct(
        private readonly EmployeeService $employees,
        private readonly RecruitmentService $recruitment,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data  data karyawan (NIK, departemen, jabatan, status, tanggal masuk, dst.)
     *
     * @throws ValidationException
     */
    public function onboard(JobApplication $application, array $data, User $actor): Employee
    {
        $employee = DB::transaction(function () use ($application, $data, $actor) {
            $application = JobApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if ($application->employee_id !== null || $application->status === ApplicationStatus::Hired) {
                throw ValidationException::withMessages(['status' => 'Pelamar ini sudah dijadikan karyawan.']);
            }

            if ($application->status !== ApplicationStatus::Accepted) {
                throw ValidationException::withMessages(['status' => 'Hanya pelamar berstatus Diterima yang bisa dijadikan karyawan.']);
            }

            $applicant = $application->applicant;

            $employee = $this->employees->create([
                ...$data,
                'full_name' => $data['full_name'] ?? $applicant->full_name,
                'email' => $data['email'] ?? $applicant->email,
                'phone' => $data['phone'] ?? $applicant->phone,
                'address' => $data['address'] ?? $applicant->address,
            ], null, sendPasswordLink: false);

            // Akun login memakai email dari data dan kata sandi awal; wajib diganti saat login pertama.
            $employee->user->forceFill(['password' => self::DEFAULT_PASSWORD, 'must_change_password' => true])->save();

            $application->forceFill(['employee_id' => $employee->id])->save();
            $this->recruitment->setStatus($application, ApplicationStatus::Hired, "Menjadi karyawan dengan NIK {$employee->nik}.");

            OnboardingRecord::create([
                'job_application_id' => $application->id,
                'employee_id' => $employee->id,
                'start_date' => $data['join_date'],
                'checklist' => array_map(fn ($item) => ['item' => $item, 'done' => false], OnboardingRecord::DEFAULT_CHECKLIST),
                'created_by' => $actor->id,
            ]);

            return $employee;
        });

        // Baru dikirim setelah transaksi berhasil, agar tidak ada email untuk akun yang batal dibuat.
        $this->accountEmail = $this->sendAccountEmail($employee);

        return $employee;
    }

    /** Email info akun yang diantrekan pada onboarding terakhir (untuk pesan konfirmasi ke HRD). */
    public function accountEmail(): ?NotificationDelivery
    {
        return $this->accountEmail;
    }

    private function sendAccountEmail(Employee $employee): NotificationDelivery
    {
        $company = SystemSetting::get('company_name', config('app.name'));

        return $this->notifier->sendToEmail($employee->user->email, $employee->full_name, new HrisNotification(
            'account',
            "Akun karyawan {$company} Anda sudah aktif",
            "Selamat bergabung di {$company}! Akun HUMANIX Anda sudah dibuat. Gunakan data berikut untuk masuk.",
            url: route('login', absolute: false),
            actionLabel: 'Masuk ke HUMANIX',
            details: [
                'NIK' => $employee->nik,
                'Email login' => $employee->user->email,
                'Kata sandi awal' => self::DEFAULT_PASSWORD,
                'Tanggal mulai' => $employee->join_date?->locale('id')->translatedFormat('j F Y') ?? '-',
            ],
            closing: 'Demi keamanan, Anda akan diminta mengganti kata sandi saat pertama kali masuk. Jangan bagikan kata sandi kepada siapa pun.',
        ));
    }

    public function toggleChecklist(OnboardingRecord $record, int $index, bool $done): void
    {
        $items = $record->checklist;

        if (! isset($items[$index])) {
            throw ValidationException::withMessages(['index' => 'Item checklist tidak ditemukan.']);
        }

        $items[$index]['done'] = $done;
        $record->update(['checklist' => $items]);
    }
}
