<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use App\Notifications\HrisNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mengirim email satu notifikasi dan mencatat hasilnya di notification_deliveries.
 * Gagal tetap tercatat gagal (beserta pesan galatnya) — tidak pernah ditandai terkirim.
 */
class SendNotificationMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(
        public readonly int $deliveryId,
        public readonly HrisNotification $notification,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $delivery = NotificationDelivery::query()->find($this->deliveryId);

        if ($delivery === null || $delivery->status === 'sent') {
            return;
        }

        $delivery->increment('attempts');

        try {
            // Selalu ke alamat yang tercatat di log (alamat pengalihan bila aslinya dummy), bukan langsung ke email akun.
            $this->notification->recipientName = $delivery->recipient_name;
            $this->notification->originalRecipient = $delivery->sent_to ? $delivery->email : null;
            $notifiable = Notification::route('mail', [($delivery->sent_to ?? $delivery->email) => $delivery->recipient_name]);

            Notification::sendNow($notifiable, $this->notification, ['mail']);
        } catch (Throwable $e) {
            $delivery->update(['error' => Str::limit($e->getMessage(), 1000)]);

            throw $e;
        }

        $delivery->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
    }

    /** Dipanggil setelah semua percobaan habis. */
    public function failed(?Throwable $e): void
    {
        NotificationDelivery::query()->whereKey($this->deliveryId)->where('status', '!=', 'sent')->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error' => Str::limit($e?->getMessage() ?? 'Gagal tanpa pesan galat.', 1000),
        ]);
    }
}
