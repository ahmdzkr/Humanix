<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReviewItem extends Model
{
    protected $fillable = ['performance_review_id', 'performance_indicator_id', 'self_score', 'self_comment', 'manager_score', 'manager_comment'];

    protected function casts(): array
    {
        return [
            'self_score' => 'integer',
            'manager_score' => 'integer',
        ];
    }

    /** @return BelongsTo<PerformanceIndicator, $this> */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(PerformanceIndicator::class, 'performance_indicator_id');
    }
}
