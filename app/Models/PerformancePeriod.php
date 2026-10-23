<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformancePeriod extends Model
{
    use Auditable;

    public const STATUS_LABELS = ['draft' => 'Draft', 'active' => 'Aktif', 'closed' => 'Ditutup'];

    protected $fillable = ['name', 'start_date', 'end_date', 'self_assessment_enabled', 'status', 'activated_at', 'closed_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'self_assessment_enabled' => 'boolean',
            'activated_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return HasMany<PerformanceIndicator, $this> */
    public function indicators(): HasMany
    {
        return $this->hasMany(PerformanceIndicator::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<PerformanceReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }

    /** Total bobot indikator (string desimal, presisi). */
    public function totalWeight(): string
    {
        return Money::add(...$this->indicators()->pluck('weight')->map(fn ($w) => (string) $w)->all());
    }
}
