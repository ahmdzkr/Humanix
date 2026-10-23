<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\Concerns\Auditable;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use Auditable, HasFactory, SoftDeletes;

    public const GENDERS = ['L' => 'Laki-laki', 'P' => 'Perempuan'];

    protected $fillable = [
        'user_id',
        'nik',
        'full_name',
        'gender',
        'birth_place',
        'birth_date',
        'phone',
        'address',
        'department_id',
        'position_id',
        'manager_id',
        'employment_status',
        'join_date',
        'contract_end_date',
        'photo_path',
        'is_active',
        'deactivated_at',
    ];

    protected $hidden = ['qr_token'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'join_date' => 'date',
            'contract_end_date' => 'date',
            'deactivated_at' => 'datetime',
            'is_active' => 'boolean',
            'employment_status' => EmploymentStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Employee $employee) {
            $employee->qr_token ??= static::newQrToken();
        });
    }

    public static function newQrToken(): string
    {
        return Str::random(48);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /** @return BelongsTo<Employee, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /** @return HasMany<Employee, $this> */
    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    /** @return HasOne<BankAccount, $this> */
    public function bankAccount(): HasOne
    {
        return $this->hasOne(BankAccount::class);
    }

    /** @return HasMany<EmployeeSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class)->latest('effective_from');
    }

    /** @return HasMany<AttendanceRecord, $this> */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /** Jadwal kerja yang berlaku pada tanggal (Y-m-d) tertentu. */
    public function scheduleOn(string $date): ?WorkSchedule
    {
        return $this->schedules()
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->with('workSchedule')
            ->first()
            ?->workSchedule;
    }

    /** @return HasMany<SalaryStructure, $this> */
    public function salaryStructures(): HasMany
    {
        return $this->hasMany(SalaryStructure::class)->latest('effective_from');
    }

    /** Struktur gaji yang berlaku pada tanggal (Y-m-d) tertentu. */
    public function salaryStructureOn(string $date): ?SalaryStructure
    {
        return $this->salaryStructures()
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->with('items.component')
            ->first();
    }

    /** @return HasMany<EmployeeHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(EmployeeHistory::class)->latest('id');
    }

    /**
     * ID seluruh bawahan langsung dan tidak langsung.
     *
     * @return list<int>
     */
    public function subordinateIds(): array
    {
        $ids = [];
        $frontier = [$this->id];

        while ($frontier !== []) {
            $frontier = Employee::query()
                ->whereIn('manager_id', $frontier)
                ->whereNotIn('id', $ids)
                ->pluck('id')
                ->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    /**
     * Batasi daftar karyawan sesuai siapa yang melihat:
     * HRD/Administrator semua, Manager bawahannya (langsung & tidak langsung), lainnya hanya diri sendiri.
     *
     * @param  Builder<Employee>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('employees.manage') || $user->hasRole(Role::Administrator->value)) {
            return;
        }

        $self = $user->employee;

        if ($self === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($user->hasRole(Role::Manager->value)) {
            $query->whereIn('id', $self->subordinateIds());

            return;
        }

        $query->whereKey($self->id);
    }
}
