<?php

namespace App\Http\Requests\Employees;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Karyawan hanya boleh mengubah telepon, alamat, dan foto miliknya.
 * Field lain ditolak tegas (bukan diabaikan diam-diam).
 */
class OwnProfileRequest extends FormRequest
{
    /** Field yang hanya boleh diubah HRD. */
    public const LOCKED_FIELDS = [
        'nik', 'full_name', 'email', 'gender', 'birth_place', 'birth_date',
        'department_id', 'position_id', 'manager_id', 'employment_status',
        'join_date', 'contract_end_date', 'is_active', 'qr_token', 'user_id',
        'bank_name', 'account_number', 'account_holder',
    ];

    public function authorize(): bool
    {
        return $this->user()->employee !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            ...array_fill_keys(self::LOCKED_FIELDS, ['prohibited']),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prohibited' => ':Attribute hanya dapat diubah oleh HRD.',
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, +, -, dan tanda kurung.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return (new EmployeeRequest)->attributes();
    }
}
