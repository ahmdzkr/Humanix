<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevelopmentPlan extends Model
{
    use Auditable;

    public const TYPES = ['training' => 'Pelatihan', 'improvement' => 'Rencana perbaikan', 'promotion' => 'Promosi', 'other' => 'Lainnya'];

    public const STATUSES = ['planned' => 'Direncanakan', 'in_progress' => 'Berjalan', 'done' => 'Selesai'];

    protected $fillable = ['performance_review_id', 'type', 'description', 'target_date', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['target_date' => 'date'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
