<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\WorkSchedule;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Riwayat absensi contoh 10 hari kerja terakhir (hanya lokal).
 */
class AttendanceDemoSeeder extends Seeder
{
    public function run(AttendanceService $attendance): void
    {
        $tz = config('app.display_timezone');
        $schedule = WorkSchedule::query()->where('name', 'Reguler')->firstOrFail();
        $start = CarbonImmutable::now($tz)->subDays(21)->toDateString();

        Employee::query()->where('is_active', true)->each(function (Employee $employee) use ($schedule, $start) {
            EmployeeSchedule::query()->firstOrCreate(
                ['employee_id' => $employee->id, 'work_schedule_id' => $schedule->id],
                ['effective_from' => $start],
            );
        });

        $day = CarbonImmutable::now($tz)->subDay();
        $done = 0;

        while ($done < 10) {
            $date = $day->toDateString();

            if ($schedule->isWorkDay($day) && ! Holiday::isHoliday($date)) {
                foreach (Employee::query()->where('is_active', true)->get() as $i => $employee) {
                    // Pola tetap (bukan acak) supaya hasil seed bisa diulang.
                    $pattern = ($i + $done) % 9;

                    if ($pattern === 0) {
                        continue; // biarkan kosong → ditandai Tidak Hadir di bawah
                    }

                    $checkIn = $schedule->startsAt($date)->addMinutes([0, -20, -10, 5, 25, -15, 40, -5, 12][$pattern]);
                    $late = $attendance->lateMinutes($schedule, $date, $checkIn);

                    AttendanceRecord::query()->firstOrCreate(
                        ['employee_id' => $employee->id, 'work_date' => $date],
                        [
                            'work_schedule_id' => $schedule->id,
                            'check_in_at' => $checkIn,
                            'check_out_at' => $schedule->endsAt($date)->addMinutes($pattern * 3),
                            'status' => $late > 0 ? AttendanceStatus::Late : AttendanceStatus::Present,
                            'late_minutes' => $late,
                            'source' => 'scan',
                        ],
                    );
                }

                $attendance->markAbsentees($date);
                $done++;
            }

            $day = $day->subDay();
        }
    }
}
