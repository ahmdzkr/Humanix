<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\OwnProfileRequest;
use App\Services\EmployeeService;
use App\Services\IdCardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Profil karyawan milik pengguna yang sedang login (/profil-saya).
 */
class OwnProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 404, 'Akun Anda belum terhubung dengan data karyawan.');

        $employee->load(['department', 'position', 'manager', 'bankAccount']);

        return Inertia::render('employees/my-profile', [
            'employee' => [
                'id' => $employee->id,
                'nik' => $employee->nik,
                'full_name' => $employee->full_name,
                'email' => $request->user()->email,
                'gender_label' => $employee::GENDERS[$employee->gender] ?? null,
                'birth_place' => $employee->birth_place,
                'birth_date' => $employee->birth_date?->format('Y-m-d'),
                'phone' => $employee->phone,
                'address' => $employee->address,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'manager' => $employee->manager?->full_name,
                'employment_status' => $employee->employment_status->label(),
                'join_date' => $employee->join_date?->format('Y-m-d'),
                'contract_end_date' => $employee->contract_end_date?->format('Y-m-d'),
                'photo_url' => $employee->photo_path
                    ? route('employees.photo', ['employee' => $employee, 'v' => substr(md5($employee->photo_path), 0, 8)], false)
                    : null,
                'bank_account' => $employee->bankAccount ? [
                    'bank_name' => $employee->bankAccount->bank_name,
                    'account_number' => $employee->bankAccount->maskedNumber(),
                    'account_holder' => $employee->bankAccount->account_holder,
                ] : null,
            ],
        ]);
    }

    public function update(OwnProfileRequest $request, EmployeeService $employees): RedirectResponse
    {
        $employees->updateOwnProfile($request->user()->employee, $request->validated(), $request->file('photo'));

        return back()->with('success', 'Profil Anda berhasil diperbarui.');
    }

    public function idCard(Request $request, IdCardService $idCards): HttpResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 404);

        return $idCards->download($employee);
    }
}
