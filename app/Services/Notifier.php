<?php

namespace App\Services;

use App\Enums\Role;
use App\Jobs\SendNotificationMail;
use App\Models\Employee;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\HrisNotification;
use App\Support\MailRedirect;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Titik tunggal pengiriman notifikasi.
 * - In-app (database) dikirim langsung; bila dipanggil di dalam transaksi, ikut di-rollback.
 * - Email dikirim lewat queue setelah transaksi commit, hanya bila SMTP dikonfigurasi
 *   (MAIL_MAILER bukan "log"); status kirim dicatat di notification_deliveries.
 */
class Notifier
{
    public function mailEnabled(): bool
    {
        return config('mail.default') !== 'log';
    }

    /**
     * @param  User|Employee|iterable<User|Employee|null>|null  $recipients
     */
    public function send(User|Employee|iterable|null $recipients, HrisNotification $notification, ?User $except = null, bool $mail = true): void
    {
        $users = $this->users($recipients)
            ->reject(fn (User $u) => $except !== null && $u->id === $except->id);

        foreach ($users as $user) {
            Notification::sendNow($user, $notification, ['database']);

            if ($mail && $this->mailEnabled() && filled($user->email)) {
                $this->queueMail($notification, $user->email, $user->name, $user->id);
            }
        }
    }

    /** Email ke penerima tanpa akun (mis. pelamar). Bila SMTP belum diatur, dicatat "Tidak dikirim". */
    public function sendToEmail(string $email, ?string $name, HrisNotification $notification): NotificationDelivery
    {
        if (! $this->mailEnabled()) {
            return NotificationDelivery::create([
                'email' => $email, 'recipient_name' => $name, 'category' => $notification->category,
                'subject' => $notification->title, 'status' => 'skipped',
                'error' => 'SMTP belum dikonfigurasi (MAIL_MAILER=log).',
            ]);
        }

        return $this->queueMail($notification, $email, $name, null);
    }

    /** Pengguna HRD aktif (penerima langkah persetujuan HRD dan pemberitahuan HR). */
    public function hrdUsers(string $permission): Collection
    {
        return User::role(Role::Hrd->value)->get()
            ->filter(fn (User $u) => $u->can($permission) && ($u->employee === null || $u->employee->is_active))
            ->values();
    }

    private function queueMail(HrisNotification $notification, string $email, ?string $name, ?int $userId): NotificationDelivery
    {
        $target = MailRedirect::resolve($email);

        $delivery = NotificationDelivery::create([
            'user_id' => $userId, 'email' => $email, 'sent_to' => $target !== $email ? $target : null, 'recipient_name' => $name,
            'category' => $notification->category, 'subject' => $notification->title, 'status' => 'queued',
        ]);

        SendNotificationMail::dispatch($delivery->id, $notification);

        return $delivery;
    }

    /**
     * Normalisasi penerima menjadi pengguna unik; karyawan nonaktif dilewati.
     *
     * @return Collection<int, User>
     */
    private function users(User|Employee|iterable|null $recipients): Collection
    {
        $items = $recipients instanceof User || $recipients instanceof Employee || $recipients === null
            ? [$recipients]
            : $recipients;

        return collect($items)
            ->map(function ($r) {
                if ($r instanceof Employee) {
                    return $r->is_active ? $r->user : null;
                }

                return $r;
            })
            ->filter(fn ($u) => $u instanceof User)
            ->unique('id')
            ->values();
    }
}
