<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    use Auditable;

    public const DAY_NAMES = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    protected $fillable = [
        'name', 'start_time', 'end_time', 'work_days', 'late_tolerance_minutes',
        'check_in_opens_minutes', 'check_out_closes_minutes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'work_days' => 'array',
            'is_active' => 'boolean',
            'late_tolerance_minutes' => 'integer',
            'check_in_opens_minutes' => 'integer',
            'check_out_closes_minutes' => 'integer',
        ];
    }

    public function isWorkDay(CarbonInterface $date): bool
    {
        return in_array($date->isoWeekday(), array_map('intval', $this->work_days ?? []), true);
    }

    /** Waktu mulai kerja pada tanggal tertentu (zona WIB, dikembalikan sebagai instant). */
    public function startsAt(string $workDate): CarbonImmutable
    {
        return CarbonImmutable::parse("{$workDate} {$this->start_time}", config('app.display_timezone'));
    }

    public function endsAt(string $workDate): CarbonImmutable
    {
        return CarbonImmutable::parse("{$workDate} {$this->end_time}", config('app.display_timezone'));
    }

    public function label(): string
    {
        return sprintf('%s (%s–%s)', $this->name, substr($this->start_time, 0, 5), substr($this->end_time, 0, 5));
    }
}
