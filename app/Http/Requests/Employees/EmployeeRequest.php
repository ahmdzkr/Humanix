<?php

namespace App\Http\Requests\Employees;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->employee();

        return $employee
            ? $this->user()->can('update', $employee)
            : $this->user()->can('create', Employee::class);
    }

    private function employee(): ?Employee
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee ? $employee : null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nik' => strtoupper(trim((string) $this->input('nik'))),
            'email' => strtolower(trim((string) $this->input('email'))),
            'manager_id' => $this->input('manager_id') ?: null,
            'account_number' => preg_replace('/\s+/', '', (string) $this->input('account_number')) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $employee = $this->employee();
        $needsContractEnd = $this->input('employment_status') === EmploymentStatus::Contract->value;
        $hasBank = $employee?->bankAccount()->exists() ?? false;

        return [
            'nik' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9.\-\/]+$/', Rule::unique('employees', 'nik')->ignore($employee?->id)],
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee?->user_id)],
            'gender' => ['nullable', Rule::in(array_keys(Employee::GENDERS))],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'position_id' => ['required', 'integer', Rule::exists('positions', 'id')->where('is_active', true)],
            'manager_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'join_date' => ['required', 'date'],
            'contract_end_date' => [$needsContractEnd ? 'required' : 'nullable', 'date', 'after:join_date'],
            'work_schedule_id' => ['nullable', 'integer', Rule::exists('work_schedules', 'id')->where('is_active', true)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'bank_name' => ['nullable', 'required_with:account_number', 'string', 'max:100'],
            'account_number' => [$hasBank ? 'nullable' : 'required_with:bank_name', 'nullable', 'digits_between:6,20'],
            'account_holder' => ['nullable', 'required_with:bank_name', 'string', 'max:150'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $employee = $this->employee();
                $managerId = $this->input('manager_id') ? (int) $this->input('manager_id') : null;

                if ($employee && $managerId !== null
                    && in_array($managerId, [$employee->id, ...$employee->subordinateIds()], true)) {
                    $validator->errors()->add('manager_id', 'Atasan tidak boleh karyawan ini sendiri atau bawahannya.');
                }

                $position = Position::query()->find($this->input('position_id'));

                if ($position?->department_id !== null && (int) $position->department_id !== (int) $this->input('department_id')) {
                    $validator->errors()->add('position_id', 'Jabatan tidak termasuk dalam departemen yang dipilih.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nik' => 'NIK',
            'full_name' => 'nama lengkap',
            'email' => 'email',
            'gender' => 'jenis kelamin',
            'birth_place' => 'tempat lahir',
            'birth_date' => 'tanggal lahir',
            'phone' => 'nomor telepon',
            'address' => 'alamat',
            'department_id' => 'departemen',
            'position_id' => 'jabatan',
            'manager_id' => 'atasan',
            'employment_status' => 'status kepegawaian',
            'join_date' => 'tanggal masuk',
            'contract_end_date' => 'tanggal akhir kontrak',
            'work_schedule_id' => 'jadwal kerja',
            'photo' => 'foto',
            'bank_name' => 'nama bank',
            'account_number' => 'nomor rekening',
            'account_holder' => 'nama pemilik rekening',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nik.regex' => 'NIK hanya boleh berisi huruf kapital, angka, titik, strip, dan garis miring.',
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, +, -, dan tanda kurung.',
            'contract_end_date.required' => 'Tanggal akhir kontrak wajib diisi untuk karyawan kontrak.',
            'department_id.exists' => 'Departemen tidak ditemukan atau sudah nonaktif.',
            'position_id.exists' => 'Jabatan tidak ditemukan atau sudah nonaktif.',
            'manager_id.exists' => 'Atasan harus karyawan yang masih aktif.',
        ];
    }
}
