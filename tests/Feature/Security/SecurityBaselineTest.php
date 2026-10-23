<?php

use App\Enums\Role;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\EmployeeService;
use App\Services\LeaveService;
use App\Services\PayrollService;
use App\Services\RecruitmentService;
use Database\Seeders\LeaveTypesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('login dibatasi 5 percobaan per menit per email+IP', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => $user->email, 'password' => 'salah'])->assertSessionHasErrors('email');
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'salah'])->assertTooManyRequests();
    $this->assertGuest();
});

test('respons web membawa header keamanan dasar', function () {
    $this->get('/login')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('Strict-Transport-Security'); // hanya lewat HTTPS
});

test('cookie sesi httpOnly dan SameSite lax; cookie aman dikendalikan SESSION_SECURE_COOKIE', function () {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax')
        ->and(file_get_contents(base_path('config/session.php')))->toContain("env('SESSION_SECURE_COOKIE')");
});

test('nomor rekening lengkap tidak pernah dikirim ke browser (HRD, atasan, maupun pemilik)', function () {
    $manager = Employee::factory()->withRole(Role::Manager)->create();
    $staff = Employee::factory()->withRole(Role::Karyawan)->reportsTo($manager)->create();
    $staff->bankAccount()->create(['bank_name' => 'BCA', 'account_number' => '1234567890', 'account_holder' => 'X']);
    $hrd = User::factory()->withRole(Role::Hrd)->create();

    foreach ([[$hrd, "/karyawan/{$staff->id}"], [$manager->user, "/karyawan/{$staff->id}"], [$staff->user, '/profil-saya']] as [$user, $url]) {
        $page = $this->actingAs($user)->get($url)->assertOk()->viewData('page');
        expect(json_encode($page['props']))->not->toContain('1234567890');
    }
});

test('token QR karyawan tidak dikirim ke halaman profil', function () {
    $staff = Employee::factory()->withRole(Role::Karyawan)->create();

    $this->actingAs($staff->user)->get('/profil-saya')
        ->assertInertia(fn (Assert $page) => $page->missing('employee.qr_token'));
    expect(json_encode($this->get('/profil-saya')->viewData('page')['props']))->not->toContain($staff->qr_token);
});

test('unggahan berbahaya ditolak: lampiran cuti hanya PDF/JPG/PNG ≤ 2 MB, CV hanya PDF', function () {
    Storage::fake('local');
    $this->seed(LeaveTypesSeeder::class);
    $staff = Employee::factory()->withRole(Role::Karyawan)->create();

    $this->actingAs($staff->user)->post('/cuti-izin', [
        'leave_type_id' => LeaveType::query()->where('code', 'SKT')->sole()->id,
        'start_date' => now()->addWeek()->startOfWeek()->toDateString(),
        'end_date' => now()->addWeek()->startOfWeek()->toDateString(),
        'reason' => 'Sakit',
        'attachment' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php'),
    ])->assertSessionHasErrors('attachment');

    $this->post('/cuti-izin', [
        'leave_type_id' => LeaveType::query()->where('code', 'SKT')->sole()->id,
        'start_date' => now()->addWeek()->startOfWeek()->toDateString(),
        'end_date' => now()->addWeek()->startOfWeek()->toDateString(),
        'reason' => 'Sakit',
        'attachment' => UploadedFile::fake()->create('besar.pdf', 3000, 'application/pdf'),
    ])->assertSessionHasErrors('attachment');

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('file privat (lampiran, CV, foto, bukti bayar) tidak disimpan di disk publik', function () {
    expect(LeaveService::ATTACHMENT_DISK)->toBe('local')
        ->and(RecruitmentService::CV_DISK)->toBe('local')
        ->and(EmployeeService::PHOTO_DISK)->toBe('local')
        ->and(PayrollService::PROOF_DISK)->toBe('local')
        ->and(config('filesystems.disks.local.root'))->not->toContain('public');
});

test('.env tidak di-commit dan .env.example tidak berisi rahasia', function () {
    $tracked = trim((string) shell_exec('git -C '.escapeshellarg(base_path()).' ls-files .env'));
    $example = file_get_contents(base_path('.env.example'));

    expect($tracked)->toBe('')
        ->and($example)->toMatch('/^APP_KEY=$/m')
        ->and($example)->toMatch('/^DB_PASSWORD=$/m')
        ->and($example)->toMatch('/^MAIL_PASSWORD=null$/m');
});
