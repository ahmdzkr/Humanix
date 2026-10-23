<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use Auditable, HasApprovals;

    protected $fillable = [
        'employee_id', 'leave_type_id', 'start_date', 'end_date', 'days', 'reason',
        'attachment_path', 'status', 'version', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'integer',
            'status' => RequestStatus::class,
            'version' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LeaveType, $this> */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approvalCategory(): string
    {
        return 'leave';
    }

    public function approvalSummary(): string
    {
        $this->loadMissing('leaveType');
        $range = $this->start_date->equalTo($this->end_date)
            ? $this->start_date->locale('id')->translatedFormat('j M Y')
            : $this->start_date->locale('id')->translatedFormat('j M').'–'.$this->end_date->locale('id')->translatedFormat('j M Y');

        return "{$this->leaveType?->name} {$this->days} hari ({$range})";
    }

    public function approvalUrl(): string
    {
        return route('leave.show', $this, absolute: false);
    }
}
