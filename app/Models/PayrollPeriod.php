<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'start_date', 'end_date', 'pay_date', 'status', 'version',
        'calculated_at', 'calculated_by', 'reviewed_at', 'reviewed_by',
        'approved_at', 'approved_by', 'closed_at', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'pay_date' => 'date',
            'status' => PayrollStatus::class,
            'version' => 'integer',
            'calculated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return HasMany<PayrollRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(PayrollRecord::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function label(): string
    {
        return $this->start_date->locale('id')->translatedFormat('F Y');
    }
}
