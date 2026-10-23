<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'employee_id', 'work_date', 'work_schedule_id', 'check_in_at', 'check_out_at',
        'status', 'late_minutes', 'source', 'note',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'status' => AttendanceStatus::class,
            'late_minutes' => 'integer',
        ];
    }

    /**
     * Waktu selalu disimpan dalam UTC. Tanpa ini, Carbon berzona WIB disimpan apa adanya
     * (08:00 WIB tersimpan sebagai 08:00 UTC = 15:00 WIB).
     */
    protected function checkInAt(): Attribute
    {
        return Attribute::make(set: fn ($value) => self::toStorageTime($value));
    }

    protected function checkOutAt(): Attribute
    {
        return Attribute::make(set: fn ($value) => self::toStorageTime($value));
    }

    private static function toStorageTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<WorkSchedule, $this> */
    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    /** @return HasMany<AttendanceCorrection, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class)->latest('id');
    }
}
