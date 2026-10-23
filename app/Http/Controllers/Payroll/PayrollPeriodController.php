<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\PaymentStatus;
use App\Enums\PayrollStatus;
use App\Exports\PayrollExport;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Services\PayrollService;
use App\Support\FilePreview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayrollPeriodController extends Controller
{
    public function __construct(private readonly PayrollService $payroll) {}

    /** Daftar periode (HRD). Pengguna tanpa akses payroll diarahkan ke slip miliknya. */
    public function index(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->can('payroll.view')) {
            return redirect()->route('payroll.my-slips');
        }

        $periods = PayrollPeriod::query()
            ->withCount('records')
            ->withSum('records', 'net_pay')
            ->latest('start_date')
            ->paginate(12)
            ->through(fn (PayrollPeriod $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'label' => $p->label(),
                'pay_date' => $p->pay_date->toDateString(),
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'records_count' => $p->records_count,
                'net_total' => $p->records_sum_net_pay ?? '0.00',
            ]);

        return Inertia::render('payroll/index', [
            'periods' => $periods,
            'can' => ['manage' => $request->user()->can('payroll.manage')],
            'suggestedMonth' => now(config('app.display_timezone'))->format('Y-m'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'pay_date' => ['required', 'date'],
        ], [], ['month' => 'bulan', 'pay_date' => 'tanggal pembayaran']);

        $period = $this->payroll->createPeriod($data['month'], $data['pay_date']);

        return redirect()->route('payroll.periods.show', $period)->with('success', "Periode {$period->label()} dibuat. Klik Hitung untuk menghitung gaji.");
    }

    public function show(Request $request, PayrollPeriod $period): Response
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));

        $records = $period->records()
            ->with(['payments' => fn ($q) => $q->latest('id')])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('employee_name', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%")))
            ->orderBy('employee_name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PayrollRecord $r) => [
                'id' => $r->id,
                'employee_name' => $r->employee_name,
                'nik' => $r->nik,
                'department' => $r->department_name,
                'total_earnings' => $r->total_earnings,
                'total_deductions' => $r->total_deductions,
                'net_pay' => $r->net_pay,
                'overtime_minutes' => $r->overtime_minutes,
                'payment_status' => $r->payments->first()?->status->value,
                'payment_status_label' => $r->payments->first()?->status->label(),
            ]);

        $totals = $period->records()->selectRaw('COUNT(*) as count, COALESCE(SUM(total_earnings),0) as earnings, COALESCE(SUM(total_deductions),0) as deductions, COALESCE(SUM(net_pay),0) as net')->first();
        $paid = Payment::query()->whereIn('payroll_record_id', $period->records()->select('id'))->where('status', PaymentStatus::Paid->value)->count();

        $status = $period->status;

        return Inertia::render('payroll/period', [
            'period' => [
                'id' => $period->id,
                'code' => $period->code,
                'label' => $period->label(),
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'pay_date' => $period->pay_date->toDateString(),
                'status' => $status->value,
                'status_label' => $status->label(),
                'version' => $period->version,
                'calculated_at' => $period->calculated_at?->toIso8601String(),
                'reviewed_at' => $period->reviewed_at?->toIso8601String(),
                'approved_at' => $period->approved_at?->toIso8601String(),
                'closed_at' => $period->closed_at?->toIso8601String(),
            ],
            'records' => $records,
            'filters' => ['search' => $search],
            'totals' => [
                'count' => (int) $totals->count,
                'earnings' => (string) $totals->earnings,
                'deductions' => (string) $totals->deductions,
                'net' => (string) $totals->net,
                'paid' => $paid,
            ],
            'can' => [
                'calculate' => $user->can('payroll.manage') && $status->isEditable(),
                'review' => $user->can('payroll.manage') && $status === PayrollStatus::Calculated,
                'approve' => $user->can('payroll.approve') && $status === PayrollStatus::Reviewed,
                'pay' => $user->can('payroll.manage') && $status === PayrollStatus::Approved,
                'close' => $user->can('payroll.manage') && $status === PayrollStatus::Approved,
                'export' => $user->can('payroll.view'),
            ],
        ]);
    }

    public function calculate(Request $request, PayrollPeriod $period): RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'integer']]);
        $result = $this->payroll->calculate($period, $request->user(), (int) $data['version']);

        $message = "{$result['calculated']} slip gaji dihitung.";

        if ($result['skipped'] !== []) {
            return back()->with('success', $message)->with('error', 'Dilewati karena belum punya struktur gaji: '.implode(', ', $result['skipped']).'.');
        }

        return back()->with('success', $message);
    }

    public function advance(Request $request, PayrollPeriod $period): RedirectResponse
    {
        $data = $request->validate([
            'target' => ['required', Rule::in(['reviewed', 'approved', 'closed'])],
            'version' => ['required', 'integer'],
        ]);

        $target = PayrollStatus::from($data['target']);
        $permission = $target === PayrollStatus::Approved ? 'payroll.approve' : 'payroll.manage';
        abort_unless($request->user()->can($permission), 403);

        $this->payroll->advance($period, $target, $request->user(), (int) $data['version']);

        return back()->with('success', "Status periode menjadi {$target->label()}.");
    }

    public function createPayments(Request $request, PayrollPeriod $period): RedirectResponse
    {
        $count = $this->payroll->createPayments($period, $request->user());

        return back()->with('success', $count > 0 ? "{$count} instruksi pembayaran dibuat." : 'Semua slip sudah punya instruksi pembayaran.');
    }

    public function export(PayrollPeriod $period): BinaryFileResponse|JsonResponse
    {
        return FilePreview::excel(new PayrollExport($period), "payroll-{$period->code}.xlsx");
    }
}
