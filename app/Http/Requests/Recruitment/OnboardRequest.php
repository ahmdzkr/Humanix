<?php

namespace App\Http\Requests\Recruitment;

use App\Http\Requests\Employees\EmployeeRequest;
use App\Models\Employee;

/**
 * Data karyawan baru dari pelamar yang diterima. Aturan validasi sama persis dengan form karyawan.
 */
class OnboardRequest extends EmployeeRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recruitment.manage') && $this->user()->can('create', Employee::class);
    }
}
