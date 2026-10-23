<?php

namespace App\Http\Requests\Organization;

use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $position = $this->route('position');

        return $position instanceof Position
            ? $this->user()->can('update', $position)
            : $this->user()->can('create', Position::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            // Field tidak dikirim: aktif untuk data baru, tetap seperti semula saat diubah.
            'is_active' => $this->has('is_active')
                ? $this->boolean('is_active')
                : ($this->route('position')?->is_active ?? true),
            'department_id' => $this->input('department_id') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('position')?->id;

        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('positions', 'code')->ignore($id)],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('positions', 'name')
                    ->where(fn ($query) => $this->input('department_id') === null
                        ? $query->whereNull('department_id')
                        : $query->where('department_id', $this->input('department_id')))
                    ->ignore($id),
            ],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'level' => ['required', 'integer', 'min:1', 'max:10'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'kode',
            'name' => 'nama jabatan',
            'department_id' => 'departemen',
            'level' => 'level',
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
            'code.regex' => 'Kode hanya boleh berisi huruf kapital, angka, dan tanda strip.',
            'name.unique' => 'Nama jabatan sudah ada di departemen ini.',
        ];
    }
}
