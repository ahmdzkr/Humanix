<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\NotificationDelivery;
use App\Models\Position;
use App\Notifications\HrisNotification;
use App\Services\OnboardingService;
use App\Support\MailRedirect;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
| Email otomatis ke pelamar di setiap tahap rekrutmen, info akun saat onboarding,
| dan pengalihan email dummy ke kotak masuk asli (MAIL_REDIRECT_TO / MAIL_USERNAME).
*/

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00', 'Asia/Jakarta'));
    $this->seed(SystemSettingsSeeder::class);

    $this->hrd = Employee::factory()->withRole(Role::Hrd)->create()->user;
    $this->manager = Employee::factory()->withRole(Role::Manager)->create()->user;
    $this->department = Department::factory()->create(['name' => 'Teknologi Informasi']);
    $this->position = Position::factory()->create(['department_id' => $this->department->id, 'name' => 'Programmer']);
});

/** Email (HrisNotification) terakhir yang dikirim ke alamat tertentu, dirender seperti email aslinya. */
function sentMailTo(string $address): array
{
    $found = null;

    Notification::assertSentOnDemand(HrisNotification::class, function (HrisNotification $n, array $channels, AnonymousNotifiable $notifiable) use ($address, &$found) {
        if (array_key_exists($address, (array) $notifiable->routes['mail'])) {
            $found = $n;
        }

        return $found !== null;
    });

    $mail = $found->toMail(new AnonymousNotifiable);

    return ['subject' => $mail->subject, 'text' => implode("\n", [...$mail->introLines, ...$mail->outroLines]), 'notification' => $found];
}

test('lolos seleksi berkas: pelamar menerima email dan HRD melihat tujuan email di pesan sukses', function () {
    [$application] = interviewedApplication(scored: false);

    $delivery = NotificationDelivery::query()->where('subject', 'Lolos seleksi berkas: Programmer Laravel')->sole();
    expect($delivery->email)->toBe('rizky@contoh.com')->and($delivery->status)->toBe('sent');
});

test('seleksi berkas ditolak: email "tidak memenuhi syarat" berisi catatan HRD', function () {
    $vacancy = createVacancy();
    $this->actingAs($this->hrd)->post("/rekruitmen/lowongan/{$vacancy->id}/status", ['status' => 'published']);
    applyTo($vacancy);
    $application = JobApplication::query()->sole();

    $this->actingAs($this->hrd)->post("/rekruitmen/lamaran/{$application->id}/seleksi", ['decision' => 'reject', 'note' => 'Pengalaman Laravel kurang dari 1 tahun.'])
        ->assertSessionHas('success', 'Pelamar ditolak. Email dikirim ke rizky@contoh.com.');

    $mail = sentMailTo('rizky@contoh.com');
    expect($mail['subject'])->toBe('Hasil seleksi berkas: Programmer Laravel')
        ->and($mail['text'])->toContain('belum memenuhi persyaratan')
        ->and($mail['text'])->toContain('Pengalaman Laravel kurang dari 1 tahun.');
});

test('"Tetapkan Jadwal" mengirim undangan wawancara berisi jadwal WIB, lokasi, pewawancara, dan catatan', function () {
    $vacancy = createVacancy();
    $this->actingAs($this->hrd)->post("/rekruitmen/lowongan/{$vacancy->id}/status", ['status' => 'published']);
    applyTo($vacancy);
    $application = JobApplication::query()->sole();
    $this->actingAs($this->hrd)->post("/rekruitmen/lamaran/{$application->id}/seleksi", ['decision' => 'pass']);

    $this->post("/rekruitmen/lamaran/{$application->id}/wawancara", [
        'scheduled_at' => '2026-10-14T10:00', 'duration_minutes' => 45, 'mode' => 'offline', 'location' => 'Ruang Meeting Lt. 2',
        'interviewer_id' => $this->manager->id, 'notes' => 'Bawa KTP dan portofolio.',
    ])->assertSessionHas('success', 'Jadwal wawancara ditetapkan. Email dikirim ke rizky@contoh.com.');

    $mail = sentMailTo('rizky@contoh.com');
    expect($mail['subject'])->toBe('Undangan wawancara: Programmer Laravel')
        ->and($mail['text'])->toContain('Rabu, 14 Oktober 2026 pukul 10:00 WIB')
        ->toContain('Ruang Meeting Lt. 2')
        ->toContain('45 menit')
        ->toContain($this->manager->name)
        ->toContain('Bawa KTP dan portofolio.');
});

test('"Tetapkan Jadwal": undangan ke email pelamar (dialihkan di lingkungan uji), bukan ke manager/interviewer', function () {
    // Mode lokal: semua email dialihkan, termasuk alamat @gmail.com pelamar uji yang tampak asli.
    config(['mail.redirect.to' => 'hrd.asli@gmail.com', 'mail.redirect.all' => true]);
    $vacancy = createVacancy();
    $this->actingAs($this->hrd)->post("/rekruitmen/lowongan/{$vacancy->id}/status", ['status' => 'published']);
    applyTo($vacancy, ['email' => 'sunardi@gmail.com', 'full_name' => 'Sunardi']);
    $application = JobApplication::query()->sole();
    $this->actingAs($this->hrd)->post("/rekruitmen/lamaran/{$application->id}/seleksi", ['decision' => 'pass']);

    $this->post("/rekruitmen/lamaran/{$application->id}/wawancara", [
        'scheduled_at' => '2026-10-14T13:30', 'duration_minutes' => 60, 'mode' => 'offline', 'location' => 'Kantor Bandung, Ruang 3',
        'interviewer_id' => $this->manager->id, 'notes' => 'Datang 15 menit lebih awal.',
    ])->assertSessionHas('success', 'Jadwal wawancara ditetapkan. Email dikirim ke sunardi@gmail.com (dialihkan ke hrd.asli@gmail.com).');

    $invitation = NotificationDelivery::query()->where('subject', 'Undangan wawancara: Programmer Laravel')->sole();
    expect($invitation->email)->toBe('sunardi@gmail.com')
        ->and($invitation->sent_to)->toBe('hrd.asli@gmail.com')
        ->and($invitation->status)->toBe('sent')
        // Interviewer hanya mendapat notifikasi in-app, tidak ada email untuknya.
        ->and(NotificationDelivery::query()->where('email', $this->manager->email)->exists())->toBeFalse();

    $mail = sentMailTo('hrd.asli@gmail.com');
    expect($mail['subject'])->toBe('[Untuk: sunardi@gmail.com] Undangan wawancara: Programmer Laravel')
        ->and($mail['notification']->toMail(new AnonymousNotifiable)->greeting)->toBe('Halo Sunardi,')
        ->and($mail['text'])->toContain('pukul 13:30 WIB')->toContain('Kantor Bandung, Ruang 3')->toContain('Datang 15 menit lebih awal.');
    Notification::assertSentTo($this->manager, HrisNotification::class, fn (HrisNotification $n, array $channels) => $channels === ['database'] && $n->title === 'Jadwal wawancara baru');

    // Alamat yang dikecualikan tetap dikirim langsung.
    config(['mail.redirect.except' => ['sunardi@gmail.com']]);
    expect(MailRedirect::resolve('sunardi@gmail.com'))->toBe('sunardi@gmail.com')
        ->and(MailRedirect::resolve('lain@gmail.com'))->toBe('hrd.asli@gmail.com');
});

test('keputusan akhir diterima/ditolak: email berisi hasil wawancara dan catatan HRD', function () {
    [$accepted] = interviewedApplication();
    $this->actingAs($this->hrd)->post("/rekruitmen/lamaran/{$accepted->id}/keputusan", ['decision' => 'accept', 'note' => 'Mulai 2 November.'])
        ->assertSessionHas('success', 'Pelamar diterima. Lanjutkan ke onboarding. Email dikirim ke rizky@contoh.com. Kuota lowongan sudah terpenuhi, lowongan ditutup otomatis.');

    $mail = sentMailTo('rizky@contoh.com');
    expect($mail['subject'])->toBe('Selamat, Anda diterima: Programmer Laravel')
        ->and($mail['text'])->toContain('DITERIMA')->toContain('Direkomendasikan')->toContain('4,0 dari 5')->toContain('Mulai 2 November.');

    Notification::fake();
    $this->actingAs($this->hrd)->post('/rekruitmen/lowongan', [
        'title' => 'Analis Data', 'department_id' => $this->department->id, 'position_id' => $this->position->id,
        'employment_status' => 'contract', 'location' => 'Bandung', 'description' => 'x', 'requirements' => 'x', 'quota' => 1, 'deadline' => '2026-10-31',
    ]);
    $vacancy = JobVacancy::query()->latest('id')->first();
    $this->post("/rekruitmen/lowongan/{$vacancy->id}/status", ['status' => 'published']);
    applyTo($vacancy, ['email' => 'nadia@contoh.com', 'full_name' => 'Nadia Putri']);
    $rejected = JobApplication::query()->latest('id')->first();
    $this->actingAs($this->hrd)->post("/rekruitmen/lamaran/{$rejected->id}/seleksi", ['decision' => 'pass']);
    $this->post("/rekruitmen/lamaran/{$rejected->id}/wawancara", [
        'scheduled_at' => '2026-10-15T09:00', 'duration_minutes' => 60, 'mode' => 'online', 'location' => 'https://meet.example.com/x', 'interviewer_id' => $this->manager->id,
    ]);
    scoreInterview(Interview::query()->latest('id')->first(), $this->manager);
    $this->actingAs($this->hrd)->post("/rekruitmen/lamaran/{$rejected->id}/keputusan", ['decision' => 'reject', 'note' => 'Kuota sudah terisi kandidat lain.'])
        ->assertSessionHas('success');

    $mail = sentMailTo('nadia@contoh.com');
    expect($mail['subject'])->toBe('Hasil akhir lamaran: Analis Data')
        ->and($mail['text'])->toContain('belum dapat kami terima')->toContain('Nilai rata-rata')->toContain('Kuota sudah terisi kandidat lain.');
});

test('email ke alamat dummy dialihkan ke kotak masuk asli dengan penanda penerima aslinya', function () {
    config(['mail.redirect.to' => 'hrd.asli@gmail.com']);

    expect(MailRedirect::resolve('rizky@contoh.com'))->toBe('hrd.asli@gmail.com')
        ->and(MailRedirect::resolve('hrd@humanix.test'))->toBe('hrd.asli@gmail.com')
        ->and(MailRedirect::resolve('budi@sub.example.com'))->toBe('budi@sub.example.com') // bukan domain terdaftar; DNS tidak dicek di tes
        ->and(MailRedirect::resolve('kandidat@gmail.com'))->toBe('kandidat@gmail.com')
        ->and(MailRedirect::resolve('hrd.asli@gmail.com'))->toBe('hrd.asli@gmail.com');

    [$application] = interviewedApplication(scored: false);

    $delivery = NotificationDelivery::query()->where('subject', 'Undangan wawancara: Programmer Laravel')->sole();
    expect($delivery->email)->toBe('rizky@contoh.com')
        ->and($delivery->sent_to)->toBe('hrd.asli@gmail.com')
        ->and($delivery->status)->toBe('sent');

    $mail = sentMailTo('hrd.asli@gmail.com');
    expect($mail['subject'])->toBe('[Untuk: rizky@contoh.com] Undangan wawancara: Programmer Laravel')
        // Tanpa kalimat keterangan pengalihan di isi email; penerima asli hanya tampak di subjek.
        ->and($mail['text'])->not->toContain('seharusnya dikirim')->not->toContain('dialihkan');

    // Pesan sukses menyebut alamat asli dan tujuan pengalihannya.
    $this->actingAs($this->hrd)->post("/rekruitmen/wawancara/{$application->interviews()->first()->id}/batal")
        ->assertSessionHas('success', 'Wawancara dibatalkan. Email dikirim ke rizky@contoh.com (dialihkan ke hrd.asli@gmail.com).');
});

test('tanpa alamat pengalihan (mis. production tanpa MAIL_REDIRECT_TO) email dikirim ke alamat aslinya', function () {
    config(['mail.redirect.to' => null]);

    expect(MailRedirect::resolve('rizky@contoh.com'))->toBe('rizky@contoh.com');

    interviewedApplication(scored: false);
    expect(NotificationDelivery::query()->whereNotNull('sent_to')->exists())->toBeFalse();
});

test('domain tanpa server email dianggap dummy bila pengecekan MX aktif', function () {
    config(['mail.redirect.to' => 'hrd.asli@gmail.com', 'mail.redirect.check_mx' => true]);
    Cache::put('mail-mx:tidak-ada-domainnya.co.id', false, 60);
    Cache::put('mail-mx:perusahaan-asli.co.id', true, 60);

    expect(MailRedirect::resolve('x@tidak-ada-domainnya.co.id'))->toBe('hrd.asli@gmail.com')
        ->and(MailRedirect::resolve('x@perusahaan-asli.co.id'))->toBe('x@perusahaan-asli.co.id');
});

test('"Jadikan Karyawan" membuat akun dengan kata sandi awal, mengirim info login, dan wajib ganti kata sandi', function () {
    $application = acceptedApplication();

    $this->actingAs($this->hrd)->get("/rekruitmen/lamaran/{$application->id}")
        ->assertInertia(fn ($page) => $page->where('defaultPassword', OnboardingService::DEFAULT_PASSWORD));

    $this->post("/rekruitmen/lamaran/{$application->id}/onboarding", onboardPayload())
        ->assertSessionHas('success', 'Rizky Pratama resmi menjadi karyawan dan akunnya sudah dibuat. Info login dikirim ke rizky@contoh.com.');

    $user = Employee::query()->where('nik', 'EMP-9001')->sole()->user;
    expect($user->email)->toBe('rizky@contoh.com')
        ->and($user->password)->not->toBe(OnboardingService::DEFAULT_PASSWORD) // disimpan ter-hash
        ->and(Hash::check(OnboardingService::DEFAULT_PASSWORD, $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue();

    $mail = sentMailTo('rizky@contoh.com');
    expect($mail['subject'])->toContain('Akun karyawan')
        ->and($mail['text'])->toContain('rizky@contoh.com')->toContain(OnboardingService::DEFAULT_PASSWORD)->toContain('EMP-9001')
        ->and($mail['notification']->url)->toBe('/login');

    // Login pertama: semua halaman diarahkan ke halaman ganti kata sandi.
    auth()->logout();
    $this->post('/login', ['email' => 'rizky@contoh.com', 'password' => OnboardingService::DEFAULT_PASSWORD]);
    $this->get('/me/dashboard')->assertRedirect('/settings/security');
    $this->get('/profil-saya')->assertRedirect('/settings/security');
    // Halaman keamanan meminta konfirmasi kata sandi (aturan 2FA bawaan); langkah itu tetap boleh dibuka.
    $this->get('/settings/security')->assertRedirect('/user/confirm-password');
    $this->get('/user/confirm-password')->assertOk();
    $this->post('/user/confirm-password', ['password' => OnboardingService::DEFAULT_PASSWORD])->assertRedirect('/settings/security');
    $this->get('/settings/security')->assertOk()->assertInertia(fn ($page) => $page->where('mustChangePassword', true));

    // Kata sandi awal tidak boleh dipakai lagi.
    $this->put('/settings/password', [
        'current_password' => OnboardingService::DEFAULT_PASSWORD, 'password' => OnboardingService::DEFAULT_PASSWORD, 'password_confirmation' => OnboardingService::DEFAULT_PASSWORD,
    ])->assertSessionHasErrors('password');

    $this->put('/settings/password', [
        'current_password' => OnboardingService::DEFAULT_PASSWORD, 'password' => 'SandiBaru#2026', 'password_confirmation' => 'SandiBaru#2026',
    ])->assertRedirect('/dashboard');

    expect($user->fresh()->must_change_password)->toBeFalse();
    $this->get('/profil-saya')->assertOk();
});

test('hanya HRD yang bisa memicu email rekrutmen; pihak lain ditolak tanpa email terkirim', function () {
    $vacancy = createVacancy();
    $this->actingAs($this->hrd)->post("/rekruitmen/lowongan/{$vacancy->id}/status", ['status' => 'published']);
    applyTo($vacancy);
    $application = JobApplication::query()->sole();
    $karyawan = Employee::factory()->withRole(Role::Karyawan)->create()->user;

    foreach ([$this->manager, $karyawan] as $user) {
        $response = $this->actingAs($user)->post("/rekruitmen/lamaran/{$application->id}/seleksi", ['decision' => 'reject', 'note' => 'x']);
        expect($response->status())->toBeIn([403, 404]);
    }

    expect(NotificationDelivery::query()->count())->toBe(0);
    Notification::assertNothingSentTo(new AnonymousNotifiable);
});

test('jadwal hanya bisa ditetapkan di masa depan dan catatan dibatasi 1000 karakter', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00', 'Asia/Jakarta'));
    $vacancy = createVacancy();
    $this->actingAs($this->hrd)->post("/rekruitmen/lowongan/{$vacancy->id}/status", ['status' => 'published']);
    applyTo($vacancy);
    $application = JobApplication::query()->sole();
    $this->actingAs($this->hrd)->post("/rekruitmen/lamaran/{$application->id}/seleksi", ['decision' => 'pass']);

    $this->post("/rekruitmen/lamaran/{$application->id}/wawancara", [
        'scheduled_at' => '2026-10-14T10:00', 'duration_minutes' => 60, 'mode' => 'offline', 'location' => 'Ruang 1',
        'interviewer_id' => $this->manager->id, 'notes' => str_repeat('a', 1001),
    ])->assertSessionHasErrors('notes');

    expect(NotificationDelivery::query()->where('subject', 'like', 'Undangan%')->exists())->toBeFalse();
});
