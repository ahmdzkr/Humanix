<?php

namespace App\Http\Controllers\Employees;

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Exports\EmployeesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\EmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Services\EmployeeService;
use App\Services\IdCardService;
use App\Support\FilePreview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $employees) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Employee::class);

        $filters = $this->filters($request);

        $employees = $this->filteredQuery($request, $filters)
            ->with(['user:id,email', 'department:id,name', 'position:id,name'])
            ->orderBy('full_name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Employee $employee) => [
                'id' => $employee->id,
                'nik' => $employee->nik,
                'full_name' => $employee->full_name,
                'email' => $employee->user?->email,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'employment_status' => $employee->employment_status->label(),
                'is_active' => $employee->is_active,
                'photo_url' => $this->photoUrl($employee),
            ]);

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'filters' => $filters,
            'departmentOptions' => Department::query()->orderBy('name')->get(['id', 'name']),
            'employmentStatuses' => EmploymentStatus::options(),
            'can' => [
                'create' => $request->user()->can('create', Employee::class),
                'export' => $request->user()->can('export', Employee::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Employee::class);

        return Inertia::render('employees/form', [
            'employee' => null,
            ...$this->employees->formOptions(),
        ]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $employee = $this->employees->create($request->validated(), $request->file('photo'));

        return redirect()->route('employees.show', $employee)
            ->with('success', "Karyawan {$employee->full_name} berhasil ditambahkan. Tautan untuk mengatur kata sandi sudah dikirim ke email karyawan.");
    }

    public function show(Request $request, Employee $employee): Response
    {
        Gate::authorize('view', $employee);

        $user = $request->user();
        $employee->load(['user', 'department', 'position', 'manager', 'bankAccount', 'histories.user:id,name']);
        $canViewBank = $user->can('viewBankAccount', $employee);

        return Inertia::render('employees/show', [
            'employee' => [
                ...$this->detail($employee),
                'role' => $employee->user->primaryRole()?->value,
                'bank_account' => $canViewBank && $employee->bankAccount ? [
                    'bank_name' => $employee->bankAccount->bank_name,
                    'account_number' => $employee->bankAccount->maskedNumber(),
                    'account_holder' => $employee->bankAccount->account_holder,
                ] : null,
                'subordinates_count' => $employee->subordinates()->count(),
                'schedule' => $employee->scheduleOn(now(config('app.display_timezone'))->toDateString())?->label(),
            ],
            'histories' => $employee->histories->take(30)->map(fn (EmployeeHistory $h) => [
                'id' => $h->id,
                'type' => $h->type,
                'label' => EmployeeHistory::TYPE_LABELS[$h->type] ?? $h->type,
                'changes' => $h->changes,
                'note' => $h->note,
                'actor' => $h->user?->name ?? 'Sistem',
                'created_at' => $h->created_at?->toIso8601String(),
            ])->values(),
            'roles' => array_column(Role::cases(), 'value'),
            'can' => [
                'update' => $user->can('update', $employee),
                'changeStatus' => $user->can('changeStatus', $employee),
                'rotateQr' => $user->can('rotateQr', $employee),
                'printIdCard' => $user->can('printIdCard', $employee),
                'changeRole' => $user->can('changeRole', $employee),
                'viewBankAccount' => $canViewBank,
            ],
        ]);
    }

    public function edit(Employee $employee): Response
    {
        Gate::authorize('update', $employee);

        $employee->load(['user', 'bankAccount']);

        return Inertia::render('employees/form', [
            'employee' => [
                ...$this->detail($employee),
                'department_id' => $employee->department_id,
                'position_id' => $employee->position_id,
                'manager_id' => $employee->manager_id,
                'employment_status_value' => $employee->employment_status->value,
                'work_schedule_id' => $employee->scheduleOn(now(config('app.display_timezone'))->toDateString())?->id,
                'bank_name' => $employee->bankAccount?->bank_name,
                'account_holder' => $employee->bankAccount?->account_holder,
                'account_number_masked' => $employee->bankAccount?->maskedNumber(),
            ],
            ...$this->employees->formOptions($employee),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->employees->update($employee, $request->validated(), $request->file('photo'));

        return redirect()->route('employees.show', $employee)->with('success', 'Data karyawan berhasil diperbarui.');
    }

    public function updateStatus(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('changeStatus', $employee);

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['note' => 'alasan']);

        $this->employees->setActive($employee, (bool) $data['is_active'], $data['note'] ?? null);

        return back()->with('success', $data['is_active'] ? 'Karyawan diaktifkan kembali.' : 'Karyawan dinonaktifkan. Akunnya tidak bisa login lagi.');
    }

    public function rotateQr(Employee $employee): RedirectResponse
    {
        Gate::authorize('rotateQr', $employee);

        $this->employees->rotateQrToken($employee);

        return back()->with('success', 'QR ID Card berhasil diganti. Cetak ulang ID Card karyawan ini.');
    }

    public function changeRole(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('changeRole', $employee);

        $data = $request->validate(['role' => ['required', Rule::enum(Role::class)]], [], ['role' => 'role']);

        $this->employees->changeRole($employee, Role::from($data['role']));

        return back()->with('success', 'Role akun berhasil diubah.');
    }

    public function sendPasswordLink(Employee $employee): RedirectResponse
    {
        Gate::authorize('update', $employee);

        $this->employees->sendPasswordSetupLink($employee);

        return back()->with('success', 'Tautan pengaturan kata sandi dikirim ke email karyawan (berlaku 60 menit).');
    }

    public function idCard(Employee $employee, IdCardService $idCards): HttpResponse
    {
        Gate::authorize('printIdCard', $employee);

        return $idCards->download($employee);
    }

    public function photo(Employee $employee): StreamedResponse
    {
        Gate::authorize('view', $employee);

        abort_unless($employee->photo_path && Storage::disk(EmployeeService::PHOTO_DISK)->exists($employee->photo_path), 404);

        return Storage::disk(EmployeeService::PHOTO_DISK)->response($employee->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function export(Request $request): BinaryFileResponse|JsonResponse
    {
        Gate::authorize('export', Employee::class);

        $query = $this->filteredQuery($request, $this->filters($request))->orderBy('full_name');

        return FilePreview::excel(new EmployeesExport($query), 'karyawan-'.now(config('app.display_timezone'))->format('Ymd-His').'.xlsx');
    }

    /**
     * @return array{search: string, department: string, employment_status: string, status: string}
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'department' => $request->integer('department') ? (string) $request->integer('department') : '',
            'employment_status' => EmploymentStatus::tryFrom((string) $request->query('employment_status'))?->value ?? '',
            'status' => in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : '',
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return Builder<Employee>
     */
    private function filteredQuery(Request $request, array $filters): Builder
    {
        return Employee::query()
            ->visibleTo($request->user())
            ->when($filters['search'] !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('full_name', 'like', "%{$filters['search']}%")
                ->orWhere('nik', 'like', "%{$filters['search']}%")
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$filters['search']}%"))))
            ->when($filters['department'] !== '', fn ($query) => $query->where('department_id', $filters['department']))
            ->when($filters['employment_status'] !== '', fn ($query) => $query->where('employment_status', $filters['employment_status']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('is_active', $filters['status'] === 'active'));
    }

    private function photoUrl(Employee $employee): ?string
    {
        return $employee->photo_path
            ? route('employees.photo', ['employee' => $employee, 'v' => substr(md5($employee->photo_path), 0, 8)], false)
            : null;
    }

    /** @return array<string, mixed> */
    private function detail(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'nik' => $employee->nik,
            'full_name' => $employee->full_name,
            'email' => $employee->user?->email,
            'gender' => $employee->gender,
            'gender_label' => Employee::GENDERS[$employee->gender] ?? null,
            'birth_place' => $employee->birth_place,
            'birth_date' => $employee->birth_date?->format('Y-m-d'),
            'phone' => $employee->phone,
            'address' => $employee->address,
            'department' => $employee->department?->name,
            'position' => $employee->position?->name,
            'manager' => $employee->manager ? ['id' => $employee->manager->id, 'full_name' => $employee->manager->full_name] : null,
            'employment_status' => $employee->employment_status->label(),
            'join_date' => $employee->join_date?->format('Y-m-d'),
            'contract_end_date' => $employee->contract_end_date?->format('Y-m-d'),
            'is_active' => $employee->is_active,
            'deactivated_at' => $employee->deactivated_at?->toIso8601String(),
            'photo_url' => $this->photoUrl($employee),
        ];
    }
}
