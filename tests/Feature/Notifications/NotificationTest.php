<?php

use App\Enums\Role;
use App\Jobs\SendNotificationMail;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Notifications\HrisNotification;
use App\Services\EmployeeService;
use App\Services\Notifier;
use Carbon\CarbonImmutable;
use Database\Seeders\LeaveTypesSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Struktur: manager → staff; HRD terpisah; "hari ini" Rabu 7 Okt 2026.
| Di lingkungan tes MAIL_MAILER=array (SMTP dianggap terkonfigurasi) dan queue sync,
| sehingga email langsung diproses dan status kirimnya bisa diperiksa.
*/
beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-10-07 09:00', 'Asia/Jakarta'));
    $this->seed(LeaveTypesSeeder::class);

    $schedule = WorkSchedule::create(['name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'work_days' => [1, 2, 3, 4, 5]]);
    $this->manager = Employee::factory()->withRole(Role::Manager)->create();
    $this->staff = Employee::factory()->withRole(Role::Karyawan)->reportsTo($this->manager)->create();
    $this->other = Employee::factory()->withRole(Role::Karyawan)->create();
    $this->hrd = Employee::factory()->withRole(Role::Hrd)->create()->user;

    foreach ([$this->manager, $this->staff, $this->other] as $e) {
        EmployeeSchedule::create(['employee_id' => $e->id, 'work_schedule_id' => $schedule->id, 'effective_from' => '2026-01-01']);
    }
});

function notificationTitles(User $user): array
{
    return $user->notifications()->get()->pluck('data.title')->all();
}

function staffSubmitsLeave(): LeaveRequest
{
    test()->actingAs(test()->staff->user)->post('/cuti-izin', [
        'leave_type_id' => LeaveType::query()->where('code', 'CT')->sole()->id,
        'start_date' => '2026-10-12',
        'end_date' => '2026-10-13',
        'reason' => 'Acara keluarga',
    ])->assertSessionHasNoErrors();

    return LeaveRequest::query()->sole();
}

function decideLeaveAs(User $user, LeaveRequest $leave, string $decision, ?string $note = null): void
{
    test()->actingAs($user)->post("/cuti-izin/{$leave->id}/keputusan", [
        'decision' => $decision, 'note' => $note, 'version' => $leave->fresh()->version,
    ])->assertSessionHasNoErrors();
}

test('alur cuti: atasan → HRD → karyawan menerima notifikasi pada gilirannya, orang lain tidak', function () {
    $leave = staffSubmitsLeave();

    expect(notificationTitles($this->manager->user))->toBe(['Pengajuan Cuti/Izin menunggu persetujuan'])
        ->and($this->hrd->notifications()->count())->toBe(0)
        ->and($this->staff->user->notifications()->count())->toBe(0);

    decideLeaveAs($this->manager->user, $leave, 'approve');
    expect(notificationTitles($this->hrd))->toBe(['Pengajuan Cuti/Izin menunggu persetujuan']);

    decideLeaveAs($this->hrd, $leave, 'approve');
    $staffNotif = $this->staff->user->notifications()->sole();

    expect($staffNotif->data['title'])->toBe('Pengajuan Cuti/Izin disetujui')
        ->and($staffNotif->data['url'])->toBe("/cuti-izin/{$leave->id}")
        ->and($this->other->user->notifications()->count())->toBe(0);

    // Email ikut terkirim lewat queue dan statusnya tercatat "sent".
    expect(NotificationDelivery::query()->where('status', 'sent')->count())->toBe(3)
        ->and(NotificationDelivery::query()->where('status', '!=', 'sent')->count())->toBe(0);
});

test('penolakan memberi tahu karyawan beserta alasannya', function () {
    $leave = staffSubmitsLeave();
    decideLeaveAs($this->manager->user, $leave, 'reject', 'Sedang ada audit tahunan');

    $data = $this->staff->user->notifications()->sole()->data;

    expect($data['title'])->toBe('Pengajuan Cuti/Izin ditolak')
        ->and($data['body'])->toContain('Alasan: Sedang ada audit tahunan')
        ->and($this->hrd->notifications()->count())->toBe(0);
});

test('lembur yang diajukan memberi tahu atasan', function () {
    $this->actingAs($this->staff->user)->post('/lembur', [
        'work_date' => '2026-10-07', 'start_time' => '17:30', 'end_time' => '19:30', 'reason' => 'Rilis aplikasi',
    ])->assertSessionHasNoErrors();

    expect(notificationTitles($this->manager->user))->toBe(['Pengajuan Lembur menunggu persetujuan']);
});

test('email yang gagal tetap tercatat gagal beserta galatnya, tidak pernah "terkirim"', function () {
    $delivery = NotificationDelivery::create([
        'user_id' => $this->staff->user_id, 'email' => $this->staff->user->email, 'category' => 'payroll',
        'subject' => 'Uji', 'status' => 'queued',
    ]);
    $job = new SendNotificationMail($delivery->id, new HrisNotification('payroll', 'Uji', 'Isi uji'));

    // SMTP tidak bisa dihubungi (port 1 ditolak).
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);

    try {
        $job->handle();
        $this->fail('Pengiriman seharusnya gagal.');
    } catch (Throwable $e) {
        $job->failed($e);
    }

    $delivery->refresh();
    expect($delivery->status)->toBe('failed')
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->error)->not->toBeEmpty()
        ->and($delivery->sent_at)->toBeNull()
        ->and($delivery->failed_at)->not->toBeNull();
});

test('tanpa SMTP (MAIL_MAILER=log): in-app tetap terkirim, email pengguna tidak diantrekan, email pelamar dicatat "tidak dikirim"', function () {
    config(['mail.default' => 'log']);
    Mail::fake();
    $notifier = app(Notifier::class);

    $notifier->send($this->staff, new HrisNotification('account', 'Uji', 'Isi'));
    $notifier->sendToEmail('pelamar@example.com', 'Pelamar', new HrisNotification('recruitment', 'Hasil lamaran', 'Isi'));

    expect($this->staff->user->notifications()->count())->toBe(1)
        ->and(NotificationDelivery::query()->where('user_id', $this->staff->user_id)->exists())->toBeFalse()
        ->and(NotificationDelivery::query()->where('email', 'pelamar@example.com')->value('status'))->toBe('skipped');
    Mail::assertNothingSent();
});

test('karyawan nonaktif tidak menerima notifikasi', function () {
    $this->other->update(['is_active' => false]);
    app(Notifier::class)->send($this->other, new HrisNotification('account', 'Uji', 'Isi'));

    expect($this->other->user->notifications()->count())->toBe(0);
});

test('perubahan rekening oleh HRD memberi tahu karyawan tanpa menyebut nomor rekening', function () {
    $this->actingAs($this->hrd);

    app(EmployeeService::class)->update($this->staff, [
        'bank_name' => 'BCA', 'account_number' => '1234567890', 'account_holder' => $this->staff->full_name,
    ]);

    $data = $this->staff->user->notifications()->sole()->data;
    expect($data['title'])->toBe('Data kepegawaian Anda diperbarui')
        ->and($data['body'])->toContain('rekening bank')
        ->and($data['body'])->not->toContain('1234567890');
});

test('lonceng: jumlah belum dibaca & 5 terbaru; buka notifikasi menandai dibaca dan menuju tautannya', function () {
    $notifier = app(Notifier::class);

    foreach (range(1, 6) as $i) {
        $notifier->send($this->staff, new HrisNotification('leave', "Notif {$i}", 'Isi', '/cuti-izin'));
    }

    $this->actingAs($this->staff->user)->get('/me/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('notificationCount', 6)
            ->has('latestNotifications', 5));

    $first = $this->staff->user->notifications()->latest()->first();
    $this->post("/notifikasi/{$first->id}/baca")->assertRedirect('/cuti-izin');
    expect($first->fresh()->read_at)->not->toBeNull();

    $this->get('/notifikasi?status=unread')->assertInertia(fn (Assert $page) => $page
        ->component('notifications/index')
        ->where('notifications.total', 5));

    $this->post('/notifikasi/baca-semua')->assertRedirect();
    expect($this->staff->user->unreadNotifications()->count())->toBe(0);
});

test('notifikasi milik orang lain tidak bisa dibuka (404)', function () {
    app(Notifier::class)->send($this->other, new HrisNotification('account', 'Rahasia', 'Isi'));
    $foreign = DatabaseNotification::query()->sole();

    $this->actingAs($this->staff->user)->post("/notifikasi/{$foreign->id}/baca")->assertNotFound();
    expect($foreign->fresh()->read_at)->toBeNull();
});

test('tautan notifikasi eksternal tidak diikuti (mencegah open redirect)', function () {
    app(Notifier::class)->send($this->staff, new HrisNotification('account', 'X', 'Isi', '//evil.example.com'));
    $n = $this->staff->user->notifications()->sole();

    $this->actingAs($this->staff->user)->from('/notifikasi')->post("/notifikasi/{$n->id}/baca")->assertRedirect('/notifikasi');
});
