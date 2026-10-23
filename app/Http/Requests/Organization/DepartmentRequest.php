<?php

namespace App\Http\Requests\Organization;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            ? $this->user()->can('update', $department)
            : $this->user()->can('create', Department::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            // Field tidak dikirim: aktif untuk data baru, tetap seperti semula saat diubah.
            'is_active' => $this->has('is_active')
                ? $this->boolean('is_active')
                : ($this->route('department')?->is_active ?? true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('department')?->id;

        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('departments', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->ignore($id)],
            'parent_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            // Kepala departemen harus karyawan aktif di departemen ini (hanya saat ubah).
            'head_employee_id' => [
                'nullable', 'integer',
                Rule::exists('employees', 'id')->where('department_id', $id ?? 0)->where('is_active', true)->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $department = $this->route('department');
                $parentId = $this->integer('parent_id') ?: null;

                if ($department instanceof Department && $parentId !== null
                    && in_array($parentId, $department->descendantAndSelfIds(), true)) {
                    $validator->errors()->add('parent_id', 'Departemen induk tidak boleh departemen ini sendiri atau turunannya.');
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
            'code' => 'kode',
            'name' => 'nama departemen',
            'parent_id' => 'departemen induk',
            'head_employee_id' => 'kepala departemen',
            'description' => 'deskripsi',
            'is_active' => 'status aktif',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'head_employee_id.exists' => 'Kepala departemen harus karyawan aktif di departemen ini.',
            'code.regex' => 'Kode hanya boleh berisi huruf kapital, angka, dan tanda strip.',
        ];
    }
}
