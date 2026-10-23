<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Notifications\HrisNotification;
use App\Services\Notifier;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Komponen gaji dan struktur gaji per karyawan (HRD, payroll.manage).
 */
class SalarySetupController extends Controller
{
    public function components(): Response
    {
        return Inertia::render('payroll/components', [
            'components' => SalaryComponent::query()->orderBy('type', 'desc')->orderBy('sort_order')->get()
                ->map(fn (SalaryComponent $c) => [
                    'id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'type' => $c->type,
                    'calculation' => $c->calculation, 'default_amount' => $c->default_amount,
                    'default_rate' => $c->default_rate, 'sort_order' => $c->sort_order, 'is_active' => $c->is_active,
                ]),
            'types' => SalaryComponent::TYPES,
            'calculations' => SalaryComponent::CALCULATIONS,
        ]);
    }

    public function storeComponent(Request $request): RedirectResponse
    {
        SalaryComponent::create($this->validatedComponent($request));

        return back()->with('success', 'Komponen gaji ditambahkan.');
    }

    public function updateComponent(Request $request, SalaryComponent $component): RedirectResponse
    {
        $component->update($this->validatedComponent($request, $component));

        return back()->with('success', 'Komponen gaji diperbarui. Perubahan berlaku pada perhitungan berikutnya.');
    }

    /** @return array<string, mixed> */
    private function validatedComponent(Request $request, ?SalaryComponent $component = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('salary_components', 'code')->ignore($component?->id)],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(SalaryComponent::TYPES))],
            'calculation' => ['required', Rule::in(array_keys(SalaryComponent::CALCULATIONS))],
            'default_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'default_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ], ['code.regex' => 'Kode hanya boleh huruf kapital, angka, dan tanda strip.'], [
            'code' => 'kode', 'name' => 'nama', 'type' => 'jenis', 'calculation' => 'cara hitung',
            'default_amount' => 'nominal default', 'default_rate' => 'persen default',
        ]);

        $data['sort_order'] ??= 0;
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : ($component?->is_active ?? true);

        return $data;
    }

    public function structures(Request $request): Response
    {
        $today = now(config('app.display_timezone'))->toDateString();
        $search = trim((string) $request->query('search', ''));

        $employees = Employee::query()
            ->where('is_active', true)
            ->with(['department:id,name', 'position:id,name'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('full_name', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%")))
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Employee $e) use ($today) {
                $structure = $e->salaryStructureOn($today);

                return [
                    'id' => $e->id,
                    'full_name' => $e->full_name,
                    'nik' => $e->nik,
                    'department' => $e->department?->name,
                    'position' => $e->position?->name,
                    'structure' => $structure ? [
                        'base_salary' => $structure->base_salary,
                        'effective_from' => $structure->effective_from->toDateString(),
                        'items' => $structure->items->map(fn ($i) => [
                            'salary_component_id' => $i->salary_component_id,
                            'amount' => $i->amount,
                            'rate' => $i->rate,
                        ])->values(),
                    ] : null,
                ];
            });

        return Inertia::render('payroll/structures', [
            'employees' => $employees,
            'filters' => ['search' => $search],
            'components' => SalaryComponent::query()->where('is_active', true)->orderBy('type', 'desc')->orderBy('sort_order')
                ->get(['id', 'code', 'name', 'type', 'calculation', 'default_amount', 'default_rate']),
        ]);
    }

    /**
     * Simpan struktur gaji baru yang berlaku sejak tanggal tertentu; struktur sebelumnya ditutup sehari sebelumnya.
     */
    public function storeStructure(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'base_salary' => ['required', 'numeric', 'gt:0', 'max:9999999999999', 'decimal:0,2'],
            'effective_from' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
            'items' => ['array'],
            'items.*.salary_component_id' => ['required', 'integer', 'distinct', Rule::exists('salary_components', 'id')->where('is_active', true)],
            'items.*.amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'items.*.rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
        ], [], [
            'base_salary' => 'gaji pokok', 'effective_from' => 'berlaku mulai',
            'items.*.amount' => 'nominal', 'items.*.rate' => 'persen',
        ]);

        $from = CarbonImmutable::parse($data['effective_from'])->toDateString();

        DB::transaction(function () use ($employee, $data, $from) {
            if ($employee->salaryStructures()->whereDate('effective_from', '>=', $from)->exists()) {
                throw ValidationException::withMessages(['effective_from' => 'Sudah ada struktur gaji yang berlaku pada atau setelah tanggal ini.']);
            }

            $employee->salaryStructures()
                ->whereNull('effective_to')
                ->update(['effective_to' => CarbonImmutable::parse($from)->subDay()->toDateString()]);

            $structure = SalaryStructure::create([
                'employee_id' => $employee->id,
                'base_salary' => Money::of($data['base_salary']),
                'effective_from' => $from,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] ?? [] as $item) {
                $structure->items()->create([
                    'salary_component_id' => $item['salary_component_id'],
                    'amount' => $item['amount'] ?? null,
                    'rate' => $item['rate'] ?? null,
                ]);
            }
        });

        // Nominal tidak disebut di notifikasi; karyawan melihatnya di slip gaji.
        app(Notifier::class)->send($employee, new HrisNotification('account', 'Struktur gaji Anda diperbarui',
            'Struktur gaji Anda diperbarui, berlaku mulai '.CarbonImmutable::parse($from)->locale('id')->translatedFormat('j F Y').'.',
            route('payroll.my-slips', absolute: false), 'Lihat slip gaji'), except: $request->user());

        return back()->with('success', "Struktur gaji {$employee->full_name} disimpan, berlaku mulai {$from}.");
    }
}
