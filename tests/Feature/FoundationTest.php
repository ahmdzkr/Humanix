<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

test('beranda mengarahkan tamu ke halaman login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('beranda mengarahkan pengguna yang sudah login ke dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect(route('dashboard'));
});

test('halaman login dirender dalam bahasa Indonesia tanpa opsi registrasi', function () {
    expect(app()->getLocale())->toBe('id');

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('canResetPassword', true)
            ->missing('canRegister'));
});

test('registrasi publik dimatikan', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Penyusup',
        'email' => 'penyusup@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
});

test('login gagal memberi pesan generik yang sama untuk email tidak terdaftar dan kata sandi salah', function () {
    $user = User::factory()->create();

    $salahPassword = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'kata-sandi-salah',
    ]);
    $tidakTerdaftar = $this->post(route('login.store'), [
        'email' => 'tidak-ada@example.com',
        'password' => 'kata-sandi-salah',
    ]);

    $salahPassword->assertSessionHasErrors(['email' => 'Email atau kata sandi yang Anda masukkan salah.']);
    $tidakTerdaftar->assertSessionHasErrors(['email' => 'Email atau kata sandi yang Anda masukkan salah.']);
    $this->assertGuest();
});

test('login dibatasi 5 percobaan per menit', function () {
    $user = User::factory()->create();
    RateLimiter::clear(strtolower($user->email).'|127.0.0.1');

    foreach (range(1, 5) as $_) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'salah']);
    }

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertTooManyRequests();

    $this->assertGuest();
});

test('pengguna bisa logout', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

test('menu modul membutuhkan login', function (string $path) {
    $this->get("/{$path}")->assertRedirect(route('login'));
})->with(['karyawan', 'rekruitmen', 'absensi', 'cuti-izin', 'lembur', 'gaji', 'penilaian-kinerja', 'laporan', 'pengaturan']);

test('tidak ada lagi menu yang mengarah ke halaman placeholder', function () {
    $this->actingAs(User::factory()->withRole(Role::Hrd)->create())
        ->get('/laporan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('reports/index'));

    expect(file_exists(resource_path('js/pages/module-placeholder.tsx')))->toBeFalse();
});

test('header menerima label role, URL dashboard, dan jumlah notifikasi', function () {
    $this->actingAs(User::factory()->withRole(Role::Hrd)->create())
        ->get(route('hr.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/hr')
            ->where('notificationCount', 0)
            ->where('auth.roleLabel', 'HRD')
            ->where('auth.dashboardUrl', '/hr/dashboard')
            ->missing('auth.user.password')
            ->missing('auth.user.two_factor_secret'));
});

test('halaman tidak ditemukan memakai halaman error HUMANIX', function () {
    $this->get('/halaman-yang-tidak-ada')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', 404));
});
