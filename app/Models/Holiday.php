<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use Auditable;

    protected $fillable = ['date', 'name'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public static function isHoliday(string $date): bool
    {
        return static::query()->whereDate('date', $date)->exists();
    }
}
