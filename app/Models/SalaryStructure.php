<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryStructure extends Model
{
    use Auditable;

    protected $table = 'employee_salary_structures';

    protected $fillable = ['employee_id', 'base_salary', 'effective_from', 'effective_to', 'notes'];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return HasMany<SalaryStructureItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SalaryStructureItem::class, 'structure_id');
    }
}
