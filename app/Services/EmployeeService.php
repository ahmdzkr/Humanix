<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\EmployeeSchedule;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Notifications\HrisNotification;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeService
{
    public function __construct(private readonly Notifier $notifier) {}

    /** Disk privat untuk foto karyawan (diakses lewat route berizin). */
    public const PHOTO_DISK = 'local';

    /** Field yang dicatat di riwayat beserta labelnya. */
    private const TRACKED = [
        'nik' => 'NIK',
        'full_name' => 'Nama lengkap',
        'email' => 'Email',
        'gender' => 'Jenis kelamin',
        'birth_place' => 'Tempat lahir',
        'birth_date' => 'Tanggal lahir',
        'phone' => 'Telepon',
        'address' => 'Alamat',
        'department' => 'Departemen',
        'position' => 'Jabatan',
        'manager' => 'Atasan',
        'employment_status' => 'Status kepegawaian',
        'join_date' => 'Tanggal masuk',
        'contract_end_date' => 'Akhir kontrak',
        'schedule' => 'Jadwal kerja',
        'photo' => 'Foto',
        'bank' => 'Rekening bank',
    ];

    /**
     * Buat karyawan sekaligus akun login (role Karyawan) dalam satu transaksi,
     * lalu kirim tautan untuk mengatur kata sandi.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $photo = null, bool $sendPasswordLink = true): Employee
    {
        $storedPhoto = $photo?->store('employee-photos', self::PHOTO_DISK);

        try {
            $employee = DB::transaction(function () use ($data, $storedPhoto) {
                $user = User::create([
                    'name' => $data['full_name'],
                    'email' => $data['email'],
                    // Kata sandi acak; karyawan mengatur sendiri lewat tautan email.
                    'password' => Str::password(32),
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $user->assignRole(Role::Karyawan->value);

                $employee = Employee::create([
                    ...Arr::only($data, $this->employeeColumns()),
                    'user_id' => $user->id,
                    'photo_path' => $storedPhoto,
                    'is_active' => true,
                ]);

                $this->syncBankAccount($employee, $data);
                $this->assignSchedule($employee, $data['work_schedule_id'] ?? null);
                $this->record($employee, 'created', null);

                return $employee;
            });
        } catch (\Throwable $e) {
            if ($storedPhoto) {
                Storage::disk(self::PHOTO_DISK)->delete($storedPhoto);
            }

            throw $e;
        }

        // Pemanggil yang membungkus dalam transaksi lebih luar (mis. onboarding) mengirim tautan sendiri setelah commit.
        if ($sendPasswordLink) {
            $this->sendPasswordSetupLink($employee);
        }

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data, ?UploadedFile $photo = null): Employee
    {
        $before = $this->snapshot($employee);
        $oldPhoto = $employee->photo_path;
        $storedPhoto = $photo?->store('employee-photos', self::PHOTO_DISK);

        DB::transaction(function () use ($employee, $data, $storedPhoto) {
            $employee->fill(Arr::only($data, $this->employeeColumns()));

            if ($storedPhoto) {
                $employee->photo_path = $storedPhoto;
            }

            $employee->save();

            $employee->user->update([
                'name' => $employee->full_name,
                'email' => $data['email'] ?? $employee->user->email,
            ]);

            $this->syncBankAccount($employee, $data);

            if (array_key_exists('work_schedule_id', $data)) {
                $this->assignSchedule($employee, $data['work_schedule_id']);
            }
        });

        if ($storedPhoto && $oldPhoto) {
            Storage::disk(self::PHOTO_DISK)->delete($oldPhoto);
        }

        $changes = $this->diff($before, $this->snapshot($employee->fresh()));

        if ($changes !== []) {
            $this->record($employee, 'updated', $changes);
            $this->notifyImportantChange($employee, array_keys($changes));
        }

        return $employee;
    }

    /**
     * Beri tahu karyawan bila data penting miliknya diubah orang lain. Hanya nama field yang disebut,
     * nilainya tidak (mis. nomor rekening tidak pernah masuk notifikasi/email).
     *
     * @param  list<string>  $fields  label field yang berubah
     */
    private function notifyImportantChange(Employee $employee, array $fields): void
    {
        $important = array_values(array_intersect($fields, [
            self::TRACKED['email'], self::TRACKED['department'], self::TRACKED['position'], self::TRACKED['manager'],
            self::TRACKED['employment_status'], self::TRACKED['contract_end_date'], self::TRACKED['schedule'], self::TRACKED['bank'],
        ]));

        if ($important === []) {
            return;
        }

        $this->notifier->send($employee, new HrisNotification('account', 'Data kepegawaian Anda diperbarui',
            'HRD memperbarui: '.mb_strtolower(implode(', ', $important)).'. Hubungi HRD bila ada yang tidak sesuai.',
            route('my-profile.show', absolute: false), 'Lihat profil'), except: Auth::user());
    }

    /**
     * Karyawan memperbarui profilnya sendiri: hanya telepon, alamat, dan foto.
     *
     * @param  array{phone?: string|null, address?: string|null}  $data
     */
    public function updateOwnProfile(Employee $employee, array $data, ?UploadedFile $photo = null): Employee
    {
        $before = $this->snapshot($employee);
        $oldPhoto = $employee->photo_path;

        $employee->fill(Arr::only($data, ['phone', 'address']));

        if ($photo) {
            $employee->photo_path = $photo->store('employee-photos', self::PHOTO_DISK);
        }

        $employee->save();

        if ($photo && $oldPhoto) {
            Storage::disk(self::PHOTO_DISK)->delete($oldPhoto);
        }

        $changes = $this->diff($before, $this->snapshot($employee->fresh()));

        if ($changes !== []) {
            $this->record($employee, 'profile_updated', $changes);
        }

        return $employee;
    }

    public function setActive(Employee $employee, bool $active, ?string $note = null): void
    {
        if ($employee->is_active === $active) {
            return;
        }

        DB::transaction(function () use ($employee, $active, $note) {
            $employee->update([
                'is_active' => $active,
                'deactivated_at' => $active ? null : now(),
            ]);

            $this->record($employee, $active ? 'activated' : 'deactivated', null, $note);
        });
    }

    public function rotateQrToken(Employee $employee): void
    {
        DB::transaction(function () use ($employee) {
            $employee->forceFill(['qr_token' => Employee::newQrToken()])->save();
            $this->record($employee, 'qr_rotated', null, 'ID Card lama tidak berlaku lagi.');
        });
    }

    public function changeRole(Employee $employee, Role $role): void
    {
        $user = $employee->user;
        $old = $user->primaryRole()?->value ?? '—';

        if ($old === $role->value) {
            return;
        }

        DB::transaction(function () use ($employee, $user, $role, $old) {
            $user->syncRoles([$role->value]);
            $this->record($employee, 'role_changed', ['Role' => [$old, $role->value]]);
            $this->notifier->send($user, new HrisNotification('account', 'Peran akun Anda diubah',
                "Peran akun Anda diubah dari {$old} menjadi {$role->value}. Menu yang tersedia menyesuaikan peran baru.",
                route('dashboard', absolute: false)), except: Auth::user());
        });
    }

    /**
     * Jadwal kerja berlaku mulai hari ini (WIB); jadwal sebelumnya ditutup kemarin.
     */
    public function assignSchedule(Employee $employee, int|string|null $workScheduleId): void
    {
        $today = now(config('app.display_timezone'))->toDateString();
        $current = $employee->schedules()
            ->whereDate('effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
            ->first();

        if ((int) $current?->work_schedule_id === (int) $workScheduleId) {
            return;
        }

        if ($current) {
            $current->effective_from->toDateString() === $today
                ? $current->delete()
                : $current->update(['effective_to' => now(config('app.display_timezone'))->subDay()->toDateString()]);
        }

        if ($workScheduleId) {
            EmployeeSchedule::create([
                'employee_id' => $employee->id,
                'work_schedule_id' => (int) $workScheduleId,
                'effective_from' => $today,
            ]);
        }
    }

    public function sendPasswordSetupLink(Employee $employee): string
    {
        return Password::broker()->sendResetLink(['email' => $employee->user->email]);
    }

    /** @return list<string> */
    private function employeeColumns(): array
    {
        return [
            'nik', 'full_name', 'gender', 'birth_place', 'birth_date', 'phone', 'address',
            'department_id', 'position_id', 'manager_id', 'employment_status',
            'join_date', 'contract_end_date',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncBankAccount(Employee $employee, array $data): void
    {
        $bankName = trim((string) ($data['bank_name'] ?? ''));
        $number = trim((string) ($data['account_number'] ?? ''));
        $holder = trim((string) ($data['account_holder'] ?? ''));

        if ($bankName === '' && $number === '' && $holder === '') {
            return;
        }

        $account = $employee->bankAccount()->firstOrNew();
        $account->bank_name = $bankName ?: $account->bank_name;
        $account->account_holder = $holder ?: $account->account_holder;

        // Nomor rekening kosong saat ubah = pertahankan yang lama.
        if ($number !== '') {
            $account->account_number = $number;
        }

        $account->save();
    }

    /**
     * Nilai yang mudah dibaca untuk dibandingkan di riwayat.
     *
     * @return array<string, string|null>
     */
    private function snapshot(Employee $employee): array
    {
        $employee->loadMissing(['department', 'position', 'manager', 'user', 'bankAccount']);
        $date = fn (?CarbonInterface $d) => $d?->format('Y-m-d');
        $status = $employee->employment_status;

        return [
            'nik' => $employee->nik,
            'full_name' => $employee->full_name,
            'email' => $employee->user?->email,
            'gender' => Employee::GENDERS[$employee->gender] ?? null,
            'birth_place' => $employee->birth_place,
            'birth_date' => $date($employee->birth_date),
            'phone' => $employee->phone,
            'address' => $employee->address,
            'department' => $employee->department?->name,
            'position' => $employee->position?->name,
            'manager' => $employee->manager?->full_name,
            'employment_status' => $status instanceof EmploymentStatus ? $status->label() : null,
            'join_date' => $date($employee->join_date),
            'contract_end_date' => $date($employee->contract_end_date),
            'schedule' => $employee->scheduleOn(now(config('app.display_timezone'))->toDateString())?->label(),
            // Foto & rekening hanya dicatat "berubah", nilainya tidak disimpan.
            'photo' => $employee->photo_path ? md5($employee->photo_path) : null,
            'bank' => $employee->bankAccount
                ? md5($employee->bankAccount->bank_name.$employee->bankAccount->account_number.$employee->bankAccount->account_holder)
                : null,
        ];
    }

    /**
     * @param  array<string, string|null>  $before
     * @param  array<string, string|null>  $after
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    private function diff(array $before, array $after): array
    {
        $changes = [];

        foreach (self::TRACKED as $key => $label) {
            if (($before[$key] ?? null) === ($after[$key] ?? null)) {
                continue;
            }

            $changes[$label] = in_array($key, ['photo', 'bank'], true)
                ? [null, 'diperbarui']
                : [$before[$key] ?? null, $after[$key] ?? null];
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>|null  $changes
     */
    private function record(Employee $employee, string $type, ?array $changes, ?string $note = null): void
    {
        EmployeeHistory::create([
            'employee_id' => $employee->id,
            'user_id' => Auth::id(),
            'type' => $type,
            'changes' => $changes,
            'note' => $note,
        ]);
    }

    /** Pilihan atasan: karyawan aktif, tanpa diri sendiri dan bawahannya (cegah siklus). */
    public function managerOptions(?Employee $except = null): array
    {
        $excluded = $except ? [$except->id, ...$except->subordinateIds()] : [];

        return Employee::query()
            ->where('is_active', true)
            ->whereNotIn('id', $excluded)
            ->with('position:id,name')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'nik', 'position_id'])
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'label' => "{$e->full_name} — {$e->position?->name}",
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    public function formOptions(?Employee $except = null): array
    {
        return [
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
            'positions' => Position::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'department_id']),
            'managers' => $this->managerOptions($except),
            'employmentStatuses' => EmploymentStatus::options(),
            'workSchedules' => WorkSchedule::query()->where('is_active', true)->orderBy('name')->get()
                ->map(fn (WorkSchedule $s) => ['id' => $s->id, 'label' => $s->label()]),
            'genders' => collect(Employee::GENDERS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ];
    }
}
