<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryComponent extends Model
{
    use Auditable;

    public const TYPES = ['earning' => 'Pendapatan', 'deduction' => 'Potongan'];

    public const CALCULATIONS = ['fixed' => 'Nominal tetap', 'percentage' => 'Persen dari gaji pokok'];

    protected $fillable = ['code', 'name', 'type', 'calculation', 'default_amount', 'default_rate', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:2',
            'default_rate' => 'decimal:4',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<SalaryStructureItem, $this> */
    public function structureItems(): HasMany
    {
        return $this->hasMany(SalaryStructureItem::class);
    }
}
