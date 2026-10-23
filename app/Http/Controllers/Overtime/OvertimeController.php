<?php

namespace App\Http\Controllers\Overtime;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Services\ApprovalService;
use App\Services\OvertimeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeController extends Controller
{
    public function __construct(
        private readonly OvertimeService $overtimes,
        private readonly ApprovalService $approvals,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = $user->employee;
        $tz = config('app.display_timezone');

        $awaitingQuery = $this->approvals->awaiting($user, OvertimeRequest::class, OvertimeService::PERMISSION);
        $awaitingCount = (clone $awaitingQuery)->count();

        $tabs = array_values(array_filter([
            $employee && $user->can('overtime.self') ? 'mine' : null,
            ($awaitingCount > 0 || $user->can(OvertimeService::PERMISSION)) ? 'approvals' : null,
            $user->can('overtime.view') ? 'all' : null,
        ]));
        abort_if($tabs === [], 403);

        $tab = in_array($request->query('tab'), $tabs, true) ? $request->query('tab') : $tabs[0];
        $filters = [
            'tab' => $tab,
            'search' => trim((string) $request->query('search', '')),
            'status' => RequestStatus::tryFrom((string) $request->query('status'))?->value ?? '',
            'department' => $request->integer('department') ? (string) $request->integer('department') : '',
        ];

        $query = match ($tab) {
            'mine' => OvertimeRequest::query()->where('employee_id', $employee->id),
            'approvals' => $awaitingQuery,
            'all' => OvertimeRequest::query()->whereIn('employee_id', Employee::query()->visibleTo($user)->select('id')),
        };

        $requests = $query
            ->with(['employee:id,full_name,nik,department_id', 'employee.department:id,name'])
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($tab !== 'mine' && $filters['search'] !== '', fn ($q) => $q->whereHas('employee', fn ($e) => $e
                ->where('full_name', 'like', "%{$filters['search']}%")->orWhere('nik', 'like', "%{$filters['search']}%")))
            ->when($tab !== 'mine' && $filters['department'] !== '', fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $filters['department'])))
            ->latest('work_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (OvertimeRequest $o) => $this->row($o));

        $monthStart = now($tz)->startOfMonth()->toDateString();

        return Inertia::render('overtime/index', [
            'tabs' => $tabs,
            'filters' => $filters,
            'requests' => $requests,
            'awaitingCount' => $awaitingCount,
            'summary' => $employee ? [
                'approved_minutes' => (int) OvertimeRequest::query()->where('employee_id', $employee->id)
                    ->where('status', RequestStatus::Approved->value)->whereDate('work_date', '>=', $monthStart)->sum('duration_minutes'),
                'pending_minutes' => (int) OvertimeRequest::query()->where('employee_id', $employee->id)
                    ->where('status', RequestStatus::Pending->value)->sum('duration_minutes'),
            ] : null,
            'maxMinutesPerDay' => $this->overtimes->maxMinutesPerDay(),
            'statusOptions' => RequestStatus::options(),
            'departmentOptions' => $tab === 'all' ? Department::query()->orderBy('name')->get(['id', 'name']) : [],
            'canSubmit' => $user->can('create', OvertimeRequest::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', OvertimeRequest::class);
        $tz = config('app.display_timezone');

        $data = $request->validate([
            'work_date' => [
                'required', 'date',
                'after_or_equal:'.now($tz)->subDays(30)->toDateString(),
                'before_or_equal:'.now($tz)->addDays(30)->toDateString(),
            ],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'work_date.after_or_equal' => 'Tanggal lembur paling lambat 30 hari ke belakang.',
            'work_date.before_or_equal' => 'Tanggal lembur paling jauh 30 hari ke depan.',
            'end_time.after' => 'Jam selesai harus setelah jam mulai (lembur lintas tengah malam belum didukung).',
        ], [
            'work_date' => 'tanggal',
            'start_time' => 'jam mulai',
            'end_time' => 'jam selesai',
            'reason' => 'alasan',
        ]);

        $overtime = $this->overtimes->submit($request->user()->employee, $data);

        return redirect()->route('overtime.show', $overtime)->with('success', 'Pengajuan lembur berhasil dikirim.');
    }

    public function show(Request $request, OvertimeRequest $overtime): Response
    {
        Gate::authorize('view', $overtime);
        $overtime->load(['employee.department', 'employee.position']);

        return Inertia::render('overtime/show', [
            'overtime' => [
                ...$this->row($overtime),
                'reason' => $overtime->reason,
                'position' => $overtime->employee->position?->name,
                'version' => $overtime->version,
                'payable' => $overtime->payable,
            ],
            'steps' => $this->approvals->stepsForDisplay($overtime),
            'can' => [
                'decide' => $request->user()->can('decide', $overtime),
                'cancel' => $request->user()->can('cancel', $overtime),
            ],
        ]);
    }

    public function decide(Request $request, OvertimeRequest $overtime): RedirectResponse
    {
        abort_unless(app(ApprovalService::class)->isApproverOf($request->user(), $overtime, OvertimeService::PERMISSION), 403);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => [Rule::requiredIf($request->input('decision') === 'reject'), 'nullable', 'string', 'max:255'],
            'version' => ['required', 'integer'],
        ], ['note.required' => 'Alasan penolakan wajib diisi.'], ['note' => 'catatan']);

        $approve = $data['decision'] === 'approve';
        $this->overtimes->decide($request->user(), $overtime, $approve, $data['note'] ?? null, (int) $data['version']);

        return back()->with('success', $approve ? 'Pengajuan lembur disetujui.' : 'Pengajuan lembur ditolak.');
    }

    public function cancel(OvertimeRequest $overtime): RedirectResponse
    {
        Gate::authorize('cancel', $overtime);

        $this->overtimes->cancel($overtime);

        return back()->with('success', 'Pengajuan lembur dibatalkan.');
    }

    /** @return array<string, mixed> */
    private function row(OvertimeRequest $o): array
    {
        $tz = config('app.display_timezone');

        return [
            'id' => $o->id,
            'employee_name' => $o->employee?->full_name,
            'employee_nik' => $o->employee?->nik,
            'department' => $o->employee?->department?->name,
            'work_date' => $o->work_date->toDateString(),
            'start_time' => $o->start_at->setTimezone($tz)->format('H:i'),
            'end_time' => $o->end_at->setTimezone($tz)->format('H:i'),
            'duration_minutes' => $o->duration_minutes,
            'status' => $o->status->value,
            'status_label' => $o->status->label(),
            'created_at' => $o->created_at?->toIso8601String(),
        ];
    }
}
