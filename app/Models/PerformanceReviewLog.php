<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReviewLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_LABELS = [
        'created' => 'Penilaian dibuat',
        'self_assessed' => 'Evaluasi diri dikirim',
        'manager_reviewed' => 'Penilaian atasan dikirim',
        'returned' => 'Dikembalikan HRD untuk revisi',
        'finalized' => 'Difinalkan HRD',
        'published' => 'Hasil dipublikasikan',
        'plan_added' => 'Rencana pengembangan ditambahkan',
    ];

    protected $fillable = ['performance_review_id', 'action', 'note', 'user_id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
