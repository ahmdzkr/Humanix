<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\PositionRequest;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PositionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Position::class);

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'department' => (string) $request->integer('department') ?: '',
            'status' => in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : '',
        ];

        $positions = Position::query()
            ->with('department:id,name')
            ->when($filters['search'] !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$filters['search']}%")
                ->orWhere('code', 'like', "%{$filters['search']}%")))
            ->when($filters['department'] !== '', fn ($query) => $query->where('department_id', $filters['department']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('is_active', $filters['status'] === 'active'))
            ->orderBy('level', 'desc')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Position $position) => [
                'id' => $position->id,
                'code' => $position->code,
                'name' => $position->name,
                'department_id' => $position->department_id,
                'department_name' => $position->department?->name,
                'level' => $position->level,
                'description' => $position->description,
                'is_active' => $position->is_active,
            ]);

        return Inertia::render('organization/positions', [
            'positions' => $positions,
            'filters' => $filters,
            'departmentOptions' => Department::query()->orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }

    public function store(PositionRequest $request): RedirectResponse
    {
        Position::create($request->validated());

        return back()->with('success', 'Jabatan berhasil ditambahkan.');
    }

    public function update(PositionRequest $request, Position $position): RedirectResponse
    {
        $position->update($request->validated());

        return back()->with('success', 'Jabatan berhasil diperbarui.');
    }

    public function destroy(Position $position): RedirectResponse
    {
        Gate::authorize('delete', $position);

        if ($position->employees()->withTrashed()->exists()) {
            return back()->with('error', 'Jabatan masih dipakai karyawan. Nonaktifkan saja, atau pindahkan karyawannya terlebih dahulu.');
        }

        $position->delete();

        return back()->with('success', 'Jabatan berhasil dihapus.');
    }
}
