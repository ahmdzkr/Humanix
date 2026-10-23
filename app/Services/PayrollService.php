<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayrollStatus;
use App\Enums\RequestStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\Payment;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\SalaryStructure;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\HrisNotification;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Payroll: periode, perhitungan, transisi status (hanya maju), penyesuaian manual, dan pembayaran.
 * Pajak/BPJS tidak diasumsikan: dimasukkan sebagai komponen gaji yang diatur HRD.
 */
class PayrollService
{
    public const PROOF_DISK = 'local';

    public function __construct(private readonly Notifier $notifier) {}

    public function createPeriod(string $month, string $payDate): PayrollPeriod
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', "{$month}-01")->startOfDay();

        if (PayrollPeriod::query()->where('code', $month)->exists()) {
            throw ValidationException::withMessages(['month' => 'Periode payroll untuk bulan ini sudah ada.']);
        }

        return PayrollPeriod::create([
            'code' => $month,
            'start_date' => $start->toDateString(),
            'end_date' => $start->endOfMonth()->toDateString(),
            'pay_date' => $payDate,
            'status' => PayrollStatus::Draft,
        ]);
    }

    /**
     * Hitung (atau hitung ulang) seluruh slip dalam periode. Item manual dipertahankan.
     *
     * @return array{calculated: int, skipped: list<string>}
     *
     * @throws ValidationException
     */
    public function calculate(PayrollPeriod $period, User $actor, int $expectedVersion): array
    {
        return DB::transaction(function () use ($period, $actor, $expectedVersion) {
            $period = $this->lockPeriod($period, $expectedVersion);

            if (! $period->status->isEditable()) {
                throw ValidationException::withMessages(['status' => 'Periode yang sudah direview tidak bisa dihitung ulang.']);
            }

            $start = $period->start_date->toDateString();
            $end = $period->end_date->toDateString();
            $skipped = [];
            $keptIds = [];

            // Lepas tautan lembur dari perhitungan sebelumnya pada periode ini.
            OvertimeRequest::query()
                ->whereIn('payroll_record_id', $period->records()->select('id'))
                ->update(['payroll_record_id' => null]);

            $employees = Employee::query()
                ->with(['department', 'position', 'bankAccount'])
                ->whereDate('join_date', '<=', $end)
                ->where(fn ($q) => $q->where('is_active', true)->orWhereDate('deactivated_at', '>=', $start))
                ->orderBy('full_name')
                ->get();

            foreach ($employees as $employee) {
                $structure = $employee->salaryStructureOn($end);

                if ($structure === null) {
                    $skipped[] = "{$employee->full_name} ({$employee->nik})";

                    continue;
                }

                $record = $this->calculateRecord($period, $employee, $structure);
                $keptIds[] = $record->id;
            }

            // Hapus slip karyawan yang tidak lagi memenuhi syarat (beserta itemnya).
            $period->records()->whereNotIn('id', $keptIds)->delete();

            $period->forceFill([
                'status' => PayrollStatus::Calculated,
                'calculated_at' => now(),
                'calculated_by' => $actor->id,
                'version' => $period->version + 1,
            ])->save();

            return ['calculated' => count($keptIds), 'skipped' => $skipped];
        });
    }

    private function calculateRecord(PayrollPeriod $period, Employee $employee, SalaryStructure $structure): PayrollRecord
    {
        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();
        $base = Money::of($structure->base_salary);

        $record = PayrollRecord::query()->firstOrNew([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
        ]);

        $attendance = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start, $end])
            ->get(['status']);

        $record->fill([
            'employee_name' => $employee->full_name,
            'nik' => $employee->nik,
            'department_name' => $employee->department?->name,
            'position_name' => $employee->position?->name,
            'bank_name' => $employee->bankAccount?->bank_name,
            'bank_account_number' => $employee->bankAccount?->account_number,
            'bank_account_holder' => $employee->bankAccount?->account_holder,
            'base_salary' => $base,
            'present_days' => $attendance->filter(fn ($a) => $a->status->isPresent())->count(),
            'absent_days' => $attendance->where('status', AttendanceStatus::Absent)->count(),
            'leave_days' => $attendance->where('status', AttendanceStatus::Leave)->count(),
            'total_earnings' => '0', 'total_deductions' => '0', 'net_pay' => '0',
        ])->save();

        // Bangun ulang item otomatis; item manual tetap.
        $record->items()->where('source', '!=', 'manual')->delete();

        PayrollItem::create([
            'payroll_record_id' => $record->id, 'name' => 'Gaji Pokok', 'type' => 'earning',
            'source' => 'base', 'amount' => $base, 'sort_order' => 0,
        ]);

        foreach ($structure->items as $item) {
            $component = $item->component;

            if (! $component?->is_active) {
                continue;
            }

            $amount = $component->calculation === 'percentage'
                ? Money::percent($base, (string) ($item->rate ?? $component->default_rate ?? '0'))
                : Money::of($item->amount ?? $component->default_amount);

            PayrollItem::create([
                'payroll_record_id' => $record->id,
                'salary_component_id' => $component->id,
                'name' => $component->name,
                'type' => $component->type,
                'source' => 'component',
                'amount' => $amount,
                'note' => $component->calculation === 'percentage'
                    ? rtrim(rtrim((string) ($item->rate ?? $component->default_rate), '0'), '.').'% × gaji pokok'
                    : null,
                'sort_order' => 10 + $component->sort_order,
            ]);
        }

        // Lembur disetujui dalam periode yang belum pernah dibayar.
        $overtimes = OvertimeRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', RequestStatus::Approved->value)
            ->where('payable', true)
            ->whereNull('payroll_record_id')
            ->whereBetween('work_date', [$start, $end])
            ->get();

        $overtimeMinutes = 0;

        if ($overtimes->isNotEmpty()) {
            $overtimeAmount = '0.00';

            foreach ($overtimes as $overtime) {
                $overtimeAmount = Money::add($overtimeAmount, $this->overtimePay($base, $overtime->duration_minutes));
                $overtimeMinutes += $overtime->duration_minutes;
            }

            PayrollItem::create([
                'payroll_record_id' => $record->id, 'name' => 'Lembur', 'type' => 'earning', 'source' => 'overtime',
                'amount' => $overtimeAmount, 'sort_order' => 900,
                'note' => sprintf('%d pengajuan, total %s', $overtimes->count(), $this->formatMinutes($overtimeMinutes)),
            ]);

            OvertimeRequest::query()->whereKey($overtimes->modelKeys())->update(['payroll_record_id' => $record->id]);
        }

        $record->overtime_minutes = $overtimeMinutes;
        $this->recalculateTotals($record);

        return $record;
    }

    /**
     * Upah lembur satu pengajuan: tarif per jam = gaji pokok ÷ pembagi (default 173);
     * jam pertama × pengali pertama (1,5), jam berikutnya × pengali lanjutan (2). Semua dari pengaturan.
     */
    public function overtimePay(string $baseSalary, int $minutes): string
    {
        $divisor = (string) SystemSetting::get('overtime_hourly_divisor', 173);
        $first = (string) SystemSetting::get('overtime_first_hour_multiplier', '1.5');
        $next = (string) SystemSetting::get('overtime_next_hour_multiplier', '2');

        $firstMinutes = min(60, $minutes);
        $nextMinutes = max(0, $minutes - 60);

        // (menit_pertama × pengali1 + menit_berikut × pengali2) / 60 × gaji / pembagi
        $weightedHours = bcdiv(bcadd(bcmul((string) $firstMinutes, $first, 8), bcmul((string) $nextMinutes, $next, 8), 8), '60', 8);

        return Money::round(bcdiv(bcmul($baseSalary, $weightedHours, 8), $divisor, 8));
    }

    public function recalculateTotals(PayrollRecord $record): void
    {
        $items = $record->items()->get(['type', 'amount']);
        $earnings = Money::add(...$items->where('type', 'earning')->map(fn ($i) => Money::of($i->amount))->values()->all());
        $deductions = Money::add(...$items->where('type', 'deduction')->map(fn ($i) => Money::of($i->amount))->values()->all());

        $record->forceFill([
            'total_earnings' => $earnings,
            'total_deductions' => $deductions,
            'net_pay' => Money::sub($earnings, $deductions),
        ])->save();
    }

    /**
     * @param  array{name: string, type: string, amount: string, note?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function addManualItem(PayrollRecord $record, array $data): PayrollItem
    {
        return DB::transaction(function () use ($record, $data) {
            $period = PayrollPeriod::query()->whereKey($record->payroll_period_id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($period);

            $item = PayrollItem::create([
                'payroll_record_id' => $record->id,
                'name' => $data['name'],
                'type' => $data['type'],
                'source' => 'manual',
                'amount' => Money::of($data['amount']),
                'note' => $data['note'] ?? null,
                'sort_order' => 950,
            ]);

            $this->recalculateTotals($record);
            $period->increment('version');

            return $item;
        });
    }

    /**
     * @throws ValidationException
     */
    public function removeManualItem(PayrollItem $item): void
    {
        DB::transaction(function () use ($item) {
            $record = PayrollRecord::query()->findOrFail($item->payroll_record_id);
            $period = PayrollPeriod::query()->whereKey($record->payroll_period_id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($period);

            if ($item->source !== 'manual') {
                throw ValidationException::withMessages(['item' => 'Hanya penyesuaian manual yang bisa dihapus.']);
            }

            $item->delete();
            $this->recalculateTotals($record);
            $period->increment('version');
        });
    }

    /**
     * Majukan status satu langkah. Mundur tidak diizinkan.
     *
     * @throws ValidationException
     */
    public function advance(PayrollPeriod $period, PayrollStatus $target, User $actor, int $expectedVersion): PayrollPeriod
    {
        return DB::transaction(function () use ($period, $target, $actor, $expectedVersion) {
            $period = $this->lockPeriod($period, $expectedVersion);

            if ($period->status->next() !== $target) {
                throw ValidationException::withMessages(['status' => "Status tidak bisa diubah dari {$period->status->label()} ke {$target->label()}."]);
            }

            match ($target) {
                PayrollStatus::Reviewed => $this->assertReviewable($period),
                PayrollStatus::Approved => $this->assertApprovable($period),
                PayrollStatus::Closed => $this->assertClosable($period),
                default => null,
            };

            $column = match ($target) {
                PayrollStatus::Reviewed => 'reviewed',
                PayrollStatus::Approved => 'approved',
                PayrollStatus::Closed => 'closed',
                default => throw ValidationException::withMessages(['status' => 'Gunakan tombol Hitung untuk status ini.']),
            };

            $period->forceFill([
                'status' => $target,
                "{$column}_at" => now(),
                "{$column}_by" => $actor->id,
                'version' => $period->version + 1,
            ])->save();

            // Slip baru terlihat oleh karyawan setelah periode disetujui; beri tahu masing-masing pemiliknya.
            // Nominal gaji tidak dimasukkan ke notifikasi/email.
            if ($target === PayrollStatus::Approved) {
                $label = $period->label();

                $period->records()->with('employee.user')->get()->each(fn (PayrollRecord $record) => $this->notifier->send(
                    $record->employee,
                    new HrisNotification('payroll', "Slip gaji {$label} tersedia", "Slip gaji Anda untuk periode {$label} sudah dapat dilihat dan diunduh.",
                        route('payroll.records.show', $record, absolute: false), 'Lihat slip gaji'),
                ));
            }

            return $period;
        });
    }

    private function assertReviewable(PayrollPeriod $period): void
    {
        if (! $period->records()->exists()) {
            throw ValidationException::withMessages(['status' => 'Belum ada slip gaji yang dihitung di periode ini.']);
        }
    }

    private function assertApprovable(PayrollPeriod $period): void
    {
        $negative = $period->records()->where('net_pay', '<', 0)->pluck('employee_name');

        if ($negative->isNotEmpty()) {
            throw ValidationException::withMessages(['status' => 'Gaji bersih negatif untuk: '.$negative->implode(', ').'.']);
        }
    }

    private function assertClosable(PayrollPeriod $period): void
    {
        $unpaid = $period->records()
            ->where('net_pay', '>', 0)
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', PaymentStatus::Paid->value))
            ->count();

        if ($unpaid > 0) {
            throw ValidationException::withMessages(['status' => "Masih ada {$unpaid} slip yang belum dibayar. Periode hanya bisa ditutup setelah semua dibayar."]);
        }
    }

    /**
     * Buat instruksi pembayaran untuk slip yang belum punya pembayaran aktif.
     * Kunci idempotensi deterministik: menjalankan ulang tidak menggandakan pembayaran.
     */
    public function createPayments(PayrollPeriod $period, User $actor): int
    {
        return DB::transaction(function () use ($period, $actor) {
            $period = PayrollPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();

            if ($period->status !== PayrollStatus::Approved) {
                throw ValidationException::withMessages(['status' => 'Pembayaran hanya bisa dibuat untuk periode yang sudah disetujui.']);
            }

            $created = 0;

            foreach ($period->records()->where('net_pay', '>', 0)->with('payments')->get() as $record) {
                if ($record->payments->contains(fn (Payment $p) => $p->status->isOpen())) {
                    continue;
                }

                $attempt = $record->payments->count() + 1;
                $payment = Payment::query()->firstOrCreate(
                    ['idempotency_key' => "payroll-{$period->code}-record-{$record->id}-attempt-{$attempt}"],
                    ['payroll_record_id' => $record->id, 'amount' => $record->net_pay, 'status' => PaymentStatus::Pending, 'created_by' => $actor->id],
                );

                $created += $payment->wasRecentlyCreated ? 1 : 0;
            }

            return $created;
        });
    }

    /**
     * @param  array{status: string, reference?: string|null, failure_reason?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function updatePayment(Payment $payment, array $data, ?UploadedFile $proof, User $actor): Payment
    {
        $proofPath = $proof?->store('payment-proofs', self::PROOF_DISK);

        try {
            return DB::transaction(function () use ($payment, $data, $proofPath, $actor) {
                $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $period = $payment->record->period;

                if ($period->status !== PayrollStatus::Approved) {
                    throw ValidationException::withMessages(['status' => 'Pembayaran hanya bisa diubah selama periode berstatus Disetujui.']);
                }

                $target = PaymentStatus::from($data['status']);

                if (! in_array($target, $payment->status->allowedNext(), true)) {
                    throw ValidationException::withMessages(['status' => "Status pembayaran tidak bisa diubah dari {$payment->status->label()} ke {$target->label()}."]);
                }

                $reference = trim((string) ($data['reference'] ?? '')) ?: $payment->reference;

                if ($target === PaymentStatus::Paid && $reference === null && $proofPath === null && $payment->proof_path === null) {
                    throw ValidationException::withMessages(['reference' => 'Pembayaran hanya bisa ditandai Sudah Dibayar dengan nomor referensi transfer atau bukti pembayaran.']);
                }

                if ($target === PaymentStatus::Failed && trim((string) ($data['failure_reason'] ?? '')) === '') {
                    throw ValidationException::withMessages(['failure_reason' => 'Alasan kegagalan wajib diisi.']);
                }

                $payment->fill([
                    'status' => $target,
                    'reference' => $reference,
                    'proof_path' => $proofPath ?? $payment->proof_path,
                    'failure_reason' => $target === PaymentStatus::Failed ? $data['failure_reason'] : $payment->failure_reason,
                    'paid_at' => $target === PaymentStatus::Paid ? now() : $payment->paid_at,
                    'updated_by' => $actor->id,
                ])->save();

                return $payment;
            });
        } catch (\Throwable $e) {
            if ($proofPath) {
                Storage::disk(self::PROOF_DISK)->delete($proofPath);
            }

            throw $e;
        }
    }

    /**
     * @throws ValidationException
     */
    private function lockPeriod(PayrollPeriod $period, int $expectedVersion): PayrollPeriod
    {
        $locked = PayrollPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();

        if ($locked->version !== $expectedVersion) {
            throw ValidationException::withMessages(['version' => 'Periode ini baru saja diubah oleh pengguna lain. Muat ulang halaman.']);
        }

        return $locked;
    }

    private function assertEditable(PayrollPeriod $period): void
    {
        if (! $period->status->isEditable()) {
            throw ValidationException::withMessages(['status' => "Periode berstatus {$period->status->label()} sudah terkunci."]);
        }
    }

    private function formatMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return trim(($h ? "{$h} jam " : '').($m ? "{$m} menit" : ''));
    }
}
