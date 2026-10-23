<?php

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Enums\Role;
use App\Models\DevelopmentPlan;
use App\Models\Employee;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewItem;
use App\Models\PerformanceReviewLog;
use App\Models\SelfAssessment;
use App\Models\User;
use App\Notifications\HrisNotification;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penilaian kinerja. Nilai akhir 0–100 = Σ bobot × skor ternormalisasi
 * ((skor − min) ÷ (max − min) × 100), dihitung dari nilai atasan.
 */
class PerformanceService
{
    /** Batas bawah nilai akhir (0–100) untuk tiap predikat. */
    public const RATINGS = [90 => 'Istimewa', 75 => 'Baik', 60 => 'Cukup', 40 => 'Kurang', 0 => 'Buruk'];

    public function __construct(private readonly Notifier $notifier) {}

    // ---------- Periode & indikator ----------

    /**
     * @throws ValidationException
     */
    public function assertDraft(PerformancePeriod $period): void
    {
        if ($period->status !== 'draft') {
            throw ValidationException::withMessages(['period' => 'Indikator hanya bisa diubah saat periode masih Draft.']);
        }
    }

    /**
     * Aktifkan periode: total bobot harus tepat 100%, lalu buat lembar penilaian untuk setiap karyawan aktif.
     *
     * @throws ValidationException
     */
    public function activate(PerformancePeriod $period): int
    {
        return DB::transaction(function () use ($period) {
            $period = PerformancePeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($period);

            $indicators = $period->indicators()->get();

            if ($indicators->isEmpty()) {
                throw ValidationException::withMessages(['period' => 'Tambahkan minimal satu indikator sebelum mengaktifkan periode.']);
            }

            $total = $period->totalWeight();

            if (Money::compare($total, '100.00') !== 0) {
                throw ValidationException::withMessages([
                    'period' => 'Total bobot indikator harus tepat 100% (saat ini '.rtrim(rtrim(str_replace('.', ',', $total), '0'), ',').'%).',
                ]);
            }

            $employees = Employee::query()
                ->where('is_active', true)
                ->whereDate('join_date', '<=', $period->end_date->toDateString())
                ->with('manager')
                ->get();

            foreach ($employees as $employee) {
                $review = PerformanceReview::create([
                    'performance_period_id' => $period->id,
                    'employee_id' => $employee->id,
                    'reviewer_id' => $employee->manager?->is_active ? $employee->manager->user_id : null,
                    'status' => $period->self_assessment_enabled ? ReviewStatus::Draft : ReviewStatus::SelfAssessed,
                ]);

                foreach ($indicators as $indicator) {
                    PerformanceReviewItem::create(['performance_review_id' => $review->id, 'performance_indicator_id' => $indicator->id]);
                }

                $this->log($review, 'created');
            }

            $period->update(['status' => 'active', 'activated_at' => now()]);

            $this->notifyPending(PerformanceReview::query()->where('performance_period_id', $period->id)->get());

            return $employees->count();
        });
    }

    /**
     * @throws ValidationException
     */
    public function closePeriod(PerformancePeriod $period): void
    {
        if ($period->status !== 'active') {
            throw ValidationException::withMessages(['period' => 'Hanya periode aktif yang bisa ditutup.']);
        }

        $unpublished = $period->reviews()->where('status', '!=', ReviewStatus::Published->value)->count();

        if ($unpublished > 0) {
            throw ValidationException::withMessages(['period' => "Masih ada {$unpublished} penilaian yang belum dipublikasikan."]);
        }

        $period->update(['status' => 'closed', 'closed_at' => now()]);
    }

    // ---------- Hak tindakan ----------

    public function isOwner(User $user, PerformanceReview $review): bool
    {
        return $user->employee?->id === $review->employee_id;
    }

    public function isHr(User $user): bool
    {
        return $user->hasRole(Role::Hrd->value) && $user->can('performance.manage');
    }

    /**
     * Atasan hanya boleh menilai bawahan dalam scope-nya (langsung/tidak langsung) yang ditugaskan kepadanya.
     * Bila karyawan tidak punya atasan, HRD yang menilai.
     */
    public function canManagerReview(User $user, PerformanceReview $review): bool
    {
        if ($this->isOwner($user, $review) || $review->period->status !== 'active') {
            return false;
        }

        if ($review->reviewer_id === null) {
            return $this->isHr($user);
        }

        if ($review->reviewer_id !== $user->id) {
            return false;
        }

        $self = $user->employee;

        return $self !== null && in_array($review->employee_id, $self->subordinateIds(), true);
    }

    public function canView(User $user, PerformanceReview $review): bool
    {
        // Karyawan (dengan pembatasan data sebelum publikasi), penilai yang ditugaskan, atau HRD.
        return $this->isOwner($user, $review)
            || $review->reviewer_id === $user->id
            || $this->isHr($user);
    }

    // ---------- Alur penilaian ----------

    /**
     * @param  array<int|string, mixed>  $scores  indicator_id => skor
     * @param  array<int|string, string|null>  $comments
     *
     * @throws ValidationException
     */
    public function submitSelf(PerformanceReview $review, User $user, array $scores, array $comments, string $achievements, ?string $challenges, int $version): PerformanceReview
    {
        abort_unless($this->isOwner($user, $review), 403);

        return DB::transaction(function () use ($review, $scores, $comments, $achievements, $challenges, $version) {
            $review = $this->lock($review, $version);

            if ($review->period->status !== 'active' || $review->status !== ReviewStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Evaluasi diri sudah dikirim atau periode tidak aktif.']);
            }

            $this->saveScores($review, $scores, $comments, 'self');

            SelfAssessment::query()->updateOrCreate(
                ['performance_review_id' => $review->id],
                ['achievements' => $achievements, 'challenges' => $challenges, 'submitted_at' => now()],
            );

            return $this->transition($review, ReviewStatus::SelfAssessed, 'self_assessed');
        });
    }

    /**
     * @param  array<int|string, mixed>  $scores
     * @param  array<int|string, string|null>  $comments
     *
     * @throws ValidationException
     */
    public function submitManager(PerformanceReview $review, User $user, array $scores, array $comments, ?string $comment, int $version): PerformanceReview
    {
        abort_unless($this->canManagerReview($user, $review), 403, 'Anda tidak berwenang menilai karyawan ini.');

        return DB::transaction(function () use ($review, $scores, $comments, $comment, $version) {
            $review = $this->lock($review, $version);

            if (! $review->status->awaitsManager()) {
                throw ValidationException::withMessages(['status' => "Penilaian atasan tidak bisa dikirim pada status {$review->status->label()}."]);
            }

            $this->saveScores($review, $scores, $comments, 'manager');
            $review->manager_comment = $comment;

            return $this->transition($review, ReviewStatus::ManagerReviewed, 'manager_reviewed');
        });
    }

    /**
     * @throws ValidationException
     */
    public function returnToManager(PerformanceReview $review, User $user, string $note, int $version): PerformanceReview
    {
        $this->assertHrActor($user, $review);

        return DB::transaction(function () use ($review, $note, $version) {
            $review = $this->lock($review, $version);
            $this->assertStatus($review, ReviewStatus::ManagerReviewed);
            $review->hr_comment = $note;

            return $this->transition($review, ReviewStatus::Returned, 'returned', $note);
        });
    }

    /**
     * @throws ValidationException
     */
    public function finalize(PerformanceReview $review, User $user, ?string $comment, int $version): PerformanceReview
    {
        $this->assertHrActor($user, $review);

        return DB::transaction(function () use ($review, $comment, $version) {
            $review = $this->lock($review, $version);
            $this->assertStatus($review, ReviewStatus::ManagerReviewed);

            $score = $this->finalScore($review->items()->with('indicator')->get());
            $review->fill(['final_score' => $score, 'rating' => $this->rating($score), 'hr_comment' => $comment]);

            return $this->transition($review, ReviewStatus::Final, 'finalized', $comment);
        });
    }

    /**
     * @throws ValidationException
     */
    public function publish(PerformanceReview $review, User $user): PerformanceReview
    {
        $this->assertHrActor($user, $review);

        return DB::transaction(function () use ($review) {
            $review = PerformanceReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($review, ReviewStatus::Final);
            $review->published_at = now();

            return $this->transition($review, ReviewStatus::Published, 'published');
        });
    }

    /**
     * @param  array{type: string, description: string, target_date?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function addPlan(PerformanceReview $review, User $user, array $data): DevelopmentPlan
    {
        abort_unless($this->isHr($user) || ($review->reviewer_id === $user->id && ! $this->isOwner($user, $review)), 403);

        if (! in_array($review->status, [ReviewStatus::Final, ReviewStatus::Published], true)) {
            throw ValidationException::withMessages(['status' => 'Rencana pengembangan dibuat setelah penilaian difinalkan.']);
        }

        $plan = DevelopmentPlan::create([...$data, 'performance_review_id' => $review->id, 'status' => 'planned', 'created_by' => $user->id]);
        $this->log($review, 'plan_added', DevelopmentPlan::TYPES[$data['type']] ?? $data['type']);

        return $plan;
    }

    // ---------- Perhitungan ----------

    /**
     * @param  Collection<int, PerformanceReviewItem>  $items
     */
    public function finalScore(Collection $items): string
    {
        $total = '0';

        foreach ($items as $item) {
            $indicator = $item->indicator;
            $range = $indicator->scale_max - $indicator->scale_min;
            $normalized = $range === 0 ? '100' : bcdiv(bcmul((string) ($item->manager_score - $indicator->scale_min), '100', 8), (string) $range, 8);
            $total = bcadd($total, bcdiv(bcmul($normalized, (string) $indicator->weight, 8), '100', 8), 8);
        }

        return Money::round($total);
    }

    public function rating(string $score): string
    {
        foreach (self::RATINGS as $min => $label) {
            if (bccomp($score, (string) $min, 2) >= 0) {
                return $label;
            }
        }

        return 'Buruk';
    }

    // ---------- Internal ----------

    /**
     * @param  array<int|string, mixed>  $scores
     * @param  array<int|string, string|null>  $comments
     *
     * @throws ValidationException
     */
    private function saveScores(PerformanceReview $review, array $scores, array $comments, string $who): void
    {
        $items = $review->items()->with('indicator')->get();
        $errors = [];

        foreach ($items as $item) {
            $indicator = $item->indicator;
            $value = $scores[$indicator->id] ?? null;

            if (! is_numeric($value) || (int) $value != $value || $value < $indicator->scale_min || $value > $indicator->scale_max) {
                $errors["scores.{$indicator->id}"] = sprintf('Nilai "%s" harus angka bulat %d–%d.', $indicator->name, $indicator->scale_min, $indicator->scale_max);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        foreach ($items as $item) {
            $id = $item->indicator->id;
            $item->update([
                "{$who}_score" => (int) $scores[$id],
                "{$who}_comment" => $comments[$id] ?? null,
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function lock(PerformanceReview $review, int $version): PerformanceReview
    {
        $locked = PerformanceReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();

        if ($locked->version !== $version) {
            throw ValidationException::withMessages(['version' => 'Penilaian ini baru saja diubah. Muat ulang halaman untuk melihat versi terbaru.']);
        }

        return $locked;
    }

    private function assertHrActor(User $user, PerformanceReview $review): void
    {
        // HRD tidak memproses penilaian dirinya sendiri.
        abort_unless($this->isHr($user) && ! $this->isOwner($user, $review), 403);
    }

    /**
     * @throws ValidationException
     */
    private function assertStatus(PerformanceReview $review, ReviewStatus $expected): void
    {
        if ($review->status !== $expected) {
            throw ValidationException::withMessages(['status' => "Tindakan ini hanya untuk status {$expected->label()} (saat ini {$review->status->label()})."]);
        }
    }

    private function transition(PerformanceReview $review, ReviewStatus $to, string $action, ?string $note = null): PerformanceReview
    {
        $review->status = $to;
        $review->version++;
        $review->save();
        $this->log($review, $action, $note);
        $this->notifyStep($review, $note);

        return $review;
    }

    /**
     * Notifikasi massal (aktivasi periode & pengingat terjadwal): karyawan yang belum mengisi evaluasi diri
     * diberi tahu per orang; penilai menerima SATU ringkasan berisi jumlah lembar yang menunggunya.
     *
     * @param  Collection<int, PerformanceReview>  $reviews
     * @return int jumlah penerima
     */
    public function notifyPending(Collection $reviews, bool $reminder = false): int
    {
        $reviews->loadMissing(['employee.user', 'reviewer', 'period']);
        $prefix = $reminder ? 'Pengingat: ' : '';
        $count = 0;

        foreach ($reviews->where('status', ReviewStatus::Draft) as $review) {
            $this->notifier->send($review->employee, new HrisNotification('performance', "{$prefix}Isi evaluasi diri",
                "Silakan isi evaluasi diri Anda untuk periode {$review->period->name}.",
                route('performance.reviews.show', $review, absolute: false)));
            $count++;
        }

        $awaiting = $reviews->filter(fn (PerformanceReview $r) => $r->status->awaitsManager());
        $hrd = $this->notifier->hrdUsers('performance.manage');

        // Tanpa atasan yang ditugaskan, HRD yang menilai.
        $byAssessor = [];

        foreach ($awaiting as $review) {
            foreach ($review->reviewer ? [$review->reviewer] : $hrd as $user) {
                $byAssessor[$user->id] ??= ['user' => $user, 'total' => 0];
                $byAssessor[$user->id]['total']++;
            }
        }

        foreach ($byAssessor as ['user' => $user, 'total' => $total]) {
            $this->notifier->send($user, new HrisNotification('performance', "{$prefix}Penilaian atasan diperlukan",
                "{$total} lembar penilaian kinerja menunggu penilaian Anda.",
                route('performance.index', absolute: false)));
            $count++;
        }

        return $count;
    }

    /**
     * Beri tahu pihak yang giliran bertindak setelah lembar penilaian berpindah status.
     * Nilai/predikat tidak pernah dimasukkan ke notifikasi.
     */
    private function notifyStep(PerformanceReview $review, ?string $note = null): void
    {
        $review->loadMissing(['employee.user', 'reviewer', 'period']);
        $period = $review->period->name;
        $name = $review->employee->full_name;
        $url = route('performance.reviews.show', $review, absolute: false);
        // Tanpa atasan yang ditugaskan, HRD yang menilai.
        $assessors = $review->reviewer ?? $this->notifier->hrdUsers('performance.manage');

        [$recipients, $title, $body] = match ($review->status) {
            ReviewStatus::Draft => [$review->employee, 'Isi evaluasi diri', "Silakan isi evaluasi diri Anda untuk periode {$period}."],
            ReviewStatus::SelfAssessed => [$assessors, 'Penilaian atasan diperlukan', "Lembar penilaian {$name} untuk periode {$period} menunggu penilaian Anda."],
            ReviewStatus::Returned => [$assessors, 'Penilaian dikembalikan untuk revisi', "Penilaian {$name} periode {$period} dikembalikan HRD.".($note ? " Catatan: {$note}" : '')],
            ReviewStatus::ManagerReviewed => [$this->notifier->hrdUsers('performance.manage'), 'Penilaian menunggu pemeriksaan HRD', "Penilaian {$name} periode {$period} sudah diisi atasan."],
            ReviewStatus::Published => [$review->employee, 'Hasil penilaian kinerja tersedia', "Hasil penilaian kinerja Anda untuk periode {$period} sudah dipublikasikan."],
            ReviewStatus::Final => [null, '', ''],
        };

        if ($recipients !== null) {
            $this->notifier->send($recipients, new HrisNotification('performance', $title, $body, $url), except: Auth::user());
        }
    }

    private function log(PerformanceReview $review, string $action, ?string $note = null): void
    {
        PerformanceReviewLog::create([
            'performance_review_id' => $review->id,
            'action' => $action,
            'note' => $note,
            'user_id' => Auth::id(),
        ]);
    }
}
