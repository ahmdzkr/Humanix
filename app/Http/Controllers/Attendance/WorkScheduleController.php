<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\WorkSchedule;
use App\Services\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkScheduleController extends Controller
{
    public function index(): Response
    {
        $today = now(config('app.display_timezone'))->toDateString();

        $schedules = WorkSchedule::query()->orderBy('name')->get()->map(fn (WorkSchedule $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'start_time' => substr($s->start_time, 0, 5),
            'end_time' => substr($s->end_time, 0, 5),
            'work_days' => array_map('intval', $s->work_days),
            'late_tolerance_minutes' => $s->late_tolerance_minutes,
            'check_in_opens_minutes' => $s->check_in_opens_minutes,
            'check_out_closes_minutes' => $s->check_out_closes_minutes,
            'is_active' => $s->is_active,
            'employees_count' => EmployeeSchedule::query()
                ->where('work_schedule_id', $s->id)
                ->whereDate('effective_from', '<=', $today)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
                ->count(),
        ]);

        return Inertia::render('attendance/schedules', [
            'schedules' => $schedules,
            'dayNames' => WorkSchedule::DAY_NAMES,
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        WorkSchedule::create($this->validated($request));

        return back()->with('success', 'Jadwal kerja berhasil ditambahkan.');
    }

    public function update(Request $request, WorkSchedule $schedule): RedirectResponse
    {
        $schedule->update($this->validated($request, $schedule));

        return back()->with('success', 'Jadwal kerja berhasil diperbarui.');
    }

    public function destroy(WorkSchedule $schedule): RedirectResponse
    {
        $used = EmployeeSchedule::query()->where('work_schedule_id', $schedule->id)->exists()
            || AttendanceRecord::query()->where('work_schedule_id', $schedule->id)->exists();

        if ($used) {
            return back()->with('error', 'Jadwal sudah pernah dipakai karyawan atau absensi. Nonaktifkan saja.');
        }

        $schedule->delete();

        return back()->with('success', 'Jadwal kerja berhasil dihapus.');
    }

    /**
     * Terapkan jadwal ke semua karyawan aktif di satu departemen (berlaku mulai hari ini).
     */
    public function assign(Request $request, WorkSchedule $schedule, EmployeeService $employees): RedirectResponse
    {
        abort_unless($schedule->is_active, 422, 'Jadwal nonaktif tidak bisa diterapkan.');

        $data = $request->validate(['department_id' => ['required', 'integer', Rule::exists('departments', 'id')]], [], ['department_id' => 'departemen']);

        $count = 0;
        DB::transaction(function () use ($data, $schedule, $employees, &$count) {
            Employee::query()->where('department_id', $data['department_id'])->where('is_active', true)
                ->each(function (Employee $employee) use ($schedule, $employees, &$count) {
                    $employees->assignSchedule($employee, $schedule->id);
                    $count++;
                });
        });

        return back()->with('success', "Jadwal {$schedule->name} diterapkan ke {$count} karyawan mulai hari ini.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?WorkSchedule $schedule = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('work_schedules', 'name')->ignore($schedule?->id)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['integer', 'between:1,7', 'distinct'],
            'late_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'check_in_opens_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'check_out_closes_minutes' => ['required', 'integer', 'min:0', 'max:720'],
            'is_active' => ['boolean'],
        ], [
            'end_time.after' => 'Jam pulang harus setelah jam masuk (shift lintas tengah malam belum didukung).',
            'work_days.required' => 'Pilih minimal satu hari kerja.',
        ], [
            'name' => 'nama jadwal',
            'start_time' => 'jam masuk',
            'end_time' => 'jam pulang',
            'work_days' => 'hari kerja',
            'late_tolerance_minutes' => 'toleransi terlambat',
            'check_in_opens_minutes' => 'absen masuk dibuka',
            'check_out_closes_minutes' => 'batas absen pulang',
        ]);

        $data['work_days'] = collect($data['work_days'])->map(fn ($d) => (int) $d)->sort()->values()->all();
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : ($schedule?->is_active ?? true);

        return $data;
    }
}
