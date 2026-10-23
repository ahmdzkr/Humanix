<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\OvertimeRequest;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OvertimeService
{
    public const PERMISSION = 'overtime.approve';

    public const MIN_MINUTES = 30;

    public function __construct(private readonly ApprovalService $approvals) {}

    public function maxMinutesPerDay(): int
    {
        return (int) SystemSetting::get('max_minutes_per_day', 240);
    }

    /**
     * @param  array{work_date: string, start_time: string, end_time: string, reason: string}  $data
     *
     * @throws ValidationException
     */
    public function submit(Employee $employee, array $data): OvertimeRequest
    {
        $tz = config('app.display_timezone');
        $date = CarbonImmutable::parse($data['work_date'], $tz)->toDateString();
        $start = CarbonImmutable::parse("{$date} {$data['start_time']}", $tz);
        $end = CarbonImmutable::parse("{$date} {$data['end_time']}", $tz);
        $minutes = (int) $start->diffInMinutes($end);

        if ($minutes < self::MIN_MINUTES) {
            throw ValidationException::withMessages(['end_time' => 'Durasi lembur minimal '.self::MIN_MINUTES.' menit.']);
        }

        // Lembur di hari kerja tidak boleh berada di dalam jam kerja jadwal.
        $schedule = $employee->scheduleOn($date);

        if ($schedule && $schedule->isWorkDay($start) && ! Holiday::isHoliday($date)
            && $start->lt($schedule->endsAt($date)) && $end->gt($schedule->startsAt($date))) {
            throw ValidationException::withMessages([
                'start_time' => sprintf('Lembur tidak boleh berada di dalam jam kerja (%s–%s).', substr($schedule->start_time, 0, 5), substr($schedule->end_time, 0, 5)),
            ]);
        }

        $request = DB::transaction(function () use ($employee, $date, $start, $end, $minutes, $data) {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

            $sameDay = OvertimeRequest::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $date)
                ->whereIn('status', RequestStatus::active())
                ->get();

            $overlap = $sameDay->first(fn (OvertimeRequest $o) => $start->lt($o->end_at) && $end->gt($o->start_at));

            if ($overlap) {
                $tz = config('app.display_timezone');

                throw ValidationException::withMessages([
                    'start_time' => sprintf('Waktu bertumpang tindih dengan pengajuan lembur lain (%s–%s).', $overlap->start_at->setTimezone($tz)->format('H:i'), $overlap->end_at->setTimezone($tz)->format('H:i')),
                ]);
            }

            $total = $sameDay->sum('duration_minutes') + $minutes;
            $max = $this->maxMinutesPerDay();

            if ($total > $max) {
                throw ValidationException::withMessages([
                    'end_time' => sprintf('Total lembur pada tanggal ini menjadi %d menit, melebihi batas %d menit per hari.', $total, $max),
                ]);
            }

            $request = OvertimeRequest::create([
                'employee_id' => $employee->id,
                'work_date' => $date,
                'start_at' => $start,
                'end_at' => $end,
                'duration_minutes' => $minutes,
                'reason' => $data['reason'],
                'status' => RequestStatus::Pending,
            ]);

            if ($this->approvals->createSteps($request, $employee->loadMissing('manager.user', 'user'), $this->approvals->flowFor($employee))) {
                $request->forceFill(['status' => RequestStatus::Approved, 'payable' => true, 'decided_at' => now()])->save();
            }

            return $request;
        });

        $this->approvals->notifyProgress($request, self::PERMISSION);

        return $request;
    }

    /**
     * @throws ValidationException
     */
    public function decide(User $user, OvertimeRequest $request, bool $approve, ?string $note, int $version): OvertimeRequest
    {
        return $this->approvals->decide($user, $request, self::PERMISSION, $approve, $note, $version,
            fn (OvertimeRequest $r) => $r->forceFill(['payable' => true]));
    }

    public function cancel(OvertimeRequest $request): OvertimeRequest
    {
        return $this->approvals->cancel($request);
    }

    public function canDecide(User $user, OvertimeRequest $request): bool
    {
        return $this->approvals->canDecide($user, $request, self::PERMISSION);
    }
}
