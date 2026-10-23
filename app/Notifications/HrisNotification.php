<?php

namespace App\Notifications;

use App\Models\SystemSetting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Satu bentuk notifikasi untuk semua modul: kategori, judul, isi singkat, dan tautan.
 * Dikirim sinkron ke database (in-app); email dikirim terpisah lewat queue oleh Notifier
 * agar status kirimnya tercatat apa adanya.
 *
 * Isi notifikasi tidak boleh memuat data sensitif (nominal gaji, rekening, nilai kinerja).
 */
class HrisNotification extends Notification
{
    public const CATEGORIES = [
        'leave' => 'Cuti/Izin',
        'overtime' => 'Lembur',
        'recruitment' => 'Rekrutmen',
        'payroll' => 'Gaji',
        'performance' => 'Penilaian Kinerja',
        'account' => 'Data Penting',
    ];

    /** Diisi job pengirim: nama penerima untuk sapaan, dan alamat asli bila email dialihkan dari alamat dummy. */
    public ?string $recipientName = null;

    public ?string $originalRecipient = null;

    /**
     * @param  string  $body  boleh beberapa paragraf, dipisah baris kosong
     * @param  array<string, string>  $details  rincian (label => isi) yang ditampilkan di email, mis. jadwal wawancara
     */
    public function __construct(
        public readonly string $category,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
        public readonly string $actionLabel = 'Lihat detail',
        public readonly array $details = [],
        public readonly ?string $closing = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string|null> */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = SystemSetting::get('company_name', config('app.name'));
        $mail = (new MailMessage)
            ->subject(($this->originalRecipient ? "[Untuk: {$this->originalRecipient}] " : '').$this->title)
            ->greeting($this->recipientName ? "Halo {$this->recipientName}," : 'Halo,');

        foreach (preg_split('/\R{2,}/', trim($this->body)) as $paragraph) {
            $mail->line($paragraph);
        }

        foreach ($this->details as $label => $value) {
            $mail->line("**{$label}:** {$value}");
        }

        if ($this->url !== null) {
            $mail->action($this->actionLabel, url($this->url));
        }

        if ($this->closing !== null) {
            $mail->line($this->closing);
        }

        return $mail->salutation("Salam,\n{$company}");
    }
}
