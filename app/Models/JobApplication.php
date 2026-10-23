<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JobApplication extends Model
{
    use Auditable;

    protected $fillable = [
        'job_vacancy_id', 'applicant_id', 'cv_path', 'cover_letter', 'consent_at', 'consent_ip',
        'status', 'decision_note', 'decided_by', 'decided_at', 'employee_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'consent_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<JobVacancy, $this> */
    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(JobVacancy::class, 'job_vacancy_id');
    }

    /** @return BelongsTo<Applicant, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return HasMany<Interview, $this> */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class)->orderBy('scheduled_at');
    }

    /** @return HasMany<ApplicationStatusHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class)->latest('id');
    }

    /** @return HasOne<OnboardingRecord, $this> */
    public function onboarding(): HasOne
    {
        return $this->hasOne(OnboardingRecord::class);
    }
}
