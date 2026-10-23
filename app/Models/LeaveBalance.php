<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    use Auditable;

    protected $fillable = ['employee_id', 'leave_type_id', 'year', 'quota', 'used'];

    protected function casts(): array
    {
        return [
            'quota' => 'float',
            'used' => 'float',
            'year' => 'integer',
        ];
    }

    /** @return BelongsTo<LeaveType, $this> */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function remaining(): float
    {
        return max(0, $this->quota - $this->used);
    }
}
