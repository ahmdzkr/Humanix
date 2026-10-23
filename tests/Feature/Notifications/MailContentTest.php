<?php

use App\Jobs\SendNotificationMail;
use App\Models\NotificationDelivery;
use App\Notifications\HrisNotification;
use App\Services\Notifier;
use Database\Seeders\SystemSettingsSeeder;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;

/*
| Isi email otomatis, dirender dan dikirim lewat transport "array" (end-to-end, tanpa fake):
| email yang dialihkan harus sama persis isinya dengan email aslinya — tanpa kalimat keterangan pengalihan.
| Penerima asli hanya tampak di awalan subjek "[Untuk: ...]" dan log pengiriman.
*/

beforeEach(function () {
    $this->seed(SystemSettingsSeeder::class);
    config(['mail.default' => 'array', 'mail.redirect.to' => 'kotak.uji@gmail.com', 'mail.redirect.all' => true]);
});

/** @return list<Email> */
function sentEmails(): array
{
    return app('mailer')->getSymfonyTransport()->messages()->map(fn (SentMessage $m) => $m->getOriginalMessage())->all();
}

dataset('jenis email', fn () => collect(HrisNotification::CATEGORIES)->keys()->mapWithKeys(fn (string $category) => [
    $category => [new HrisNotification(
        $category,
        "Judul {$category}",
        "Paragraf pertama.\n\nParagraf kedua.",
        url: '/notifikasi',
        details: ['Rincian' => 'Isi rincian', 'Catatan dari HRD' => 'Catatan uji'],
        closing: 'Kalimat penutup.',
    )],
])->all());

test('email yang dialihkan tidak memuat kalimat keterangan pengalihan', function (HrisNotification $notification) {
    $delivery = app(Notifier::class)->sendToEmail('pelamar@gmail.com', 'Pelamar Uji', $notification);

    expect($delivery->fresh()->status)->toBe('sent')->and($delivery->sent_to)->toBe('kotak.uji@gmail.com');

    [$email] = sentEmails();
    $body = $email->getHtmlBody().$email->getTextBody();

    expect($email->getTo()[0]->getAddress())->toBe('kotak.uji@gmail.com')
        ->and($email->getSubject())->toBe("[Untuk: pelamar@gmail.com] {$notification->title}")
        ->and($body)->toContain('Halo Pelamar Uji')->toContain('Paragraf kedua.')->toContain('Catatan uji')->toContain('Kalimat penutup.')
        ->and($body)->not->toContain('seharusnya dikirim')
        ->not->toContain('dialihkan')
        ->not->toContain('dummy')
        ->not->toContain('alamat contoh')
        ->not->toContain('pelamar@gmail.com');
})->with('jenis email');

test('tanpa pengalihan, isi email sama dan subjek tanpa awalan', function (HrisNotification $notification) {
    config(['mail.redirect.to' => null]);

    $delivery = NotificationDelivery::create([
        'email' => 'karyawan@gmail.com', 'recipient_name' => 'Karyawan', 'category' => $notification->category,
        'subject' => $notification->title, 'status' => 'queued',
    ]);
    (new SendNotificationMail($delivery->id, $notification))->handle();

    [$email] = sentEmails();
    expect($email->getTo()[0]->getAddress())->toBe('karyawan@gmail.com')
        ->and($email->getSubject())->toBe($notification->title)
        ->and($email->getHtmlBody())->not->toContain('dialihkan')->not->toContain('seharusnya dikirim');
})->with('jenis email');
