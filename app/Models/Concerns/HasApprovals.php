<?php

namespace App\Models\Concerns;

use App\Enums\RequestStatus;
use App\Models\ApprovalStep;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Model pengajuan yang melewati langkah persetujuan (cuti/izin, lembur).
 *
 * @property RequestStatus $status
 * @property int $version
 */
trait HasApprovals
{
    /** @return MorphMany<ApprovalStep, $this> */
    public function approvalSteps(): MorphMany
    {
        return $this->morphMany(ApprovalStep::class, 'approvable')->orderBy('step');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function currentStep(): ?ApprovalStep
    {
        return $this->approvalSteps()->where('status', 'pending')->orderBy('step')->first();
    }

    public function isPending(): bool
    {
        return $this->status === RequestStatus::Pending;
    }

    /** Kategori notifikasi: "leave" atau "overtime". */
    abstract public function approvalCategory(): string;

    /** Ringkasan singkat untuk notifikasi, mis. "Cuti Tahunan 3 hari (12–14 Okt 2026)". */
    abstract public function approvalSummary(): string;

    /** URL relatif halaman detail pengajuan. */
    abstract public function approvalUrl(): string;
}
