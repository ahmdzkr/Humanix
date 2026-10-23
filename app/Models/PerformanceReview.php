<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PerformanceReview extends Model
{
    use Auditable;

    protected $fillable = [
        'performance_period_id', 'employee_id', 'reviewer_id', 'status', 'version',
        'manager_comment', 'hr_comment', 'final_score', 'rating', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'version' => 'integer',
            'final_score' => 'decimal:2',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PerformancePeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PerformancePeriod::class, 'performance_period_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return HasMany<PerformanceReviewItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PerformanceReviewItem::class);
    }

    /** @return HasOne<SelfAssessment, $this> */
    public function selfAssessment(): HasOne
    {
        return $this->hasOne(SelfAssessment::class);
    }

    /** @return HasMany<DevelopmentPlan, $this> */
    public function developmentPlans(): HasMany
    {
        return $this->hasMany(DevelopmentPlan::class)->latest('id');
    }

    /** @return HasMany<PerformanceReviewLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(PerformanceReviewLog::class)->latest('id');
    }
}
