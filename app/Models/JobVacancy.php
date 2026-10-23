<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobVacancy extends Model
{
    use Auditable;

    public const STATUS_LABELS = ['draft' => 'Draft', 'published' => 'Dipublikasikan', 'closed' => 'Ditutup'];

    protected $fillable = [
        'slug', 'title', 'department_id', 'position_id', 'employment_status', 'location',
        'description', 'requirements', 'quota', 'deadline', 'status', 'published_at', 'closed_at', 'closed_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'quota' => 'integer',
            'employment_status' => EmploymentStatus::class,
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /** @return HasMany<JobApplication, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * Lowongan yang tampil di halaman karier: dipublikasikan dan tenggat belum lewat (WIB).
     *
     * @param  Builder<JobVacancy>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', 'published')
            ->whereDate('deadline', '>=', now(config('app.display_timezone'))->toDateString());
    }

    public function isOpen(): bool
    {
        return $this->status === 'published'
            && $this->deadline->toDateString() >= now(config('app.display_timezone'))->toDateString();
    }
}
