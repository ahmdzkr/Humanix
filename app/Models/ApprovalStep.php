<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalStep extends Model
{
    use Auditable;

    /** `managers` = siapa pun berrole Manager (dipakai untuk lembur HRD yang tidak punya atasan berrole Manager). */
    public const ROLE_LABELS = ['manager' => 'Atasan', 'managers' => 'Manager', 'hrd' => 'HRD'];

    public const STATUS_LABELS = [
        'pending' => 'Menunggu',
        'waiting' => 'Belum giliran',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'skipped' => 'Dilewati',
        'cancelled' => 'Dibatalkan',
    ];

    protected $fillable = [
        'approvable_type', 'approvable_id', 'step', 'approver_role',
        'assigned_user_id', 'decided_by', 'status', 'note', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'step' => 'integer',
        ];
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
