<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRecord extends Model
{
    protected $fillable = [
        'payroll_period_id', 'employee_id', 'employee_name', 'nik', 'department_name', 'position_name',
        'bank_name', 'bank_account_number', 'bank_account_holder',
        'base_salary', 'total_earnings', 'total_deductions', 'net_pay',
        'overtime_minutes', 'present_days', 'absent_days', 'leave_days',
    ];

    protected $hidden = ['bank_account_number'];

    protected function casts(): array
    {
        return [
            'bank_account_number' => 'encrypted',
            'base_salary' => 'decimal:2',
            'total_earnings' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'overtime_minutes' => 'integer',
        ];
    }

    /** @return BelongsTo<PayrollPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return HasMany<PayrollItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class)->orderBy('type', 'desc')->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    public function maskedAccount(): ?string
    {
        $number = (string) $this->bank_account_number;

        return $number === '' ? null : '****'.substr($number, -4);
    }

    public function isPaid(): bool
    {
        return $this->payments()->where('status', PaymentStatus::Paid->value)->exists();
    }
}
