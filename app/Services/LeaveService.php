<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\RequestStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    public const ATTACHMENT_DISK = 'local';

    public const PERMISSION = 'leave.approve';

    public function __construct(private readonly ApprovalService $approvals) {}

    /**
     * Hari kerja dalam rentang: mengikuti hari kerja jadwal karyawan (default Senin–Jumat)
     * dan tidak menghitung hari libur.
     *
     * @return list<string>
     */
    public function workingDates(Employee $employee, string $start, string $end): array
    {
        $holidays = Holiday::query()->whereBetween('date', [$start, $end])->pluck('date')
            ->map(fn ($d) => $d->toDateString())->all();
        $dates = [];

        foreach (CarbonPeriod::create($start, $end) as $day) {
            $date = $day->toDateString();
            $schedule = $employee->scheduleOn($date);
            $isWorkDay = $schedule ? $schedule->isWorkDay($day) : $day->isWeekday();

            if ($isWorkDay && ! in_array($date, $holidays, true)) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    public function balanceFor(Employee $employee, LeaveType $type, int $year, bool $lock = false): LeaveBalance
    {
        $balance = LeaveBalance::query()->firstOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
            ['quota' => $type->default_quota, 'used' => 0],
        );

        return $lock ? LeaveBalance::query()->whereKey($balance->id)->lockForUpdate()->first() : $balance;
    }

    /** Hari yang sedang diajukan (Menunggu) untuk jenis & tahun ini, dihitung sebagai "dipesan". */
    public function pendingDays(Employee $employee, LeaveType $type, int $year, ?int $exceptId = null): int
    {
        return (int) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('status', RequestStatus::Pending->value)
            ->whereYear('start_date', $year)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->sum('days');
    }

    /**
     * @param  array{leave_type_id: int, start_date: string, end_date: string, reason: string}  $data
     *
     * @throws ValidationException
     */
    public function submit(Employee $employee, array $data, ?UploadedFile $attachment = null): LeaveRequest
    {
        $type = LeaveType::query()->where('is_active', true)->findOrFail($data['leave_type_id']);
        $start = CarbonImmutable::parse($data['start_date'])->toDateString();
        $end = CarbonImmutable::parse($data['end_date'])->toDateString();
        $days = count($this->workingDates($employee, $start, $end));

        if ($days === 0) {
            throw ValidationException::withMessages(['end_date' => 'Rentang tanggal tidak berisi hari kerja (akhir pekan atau hari libur).']);
        }

        if ($type->requires_attachment && $attachment === null) {
            throw ValidationException::withMessages(['attachment' => "Lampiran wajib untuk {$type->name}."]);
        }

        if ($type->uses_balance && substr($start, 0, 4) !== substr($end, 0, 4)) {
            throw ValidationException::withMessages(['end_date' => 'Pengajuan cuti bersaldo tidak boleh melewati pergantian tahun. Pisahkan menjadi dua pengajuan.']);
        }

        $path = $attachment?->store('leave-attachments', self::ATTACHMENT_DISK);

        try {
            $request = DB::transaction(function () use ($employee, $type, $start, $end, $days, $data, $path) {
                // Kunci baris karyawan: mencegah dua pengajuan bersamaan lolos cek bentrok & saldo.
                Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

                $overlap = LeaveRequest::query()
                    ->where('employee_id', $employee->id)
                    ->whereIn('status', RequestStatus::active())
                    ->whereDate('start_date', '<=', $end)
                    ->whereDate('end_date', '>=', $start)
                    ->exists();

                if ($overlap) {
                    throw ValidationException::withMessages(['start_date' => 'Tanggal bentrok dengan pengajuan cuti/izin lain yang masih menunggu atau sudah disetujui.']);
                }

                if ($type->uses_balance) {
                    $year = (int) substr($start, 0, 4);
                    $balance = $this->balanceFor($employee, $type, $year, lock: true);
                    $available = $balance->remaining() - $this->pendingDays($employee, $type, $year);

                    if ($days > $available) {
                        throw ValidationException::withMessages([
                            'end_date' => sprintf('Sisa %s tidak cukup: tersedia %s hari, diajukan %d hari kerja.', $type->name, rtrim(rtrim(number_format(max(0, $available), 1, ',', ''), '0'), ','), $days),
                        ]);
                    }
                }

                $request = LeaveRequest::create([
                    'employee_id' => $employee->id,
                    'leave_type_id' => $type->id,
                    'start_date' => $start,
                    'end_date' => $end,
                    'days' => $days,
                    'reason' => $data['reason'],
                    'attachment_path' => $path,
                    'status' => RequestStatus::Pending,
                ]);

                if ($this->approvals->createSteps($request, $employee->loadMissing('manager'), $this->approvals->flowFor($employee))) {
                    $request->forceFill(['status' => RequestStatus::Approved, 'decided_at' => now()])->save();
                    $this->applyApproval($request);
                }

                return $request;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk(self::ATTACHMENT_DISK)->delete($path);
            }

            throw $e;
        }

        $this->approvals->notifyProgress($request, self::PERMISSION);

        return $request;
    }

    /**
     * @throws ValidationException
     */
    public function decide(User $user, LeaveRequest $request, bool $approve, ?string $note, int $version): LeaveRequest
    {
        return $this->approvals->decide($user, $request, self::PERMISSION, $approve, $note, $version, fn (LeaveRequest $r) => $this->applyApproval($r));
    }

    public function cancel(LeaveRequest $request): LeaveRequest
    {
        return $this->approvals->cancel($request);
    }

    /**
     * Efek persetujuan akhir (di dalam transaksi): potong saldo dan ubah absensi "Tidak Hadir" menjadi "Cuti/Izin".
     *
     * @throws ValidationException
     */
    private function applyApproval(LeaveRequest $request): void
    {
        $request->loadMissing(['leaveType', 'employee']);

        if ($request->leaveType->uses_balance) {
            $balance = $this->balanceFor($request->employee, $request->leaveType, (int) $request->start_date->year, lock: true);

            if ($balance->remaining() < $request->days) {
                throw ValidationException::withMessages(['version' => 'Saldo cuti karyawan sudah tidak cukup untuk pengajuan ini.']);
            }

            $balance->update(['used' => $balance->used + $request->days]);
        }

        AttendanceRecord::query()
            ->where('employee_id', $request->employee_id)
            ->whereBetween('work_date', [$request->start_date->toDateString(), $request->end_date->toDateString()])
            ->where('status', AttendanceStatus::Absent->value)
            ->update(['status' => AttendanceStatus::Leave->value, 'note' => "Cuti: {$request->leaveType->name}"]);
    }

    public function canDecide(User $user, LeaveRequest $request): bool
    {
        return $this->approvals->canDecide($user, $request, self::PERMISSION);
    }
}
