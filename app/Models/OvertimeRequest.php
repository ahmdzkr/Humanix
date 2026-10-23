<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class OvertimeRequest extends Model
{
    use Auditable, HasApprovals;

    protected $fillable = [
        'employee_id', 'work_date', 'start_at', 'end_at', 'duration_minutes', 'reason',
        'status', 'version', 'payable', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'duration_minutes' => 'integer',
            'status' => RequestStatus::class,
            'version' => 'integer',
            'payable' => 'boolean',
            'decided_at' => 'datetime',
        ];
    }

    // Selalu simpan sebagai UTC (lihat AttendanceRecord untuk alasannya).
    protected function startAt(): Attribute
    {
        return Attribute::make(set: fn ($value) => Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'));
    }

    protected function endAt(): Attribute
    {
        return Attribute::make(set: fn ($value) => Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'));
    }

    public function approvalCategory(): string
    {
        return 'overtime';
    }

    public function approvalSummary(): string
    {
        $tz = config('app.display_timezone');

        return sprintf('Lembur %s, %s–%s (%d menit)',
            $this->work_date->locale('id')->translatedFormat('j M Y'),
            $this->start_at->setTimezone($tz)->format('H:i'),
            $this->end_at->setTimezone($tz)->format('H:i'),
            $this->duration_minutes);
    }

    public function approvalUrl(): string
    {
        return route('overtime.show', $this, absolute: false);
    }
}
