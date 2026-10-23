<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\DepartmentRequest;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Department::class);

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : '',
        ];

        $departments = Department::query()
            ->with(['parent:id,name', 'head:id,full_name'])
            ->withCount(['positions', 'children', 'employees'])
            ->when($filters['search'] !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$filters['search']}%")
                ->orWhere('code', 'like', "%{$filters['search']}%")))
            ->when($filters['status'] !== '', fn ($query) => $query->where('is_active', $filters['status'] === 'active'))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Department $department) => [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
                'parent_id' => $department->parent_id,
                'parent_name' => $department->parent?->name,
                'head_employee_id' => $department->head_employee_id,
                'head_name' => $department->head?->full_name,
                'employees_count' => $department->employees_count,
                'description' => $department->description,
                'is_active' => $department->is_active,
                'positions_count' => $department->positions_count,
                'children_count' => $department->children_count,
            ]);

        return Inertia::render('organization/departments', [
            'departments' => $departments,
            'filters' => $filters,
            'parentOptions' => Department::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'headOptions' => Employee::query()->where('is_active', true)->orderBy('full_name')->get(['id', 'full_name', 'department_id']),
        ]);
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        Department::create($request->validated());

        return back()->with('success', 'Departemen berhasil ditambahkan.');
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        return back()->with('success', 'Departemen berhasil diperbarui.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        Gate::authorize('delete', $department);

        if ($department->children()->exists() || $department->positions()->exists() || $department->employees()->withTrashed()->exists()) {
            return back()->with('error', 'Departemen masih memiliki sub-departemen, jabatan, atau karyawan. Nonaktifkan saja, atau pindahkan datanya terlebih dahulu.');
        }

        $department->delete();

        return back()->with('success', 'Departemen berhasil dihapus.');
    }
}
