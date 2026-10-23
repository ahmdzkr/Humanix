<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PayrollItem;
use App\Models\PayrollRecord;
use App\Models\SystemSetting;
use App\Services\PayrollService;
use App\Support\FilePreview;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRecordController extends Controller
{
    public function __construct(private readonly PayrollService $payroll) {}

    /** Slip milik karyawan yang login (hanya periode yang sudah disetujui). */
    public function mine(Request $request): Response
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 404, 'Akun Anda belum terhubung dengan data karyawan.');

        $records = PayrollRecord::query()
            ->where('employee_id', $employee->id)
            ->whereHas('period', fn ($q) => $q->whereIn('status', ['approved', 'closed']))
            ->with(['period', 'payments'])
            ->get()
            ->sortByDesc(fn (PayrollRecord $r) => $r->period->start_date)
            ->values()
            ->map(fn (PayrollRecord $r) => [
                'id' => $r->id,
                'period' => $r->period->label(),
                'pay_date' => $r->period->pay_date->toDateString(),
                'net_pay' => $r->net_pay,
                'paid' => $r->payments->contains(fn (Payment $p) => $p->status === PaymentStatus::Paid),
            ]);

        return Inertia::render('payroll/my-slips', ['slips' => $records]);
    }

    public function show(Request $request, PayrollRecord $record): Response
    {
        Gate::authorize('view', $record);
        $record->load(['period', 'items', 'payments']);
        $user = $request->user();
        $isHr = $user->can('payroll.view');

        return Inertia::render('payroll/record', [
            'record' => $this->presentSlip($record),
            'payments' => $isHr ? $record->payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'reference' => $p->reference,
                'has_proof' => $p->proof_path !== null,
                'proof_kind' => FilePreview::kind($p->proof_path),
                'failure_reason' => $p->failure_reason,
                'paid_at' => $p->paid_at?->toIso8601String(),
                'allowed_next' => array_map(fn (PaymentStatus $s) => ['value' => $s->value, 'label' => $s->label()], $p->status->allowedNext()),
                'idempotency_key' => $p->idempotency_key,
            ])->values() : [],
            'can' => [
                'adjust' => $user->can('payroll.manage') && $record->period->status->isEditable(),
                'pay' => $user->can('payroll.manage') && $record->period->status->value === 'approved',
                'backToPeriod' => $isHr,
            ],
        ]);
    }

    public function pdf(PayrollRecord $record): HttpResponse
    {
        Gate::authorize('view', $record);
        $record->load(['period', 'items']);

        $pdf = Pdf::loadView('pdf.payslip', [
            'slip' => $this->presentSlip($record),
            'company' => SystemSetting::get('company_name', config('app.name')),
            'logo' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/logo-humanix.png'))),
        ])->setPaper('a5', 'portrait')->setOption('isFontSubsettingEnabled', true);

        return FilePreview::pdf($pdf, "slip-gaji-{$record->period->code}-{$record->nik}.pdf");
    }

    public function addItem(Request $request, PayrollRecord $record): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['earning', 'deduction'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999', 'decimal:0,2'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['name' => 'nama', 'type' => 'jenis', 'amount' => 'nominal', 'note' => 'keterangan']);

        $this->payroll->addManualItem($record, $data);

        return back()->with('success', 'Penyesuaian ditambahkan.');
    }

    public function removeItem(Request $request, PayrollRecord $record, PayrollItem $item): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.manage') && $item->payroll_record_id === $record->id, 403);

        $this->payroll->removeManualItem($item);

        return back()->with('success', 'Penyesuaian dihapus.');
    }

    public function updatePayment(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.manage'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::enum(PaymentStatus::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'failure_reason' => ['nullable', 'string', 'max:255'],
            'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ], [], ['reference' => 'nomor referensi', 'failure_reason' => 'alasan', 'proof' => 'bukti pembayaran']);

        $this->payroll->updatePayment($payment, $data, $request->file('proof'), $request->user());

        return back()->with('success', 'Status pembayaran diperbarui.');
    }

    public function paymentProof(Request $request, Payment $payment): StreamedResponse
    {
        abort_unless($request->user()->can('payroll.view'), 403);
        abort_unless($payment->proof_path && Storage::disk(PayrollService::PROOF_DISK)->exists($payment->proof_path), 404);

        return FilePreview::stored(PayrollService::PROOF_DISK, $payment->proof_path);
    }

    /** @return array<string, mixed> */
    private function presentSlip(PayrollRecord $record): array
    {
        $items = fn (string $type) => $record->items->where('type', $type)->map(fn (PayrollItem $i) => [
            'id' => $i->id,
            'name' => $i->name,
            'amount' => $i->amount,
            'source' => $i->source,
            'note' => $i->note,
        ])->values();

        return [
            'id' => $record->id,
            'period_id' => $record->payroll_period_id,
            'period' => $record->period->label(),
            'period_status' => $record->period->status->value,
            'pay_date' => $record->period->pay_date->toDateString(),
            'employee_name' => $record->employee_name,
            'nik' => $record->nik,
            'department' => $record->department_name,
            'position' => $record->position_name,
            'bank_name' => $record->bank_name,
            'bank_account' => $record->maskedAccount(),
            'bank_account_holder' => $record->bank_account_holder,
            'earnings' => $items('earning'),
            'deductions' => $items('deduction'),
            'total_earnings' => $record->total_earnings,
            'total_deductions' => $record->total_deductions,
            'net_pay' => $record->net_pay,
            'overtime_minutes' => $record->overtime_minutes,
            'present_days' => $record->present_days,
            'absent_days' => $record->absent_days,
            'leave_days' => $record->leave_days,
        ];
    }
}
