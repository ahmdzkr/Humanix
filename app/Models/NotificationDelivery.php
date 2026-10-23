<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catatan pengiriman email notifikasi (queued / sent / failed / skipped). */
class NotificationDelivery extends Model
{
    public const STATUS_LABELS = [
        'queued' => 'Dalam antrean',
        'sent' => 'Terkirim',
        'failed' => 'Gagal',
        'skipped' => 'Tidak dikirim',
    ];

    protected $fillable = [
        'user_id', 'email', 'sent_to', 'recipient_name', 'category', 'subject', 'status',
        'attempts', 'error', 'sent_at', 'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /** Ringkasan tujuan untuk pesan ke pengguna, mis. "a@contoh.com (dialihkan ke b@gmail.com)". */
    public function recipientLabel(): string
    {
        return $this->sent_to ? "{$this->email} (dialihkan ke {$this->sent_to})" : $this->email;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
