<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Exceptions\AttendanceScanRejected;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Aturan absensi. Semua keputusan memakai waktu server, bukan waktu perangkat kios.
 */
class AttendanceService
{
    /** Jeda minimal antara absen masuk dan pulang, mencegah scan ganda tercatat sebagai pulang. */
    public const MIN_MINUTES_BETWEEN_SCANS = 30;

    public function __construct(private readonly LeaveCalendar $leaveCalendar) {}

    /**
     * Proses scan QR dari kios: menentukan absen masuk atau pulang.
     *
     * @return array{type: 'check_in'|'check_out', record: AttendanceRecord, employee: Employee}
     *
     * @throws AttendanceScanRejected
     */
    public function scan(string $token, ?CarbonInterface $now = null): array
    {
        $now = CarbonImmutable::instance($now ?? now());
        $local = $now->setTimezone(config('app.display_timezone'));
        $workDate = $local->toDateString();

        $employee = Employee::query()->where('qr_token', $token)->first();

        if ($employee === null) {
            throw new AttendanceScanRejected('QR tidak dikenali. Gunakan ID Card terbaru atau hubungi HRD.');
        }

        $name = $employee->full_name;

        if (! $employee->is_active) {
            throw new AttendanceScanRejected('Karyawan sudah nonaktif.', $name);
        }

        $schedule = $employee->scheduleOn($workDate);

        if ($schedule === null || ! $schedule->is_active) {
            throw new AttendanceScanRejected('Belum ada jadwal kerja aktif untuk hari ini. Hubungi HRD.', $name);
        }

        if (! $schedule->isWorkDay($local) || Holiday::isHoliday($workDate)) {
            throw new AttendanceScanRejected('Hari ini bukan hari kerja sesuai jadwal Anda.', $name);
        }

        if ($this->leaveCalendar->isOnApprovedLeave($employee, $workDate)) {
            throw new AttendanceScanRejected('Anda tercatat cuti/izin hari ini.', $name);
        }

        try {
            return DB::transaction(function () use ($employee, $schedule, $workDate, $now, $name) {
                // Kunci baris karyawan agar dua scan bersamaan tidak sama-sama dianggap absen masuk.
                Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

                $record = AttendanceRecord::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('work_date', $workDate)
                    ->first();

                if ($record === null || $record->check_in_at === null) {
                    return [
                        'type' => 'check_in',
                        'record' => $this->checkIn($employee, $schedule, $workDate, $now, $record),
                        'employee' => $employee,
                    ];
                }

                if ($record->check_out_at !== null) {
                    throw new AttendanceScanRejected('Anda sudah absen masuk dan pulang hari ini.', $name);
                }

                return [
                    'type' => 'check_out',
                    'record' => $this->checkOut($record, $schedule, $workDate, $now, $name),
                    'employee' => $employee,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            throw new AttendanceScanRejected('Absen masuk Anda sudah tercatat.', $name);
        }
    }

    private function checkIn(Employee $employee, WorkSchedule $schedule, string $workDate, CarbonImmutable $now, ?AttendanceRecord $record): AttendanceRecord
    {
        $start = $schedule->startsAt($workDate);
        $opens = $start->subMinutes($schedule->check_in_opens_minutes);

        if ($now->lt($opens)) {
            throw new AttendanceScanRejected(
                'Absen masuk baru dibuka pukul '.$opens->setTimezone(config('app.display_timezone'))->format('H:i').' WIB.',
                $employee->full_name,
            );
        }

        if ($now->gt($schedule->endsAt($workDate))) {
            throw new AttendanceScanRejected('Jam kerja hari ini sudah berakhir. Hubungi HRD untuk koreksi absensi.', $employee->full_name);
        }

        $lateMinutes = $this->lateMinutes($schedule, $workDate, $now);

        $attributes = [
            'work_schedule_id' => $schedule->id,
            'check_in_at' => $now,
            'status' => $lateMinutes > 0 ? AttendanceStatus::Late : AttendanceStatus::Present,
            'late_minutes' => $lateMinutes,
            'source' => 'scan',
        ];

        if ($record) {
            $record->update($attributes);

            return $record;
        }

        return AttendanceRecord::create([...$attributes, 'employee_id' => $employee->id, 'work_date' => $workDate]);
    }

    private function checkOut(AttendanceRecord $record, WorkSchedule $schedule, string $workDate, CarbonImmutable $now, string $name): AttendanceRecord
    {
        $tz = config('app.display_timezone');

        if ($now->lt($record->check_in_at->addMinutes(self::MIN_MINUTES_BETWEEN_SCANS))) {
            throw new AttendanceScanRejected(
                'Anda sudah absen masuk pukul '.$record->check_in_at->setTimezone($tz)->format('H:i').' WIB.',
                $name,
            );
        }

        if ($now->gt($schedule->endsAt($workDate)->addMinutes($schedule->check_out_closes_minutes))) {
            throw new AttendanceScanRejected('Batas waktu absen pulang sudah lewat. Hubungi HRD untuk koreksi.', $name);
        }

        $record->update(['check_out_at' => $now]);

        return $record;
    }

    /**
     * Menit terlambat dihitung dari jam mulai, dan hanya jika melewati toleransi.
     */
    public function lateMinutes(WorkSchedule $schedule, string $workDate, CarbonInterface $checkIn): int
    {
        $start = $schedule->startsAt($workDate);
        $tolerance = $schedule->late_tolerance_minutes ?? (int) SystemSetting::get('late_tolerance_minutes', 15);
        $minutes = (int) floor($start->diffInMinutes($checkIn, false));

        return $minutes > $tolerance ? $minutes : 0;
    }

    /**
     * Tandai "Tidak Hadir" (atau "Cuti/Izin") untuk karyawan yang dijadwalkan kerja tetapi tidak absen.
     *
     * @return array{absent: int, leave: int}
     */
    public function markAbsentees(string $workDate): array
    {
        $date = CarbonImmutable::parse($workDate, config('app.display_timezone'));
        $counts = ['absent' => 0, 'leave' => 0];

        if (Holiday::isHoliday($workDate)) {
            return $counts;
        }

        Employee::query()
            ->where('is_active', true)
            ->whereDate('join_date', '<=', $workDate)
            ->whereDoesntHave('attendanceRecords', fn ($q) => $q->whereDate('work_date', $workDate))
            ->each(function (Employee $employee) use ($workDate, $date, &$counts) {
                $schedule = $employee->scheduleOn($workDate);

                if ($schedule === null || ! $schedule->isWorkDay($date)) {
                    return;
                }

                $onLeave = $this->leaveCalendar->isOnApprovedLeave($employee, $workDate);

                AttendanceRecord::query()->firstOrCreate(
                    ['employee_id' => $employee->id, 'work_date' => $workDate],
                    [
                        'work_schedule_id' => $schedule->id,
                        'status' => $onLeave ? AttendanceStatus::Leave : AttendanceStatus::Absent,
                        'source' => 'system',
                    ],
                );

                $counts[$onLeave ? 'leave' : 'absent']++;
            });

        return $counts;
    }

    /**
     * Koreksi oleh HRD: alasan wajib, nilai lama & baru disimpan.
     *
     * @param  array{check_in_time?: string|null, check_out_time?: string|null, status: string, note?: string|null}  $data
     */
    public function correct(Employee $employee, string $workDate, array $data, string $reason, User $actor): AttendanceRecord
    {
        return DB::transaction(function () use ($employee, $workDate, $data, $reason, $actor) {
            $record = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $workDate)
                ->lockForUpdate()
                ->first();

            $old = $record ? $this->correctionSnapshot($record) : null;
            $schedule = $record?->workSchedule ?? $employee->scheduleOn($workDate);
            $tz = config('app.display_timezone');
            $toInstant = fn (?string $time) => $time ? CarbonImmutable::parse("{$workDate} {$time}", $tz) : null;

            $checkIn = $toInstant($data['check_in_time'] ?? null);
            $status = AttendanceStatus::from($data['status']);

            $record ??= new AttendanceRecord(['employee_id' => $employee->id, 'work_date' => $workDate]);
            $record->fill([
                'work_schedule_id' => $schedule?->id,
                'check_in_at' => $checkIn,
                'check_out_at' => $toInstant($data['check_out_time'] ?? null),
                'status' => $status,
                'late_minutes' => $status === AttendanceStatus::Late && $checkIn && $schedule
                    ? max(1, $this->lateMinutes($schedule, $workDate, $checkIn))
                    : 0,
                'source' => 'correction',
                'note' => $data['note'] ?? null,
            ])->save();

            AttendanceCorrection::create([
                'attendance_record_id' => $record->id,
                'user_id' => $actor->id,
                'old_values' => $old,
                'new_values' => $this->correctionSnapshot($record->fresh()),
                'reason' => $reason,
            ]);

            return $record;
        });
    }

    /** @return array<string, string|null> */
    private function correctionSnapshot(AttendanceRecord $record): array
    {
        $tz = config('app.display_timezone');

        return [
            'Masuk' => $record->check_in_at?->setTimezone($tz)->format('H:i'),
            'Pulang' => $record->check_out_at?->setTimezone($tz)->format('H:i'),
            'Status' => $record->status?->label(),
            'Catatan' => $record->note,
        ];
    }
}
