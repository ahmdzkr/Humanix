<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingRecord extends Model
{
    use Auditable;

    /** Daftar periksa awal onboarding; HRD mencentang satu per satu. */
    public const DEFAULT_CHECKLIST = [
        'Kontrak kerja ditandatangani',
        'Dokumen identitas & NPWP diterima',
        'Data rekening bank diisi',
        'ID Card dicetak',
        'Perangkat & akses kerja disiapkan',
        'Orientasi hari pertama',
    ];

    protected $fillable = ['job_application_id', 'employee_id', 'start_date', 'checklist', 'created_by'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'checklist' => 'array',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
