<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PerformanceIndicator extends Model
{
    use Auditable;

    protected $fillable = ['performance_period_id', 'name', 'description', 'weight', 'scale_min', 'scale_max', 'target', 'sort_order'];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'scale_min' => 'integer',
            'scale_max' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
