<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Interview extends Model
{
    use Auditable;

    public const RECOMMENDATIONS = ['hire' => 'Direkomendasikan', 'consider' => 'Dipertimbangkan', 'reject' => 'Tidak direkomendasikan'];

    protected $fillable = [
        'job_application_id', 'scheduled_at', 'duration_minutes', 'mode', 'location', 'notes', 'interviewer_id',
        'status', 'recommendation', 'summary', 'scored_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'scored_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    /** Disimpan UTC; input berzona WIB dikonversi. */
    protected function scheduledAt(): Attribute
    {
        return Attribute::make(set: fn ($value) => Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'));
    }

    /** @return BelongsTo<JobApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    /** @return BelongsTo<User, $this> */
    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    /** @return HasMany<InterviewScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(InterviewScore::class)->orderBy('id');
    }

    public function averageScore(): ?float
    {
        $avg = $this->scores->avg('score');

        return $avg === null ? null : round((float) $avg, 2);
    }
}
