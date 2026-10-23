<?php

namespace App\Http\Controllers\Leave;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\ApprovalService;
use App\Services\LeaveService;
use App\Support\FilePreview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveController extends Controller
{
    public function __construct(
        private readonly LeaveService $leaves,
        private readonly ApprovalService $approvals,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = $user->employee;
        $year = (int) now(config('app.display_timezone'))->year;

        $canSubmit = $user->can('create', LeaveRequest::class);
        $canViewAll = $user->can('leave.view');
        $awaitingQuery = $this->approvals->awaiting($user, LeaveRequest::class, LeaveService::PERMISSION);
        $awaitingCount = (clone $awaitingQuery)->count();

        $tabs = array_values(array_filter([
            $employee && $user->can('leave.self') ? 'mine' : null,
            ($awaitingCount > 0 || $user->can(LeaveService::PERMISSION)) ? 'approvals' : null,
            $canViewAll ? 'all' : null,
        ]));
        abort_if($tabs === [], 403);

        $tab = in_array($request->query('tab'), $tabs, true) ? $request->query('tab') : $tabs[0];
        $filters = [
            'tab' => $tab,
            'search' => trim((string) $request->query('search', '')),
            'status' => RequestStatus::tryFrom((string) $request->query('status'))?->value ?? '',
            'type' => $request->integer('type') ? (string) $request->integer('type') : '',
            'department' => $request->integer('department') ? (string) $request->integer('department') : '',
        ];

        $query = match ($tab) {
            'mine' => LeaveRequest::query()->where('employee_id', $employee->id),
            'approvals' => $awaitingQuery,
            'all' => LeaveRequest::query()->whereIn('employee_id', Employee::query()->visibleTo($user)->select('id')),
        };

        $requests = $query
            ->with(['employee:id,full_name,nik,department_id', 'employee.department:id,name', 'leaveType:id,name'])
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['type'] !== '', fn ($q) => $q->where('leave_type_id', $filters['type']))
            ->when($tab !== 'mine' && $filters['search'] !== '', fn ($q) => $q->whereHas('employee', fn ($e) => $e
                ->where('full_name', 'like', "%{$filters['search']}%")->orWhere('nik', 'like', "%{$filters['search']}%")))
            ->when($tab !== 'mine' && $filters['department'] !== '', fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $filters['department'])))
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (LeaveRequest $r) => $this->row($r));

        $types = LeaveType::query()->where('is_active', true)->orderBy('name')->get();

        return Inertia::render('leave/index', [
            'tabs' => $tabs,
            'filters' => $filters,
            'requests' => $requests,
            'awaitingCount' => $awaitingCount,
            'balances' => $employee ? $types->where('uses_balance', true)->map(function (LeaveType $type) use ($employee, $year) {
                $balance = $this->leaves->balanceFor($employee, $type, $year);
                $pending = $this->leaves->pendingDays($employee, $type, $year);

                return [
                    'type' => $type->name,
                    'year' => $year,
                    'quota' => $balance->quota,
                    'used' => $balance->used,
                    'pending' => $pending,
                    'remaining' => max(0, $balance->remaining() - $pending),
                ];
            })->values() : [],
            'leaveTypes' => $types->map(fn (LeaveType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'uses_balance' => $t->uses_balance,
                'requires_attachment' => $t->requires_attachment,
            ])->values(),
            'statusOptions' => RequestStatus::options(),
            'departmentOptions' => $tab === 'all' ? Department::query()->orderBy('name')->get(['id', 'name']) : [],
            'canSubmit' => $canSubmit,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', LeaveRequest::class);

        $data = $request->validate([
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date', 'after_or_equal:'.now(config('app.display_timezone'))->subDays(30)->toDateString()],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ], [
            'start_date.after_or_equal' => 'Tanggal mulai paling lambat 30 hari ke belakang.',
            'end_date.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal mulai.',
        ], [
            'leave_type_id' => 'jenis cuti/izin',
            'start_date' => 'tanggal mulai',
            'end_date' => 'tanggal akhir',
            'reason' => 'alasan',
            'attachment' => 'lampiran',
        ]);

        $leave = $this->leaves->submit($request->user()->employee, $data, $request->file('attachment'));

        return redirect()->route('leave.show', $leave)->with('success', $leave->status === RequestStatus::Approved
            ? 'Pengajuan otomatis disetujui (tidak ada langkah persetujuan).'
            : 'Pengajuan berhasil dikirim dan menunggu persetujuan.');
    }

    public function show(Request $request, LeaveRequest $leave): Response
    {
        Gate::authorize('view', $leave);
        $leave->load(['employee.department', 'employee.position', 'leaveType']);

        return Inertia::render('leave/show', [
            'leave' => [
                ...$this->row($leave),
                'reason' => $leave->reason,
                'position' => $leave->employee->position?->name,
                'has_attachment' => $leave->attachment_path !== null,
                'attachment_kind' => FilePreview::kind($leave->attachment_path),
                'version' => $leave->version,
            ],
            'steps' => $this->approvals->stepsForDisplay($leave),
            'can' => [
                'decide' => $request->user()->can('decide', $leave),
                'cancel' => $request->user()->can('cancel', $leave),
            ],
        ]);
    }

    public function decide(Request $request, LeaveRequest $leave): RedirectResponse
    {
        abort_unless(app(ApprovalService::class)->isApproverOf($request->user(), $leave, LeaveService::PERMISSION), 403);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => [Rule::requiredIf($request->input('decision') === 'reject'), 'nullable', 'string', 'max:255'],
            'version' => ['required', 'integer'],
        ], ['note.required' => 'Alasan penolakan wajib diisi.'], ['note' => 'catatan']);

        $approve = $data['decision'] === 'approve';
        $this->leaves->decide($request->user(), $leave, $approve, $data['note'] ?? null, (int) $data['version']);

        return back()->with('success', $approve ? 'Pengajuan disetujui.' : 'Pengajuan ditolak.');
    }

    public function cancel(LeaveRequest $leave): RedirectResponse
    {
        Gate::authorize('cancel', $leave);

        $this->leaves->cancel($leave);

        return back()->with('success', 'Pengajuan dibatalkan.');
    }

    public function attachment(LeaveRequest $leave): StreamedResponse
    {
        Gate::authorize('view', $leave);
        abort_unless($leave->attachment_path && Storage::disk(LeaveService::ATTACHMENT_DISK)->exists($leave->attachment_path), 404);

        return FilePreview::stored(LeaveService::ATTACHMENT_DISK, $leave->attachment_path);
    }

    /** @return array<string, mixed> */
    private function row(LeaveRequest $r): array
    {
        return [
            'id' => $r->id,
            'employee_name' => $r->employee?->full_name,
            'employee_nik' => $r->employee?->nik,
            'department' => $r->employee?->department?->name,
            'type' => $r->leaveType?->name,
            'start_date' => $r->start_date->toDateString(),
            'end_date' => $r->end_date->toDateString(),
            'days' => $r->days,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
