<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\ApprovalStep;
use App\Models\Employee;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\HrisNotification;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alur persetujuan bertingkat (default: Atasan → HRD, dari system_settings.approval_flow).
 * Dipakai cuti/izin dan lembur.
 */
class ApprovalService
{
    public function __construct(private readonly Notifier $notifier) {}

    /**
     * Buat langkah persetujuan untuk pengajuan baru. Mengembalikan true jika
     * tidak ada langkah yang perlu dijalankan (langsung disetujui).
     */
    /**
     * Pengajuan (cuti/izin & lembur) dari HRD diputuskan oleh Manager, bukan HRD (tidak ada yang menyetujui
     * pengajuannya sendiri): atasan langsung bila ia berrole Manager dan aktif, selain itu siapa pun yang
     * berrole Manager. Karyawan lain memakai alur dari pengaturan sistem (null).
     *
     * @return list<string>|null
     */
    public function flowFor(Employee $employee): ?array
    {
        $employee->loadMissing('user', 'manager.user');

        if (! $employee->user?->hasRole(Role::Hrd->value)) {
            return null;
        }

        $manager = $employee->manager;

        return $manager?->is_active && $manager->user?->hasRole(Role::Manager->value) ? ['manager'] : ['managers'];
    }

    /**
     * @param  list<string>|null  $flow  urutan approver_role; null = alur dari pengaturan sistem
     */
    public function createSteps(Model $approvable, Employee $employee, ?array $flow = null): bool
    {
        $flow ??= (array) SystemSetting::get('approval_flow', ['manager', 'hrd']);
        $managerUserId = $employee->manager?->is_active ? $employee->manager->user_id : null;
        $first = true;
        $step = 0;

        foreach ($flow as $role) {
            $step++;
            $skipped = $role === 'manager' && $managerUserId === null;

            ApprovalStep::create([
                'approvable_type' => $approvable->getMorphClass(),
                'approvable_id' => $approvable->getKey(),
                'step' => $step,
                'approver_role' => $role,
                'assigned_user_id' => $role === 'manager' ? $managerUserId : null,
                'status' => $skipped ? 'skipped' : ($first ? 'pending' : 'waiting'),
                'note' => $skipped ? 'Tidak ada atasan langsung yang aktif.' : null,
            ]);

            if (! $skipped) {
                $first = false;
            }
        }

        return $first;
    }

    /**
     * Apakah pengguna termasuk pemberi persetujuan pengajuan ini (langkah mana pun, juga yang sudah lewat).
     * Dipakai untuk menolak pihak luar dengan 403 sebelum validasi; pemberi persetujuan yang sah tetap
     * mendapat pesan "sudah diproses" bila pengajuan berubah.
     */
    public function isApproverOf(User $user, Model $approvable, string $permission): bool
    {
        if ($user->employee?->id === $approvable->employee_id) {
            return false;
        }

        $isHrd = $user->hasRole(Role::Hrd->value) && $user->can($permission);
        $isManager = $this->isManagerApprover($user, $permission);

        return $approvable->approvalSteps()
            ->where(fn ($q) => $q->where(fn ($m) => $m->where('approver_role', 'manager')->where('assigned_user_id', $user->id))
                ->when($isHrd, fn ($w) => $w->orWhere('approver_role', 'hrd'))
                ->when($isManager, fn ($w) => $w->orWhere('approver_role', 'managers')))
            ->exists();
    }

    /**
     * Apakah pengguna boleh memutuskan langkah yang sedang berjalan.
     */
    public function canDecide(User $user, Model $approvable, string $permission): bool
    {
        if (! $approvable->isPending()) {
            return false;
        }

        // Tidak ada yang boleh menyetujui pengajuannya sendiri.
        if ($user->employee?->id === $approvable->employee_id) {
            return false;
        }

        $step = $approvable->currentStep();

        // Langkah atasan diotorisasi oleh struktur (atasan langsung yang ditugaskan),
        // bukan oleh role: supervisor berrole Karyawan tetap bisa menyetujui bawahannya.
        return match ($step?->approver_role) {
            'manager' => $step->assigned_user_id === $user->id,
            'hrd' => $user->hasRole(Role::Hrd->value) && $user->can($permission),
            'managers' => $this->isManagerApprover($user, $permission),
            default => false,
        };
    }

    private function isManagerApprover(User $user, string $permission): bool
    {
        return $user->hasRole(Role::Manager->value) && $user->can($permission)
            && ($user->employee === null || $user->employee->is_active);
    }

    /**
     * Setujui atau tolak langkah saat ini.
     *
     * @param  Closure(Model): void  $onApproved  dipanggil di dalam transaksi saat semua langkah disetujui
     *
     * @throws ValidationException
     */
    public function decide(User $user, Model $approvable, string $permission, bool $approve, ?string $note, int $expectedVersion, Closure $onApproved): Model
    {
        $result = DB::transaction(function () use ($user, $approvable, $permission, $approve, $note, $expectedVersion, $onApproved) {
            /** @var Model $locked */
            $locked = $approvable->newQuery()->whereKey($approvable->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->version !== $expectedVersion || ! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'version' => 'Pengajuan ini sudah diproses atau berubah. Muat ulang halaman untuk melihat status terbaru.',
                ]);
            }

            if (! $this->canDecide($user, $locked, $permission)) {
                abort(403, 'Anda tidak berwenang memproses langkah persetujuan ini.');
            }

            $step = $locked->currentStep();
            $step->update([
                'status' => $approve ? 'approved' : 'rejected',
                'decided_by' => $user->id,
                'decided_at' => now(),
                'note' => $note,
            ]);

            if (! $approve) {
                $locked->approvalSteps()->where('status', 'waiting')->update(['status' => 'cancelled']);
                $locked->forceFill(['status' => RequestStatus::Rejected, 'decided_at' => now()]);
            } else {
                $next = $locked->approvalSteps()->where('status', 'waiting')->orderBy('step')->first();

                if ($next) {
                    $next->update(['status' => 'pending']);
                } else {
                    $locked->forceFill(['status' => RequestStatus::Approved, 'decided_at' => now()]);
                    $onApproved($locked);
                }
            }

            $locked->forceFill(['version' => $locked->version + 1])->save();

            return $locked;
        });

        $this->notifyProgress($result, $permission, $user, $note);

        return $result;
    }

    /**
     * Notifikasi setelah pengajuan dibuat atau diputuskan:
     * masih menunggu → pemberi persetujuan langkah berjalan; selesai → pemilik pengajuan.
     */
    public function notifyProgress(Model $approvable, string $permission, ?User $actor = null, ?string $note = null): void
    {
        $approvable->loadMissing('employee.user');
        $category = $approvable->approvalCategory();
        $label = HrisNotification::CATEGORIES[$category];
        $summary = $approvable->approvalSummary();
        $url = $approvable->approvalUrl();
        $owner = $approvable->employee;

        if ($approvable->isPending()) {
            $step = $approvable->currentStep();
            $approvers = match ($step?->approver_role) {
                'manager' => User::query()->whereKey($step->assigned_user_id)->get(),
                'hrd' => $this->notifier->hrdUsers($permission),
                'managers' => User::role(Role::Manager->value)->get()->filter(fn (User $u) => $this->isManagerApprover($u, $permission))->values(),
                default => collect(),
            };

            $this->notifier->send(
                $approvers,
                new HrisNotification($category, "Pengajuan {$label} menunggu persetujuan", "{$owner->full_name} mengajukan {$summary}.", $url, 'Tinjau pengajuan'),
                except: $owner->user,
            );

            return;
        }

        if (! in_array($approvable->status, [RequestStatus::Approved, RequestStatus::Rejected], true)) {
            return;
        }

        $approved = $approvable->status === RequestStatus::Approved;
        $body = sprintf('Pengajuan %s Anda %s%s.', $summary, $approved ? 'disetujui' : 'ditolak', $actor ? " oleh {$actor->name}" : '');

        if (! $approved && filled($note)) {
            $body .= " Alasan: {$note}";
        }

        $this->notifier->send(
            $owner,
            new HrisNotification($category, "Pengajuan {$label} ".($approved ? 'disetujui' : 'ditolak'), $body, $url),
            except: $actor,
        );
    }

    /**
     * Pembatalan oleh pemilik pengajuan, hanya saat masih Menunggu.
     *
     * @throws ValidationException
     */
    public function cancel(Model $approvable): Model
    {
        return DB::transaction(function () use ($approvable) {
            $locked = $approvable->newQuery()->whereKey($approvable->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya pengajuan berstatus Menunggu yang bisa dibatalkan.',
                ]);
            }

            $locked->approvalSteps()->whereIn('status', ['pending', 'waiting'])->update(['status' => 'cancelled']);
            $locked->forceFill([
                'status' => RequestStatus::Cancelled,
                'decided_at' => now(),
                'version' => $locked->version + 1,
            ])->save();

            return $locked;
        });
    }

    /**
     * Pengajuan yang menunggu keputusan pengguna ini (untuk tab "Perlu Persetujuan").
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $class
     * @return Builder<TModel>
     */
    public function awaiting(User $user, string $class, string $permission)
    {
        $query = $class::query()->where('status', RequestStatus::Pending->value);
        $isHrd = $user->hasRole(Role::Hrd->value) && $user->can($permission);
        $isManager = $this->isManagerApprover($user, $permission);
        $ownEmployeeId = $user->employee?->id;

        return $query
            ->when($ownEmployeeId, fn ($q) => $q->where('employee_id', '!=', $ownEmployeeId))
            ->whereHas('approvalSteps', fn ($q) => $q->where('status', 'pending')->where(function ($w) use ($user, $isHrd, $isManager) {
                $w->where(fn ($m) => $m->where('approver_role', 'manager')->where('assigned_user_id', $user->id));

                if ($isHrd) {
                    $w->orWhere('approver_role', 'hrd');
                }

                if ($isManager) {
                    $w->orWhere('approver_role', 'managers');
                }
            }));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stepsForDisplay(Model $approvable): array
    {
        return $approvable->approvalSteps()->with(['decider:id,name', 'assignedUser:id,name'])->get()
            ->map(fn (ApprovalStep $s) => [
                'step' => $s->step,
                'role' => ApprovalStep::ROLE_LABELS[$s->approver_role] ?? $s->approver_role,
                'assignee' => $s->assignedUser?->name,
                'status' => $s->status,
                'status_label' => ApprovalStep::STATUS_LABELS[$s->status] ?? $s->status,
                'decided_by' => $s->decider?->name,
                'decided_at' => $s->decided_at?->toIso8601String(),
                'note' => $s->note,
            ])->all();
    }
}
