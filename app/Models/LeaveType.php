<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use Auditable;

    protected $fillable = ['code', 'name', 'uses_balance', 'default_quota', 'requires_attachment', 'is_active'];

    protected function casts(): array
    {
        return [
            'uses_balance' => 'boolean',
            'requires_attachment' => 'boolean',
            'is_active' => 'boolean',
            'default_quota' => 'integer',
        ];
    }
}
