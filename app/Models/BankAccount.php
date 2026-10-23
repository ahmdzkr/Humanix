<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    use Auditable;

    protected $fillable = ['employee_id', 'bank_name', 'account_number', 'account_holder'];

    protected $hidden = ['account_number'];

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Nomor rekening tersamar, mis. ****1234. */
    public function maskedNumber(): string
    {
        $digits = (string) $this->account_number;

        return str_repeat('*', 4).substr($digits, -4);
    }
}
