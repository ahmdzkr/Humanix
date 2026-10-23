<?php

namespace App\Console\Commands;

use App\Enums\ReviewStatus;
use App\Models\PerformanceReview;
use App\Services\PerformanceService;
use Illuminate\Console\Command;

/**
 * Pengingat mingguan untuk lembar penilaian yang masih menunggu evaluasi diri atau penilaian atasan
 * di periode aktif.
 */
class RemindPerformanceReviewsCommand extends Command
{
    protected $signature = 'humanix:remind-reviews';

    protected $description = 'Kirim pengingat penilaian kinerja yang belum diisi (periode aktif)';

    public function handle(PerformanceService $performance): int
    {
        $reviews = PerformanceReview::query()
            ->whereHas('period', fn ($q) => $q->where('status', 'active'))
            ->whereIn('status', [ReviewStatus::Draft->value, ReviewStatus::SelfAssessed->value, ReviewStatus::Returned->value])
            ->get();

        $sent = $performance->notifyPending($reviews, reminder: true);
        $this->info("{$reviews->count()} lembar menunggu; pengingat dikirim ke {$sent} penerima.");

        return self::SUCCESS;
    }
}
