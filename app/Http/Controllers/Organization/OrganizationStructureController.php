<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationStructureController extends Controller
{
    public function __invoke(): Response
    {
        $departments = Department::query()
            ->active()
            ->with(['positions' => fn ($query) => $query
                ->where('is_active', true)
                ->orderByDesc('level')
                ->orderBy('name')])
            ->orderBy('name')
            ->get();

        return Inertia::render('organization/structure', [
            'tree' => $this->buildTree($departments->groupBy('parent_id'), null),
            // Posisi tanpa departemen (mis. Direktur Utama) ditampilkan di puncak.
            'unassignedPositions' => Position::query()
                ->whereNull('department_id')
                ->where('is_active', true)
                ->orderByDesc('level')
                ->get(['id', 'code', 'name', 'level']),
        ]);
    }

    /**
     * @param  Collection<int|string, Collection<int, Department>>  $byParent
     * @return list<array<string, mixed>>
     */
    private function buildTree(Collection $byParent, ?int $parentId): array
    {
        return $byParent->get($parentId ?? '', collect())
            ->map(fn (Department $department) => [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
                'positions' => $department->positions
                    ->map(fn ($position) => $position->only(['id', 'code', 'name', 'level']))
                    ->values()
                    ->all(),
                'children' => $this->buildTree($byParent, $department->id),
            ])
            ->values()
            ->all();
    }
}
