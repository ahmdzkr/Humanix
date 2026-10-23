<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Matriks akses vertikal: setiap role × setiap halaman.
| A = Administrator, H = HRD, M = Manager, K = Karyawan.
*/
/** Halaman (GET tanpa parameter) → role yang boleh membuka. Setiap route GET baru wajib masuk sini. */
function accessMatrix(): array
{
    $all = ['A', 'H', 'M', 'K'];

    return [
        '/admin/dashboard' => ['A'],
        '/hr/dashboard' => ['H'],
        '/manager/dashboard' => ['M'],
        '/me/dashboard' => ['K'],
        '/pengaturan' => ['A', 'H'],
        '/pengaturan/sistem' => ['A'],
        '/pengaturan/departemen' => ['A', 'H'],
        '/pengaturan/jabatan' => ['A', 'H'],
        '/pengaturan/jadwal-kerja' => ['H'],
        '/pengaturan/hari-libur' => ['H'],
        '/organisasi/struktur' => $all,
        '/karyawan' => ['A', 'H', 'M'],
        '/karyawan/tambah' => ['H'],
        '/karyawan/ekspor' => ['H'],
        '/profil-saya' => $all,
        '/profil-saya/id-card' => $all,
        '/rekruitmen' => ['H', 'M'],
        '/absensi' => ['H' => 200, 'M' => 200, 'K' => 302], // Karyawan → Absensi Saya
        '/absensi/saya' => ['H', 'M', 'K'],
        '/absensi/kios' => ['H'],
        '/absensi/ekspor' => ['H'],
        '/cuti-izin' => ['H', 'M', 'K'],
        '/lembur' => ['H', 'M', 'K'],
        '/gaji' => ['H' => 200, 'M' => 302, 'K' => 302], // tanpa payroll.view → Slip Saya
        '/gaji/slip-saya' => ['H', 'M', 'K'],
        '/gaji/komponen' => ['H'],
        '/gaji/struktur' => ['H'],
        '/penilaian-kinerja' => ['H', 'M', 'K'],
        '/laporan' => ['H', 'M'],
        '/laporan/cuti-lembur' => ['H', 'M'],
        '/cari' => $all,
        '/notifikasi' => $all,
        '/audit-log' => ['A'],
        '/audit-log/email' => ['A'],
        '/settings/profile' => $all,
        '/settings/appearance' => $all,
    ];
}

dataset('matriks akses', function () {
    $matrix = accessMatrix();

    $roles = ['A' => Role::Administrator, 'H' => Role::Hrd, 'M' => Role::Manager, 'K' => Role::Karyawan];

    foreach ($matrix as $path => $allowed) {
        foreach ($roles as $short => $role) {
            // Daftar = boleh (200) / tidak (403); peta = status eksplisit (mis. 302 ke halaman pribadi).
            $expected = array_is_list($allowed) ? (in_array($short, $allowed, true) ? 200 : 403) : ($allowed[$short] ?? 403);
            yield "{$role->value} {$path} → {$expected}" => [$role, $path, $expected];
        }
    }
});

test('role hanya bisa membuka halaman sesuai izinnya', function (Role $role, string $path, int $expected) {
    // Akun nyata: setiap role (termasuk Admin) di sini juga punya data karyawan.
    $this->actingAs(Employee::factory()->withRole($role)->create()->user)
        ->get($path)
        ->assertStatus($expected);
})->with('matriks akses');

test('setiap halaman GET tanpa parameter yang butuh login tercakup di matriks akses', function () {
    // Route bawaan Fortify/verifikasi dan pengalih diuji di tes Auth masing-masing.
    $ignored = ['dashboard', 'settings', 'settings/security', 'email/verify', 'user/confirm-password',
        'user/confirmed-password-status', 'user/two-factor-qr-code', 'user/two-factor-recovery-codes', 'user/two-factor-secret-key'];

    $uncovered = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($r) => in_array('GET', $r->methods(), true)
            && ! str_contains($r->uri(), '{')
            && in_array('auth', $r->gatherMiddleware(), true)
            && ! in_array($r->uri(), $ignored, true))
        ->map(fn ($r) => '/'.$r->uri())
        ->reject(fn ($uri) => array_key_exists($uri, accessMatrix()))
        ->values()->all();

    expect($uncovered)->toBe([]);
});

test('tamu diarahkan ke halaman login untuk setiap halaman di matriks', function () {
    foreach (array_keys(accessMatrix()) as $path) {
        $this->get($path)->assertRedirect('/login');
    }
});

test('halaman yang ditolak memakai halaman error 403 HUMANIX', function () {
    $this->actingAs(User::factory()->withRole(Role::Karyawan)->create())
        ->get('/pengaturan/departemen')
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 403));
});

test('setelah login setiap role diarahkan ke dashboard masing-masing', function (Role $role, string $url) {
    $user = User::factory()->withRole($role)->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('dashboard'))->assertRedirect($url);
})->with([
    [Role::Administrator, '/admin/dashboard'],
    [Role::Hrd, '/hr/dashboard'],
    [Role::Manager, '/manager/dashboard'],
    [Role::Karyawan, '/me/dashboard'],
]);

test('Administrator tidak diberi izin gaji dan kinerja secara default', function () {
    $admin = User::factory()->withRole(Role::Administrator)->create();

    $permissions = $admin->getAllPermissions()->pluck('name');

    expect($permissions->filter(fn ($p) => str_starts_with($p, 'payroll.') || str_starts_with($p, 'performance.')))
        ->toBeEmpty()
        ->and($permissions)->toContain('settings.manage', 'users.manage', 'audit.view');
});

test('menu sidebar hanya dikirim sesuai izin pengguna', function () {
    $this->actingAs(User::factory()->withRole(Role::Karyawan)->create())
        ->get('/me/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.roleLabel', 'Karyawan')
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->sort()->values()->all() === collect(Role::Karyawan->defaultPermissions())->sort()->values()->all()));
});

test('Manager dan Karyawan tidak bisa mengubah data master organisasi', function (Role $role) {
    $department = Department::factory()->create();
    $position = Position::factory()->create();
    $this->actingAs(User::factory()->withRole($role)->create());

    $this->post('/pengaturan/departemen', ['code' => 'X', 'name' => 'X'])->assertForbidden();
    $this->put("/pengaturan/departemen/{$department->id}", ['code' => 'X', 'name' => 'X'])->assertForbidden();
    $this->delete("/pengaturan/departemen/{$department->id}")->assertForbidden();
    $this->post('/pengaturan/jabatan', ['code' => 'X', 'name' => 'X', 'level' => 1])->assertForbidden();
    $this->put("/pengaturan/jabatan/{$position->id}", ['code' => 'X', 'name' => 'X', 'level' => 1])->assertForbidden();
    $this->delete("/pengaturan/jabatan/{$position->id}")->assertForbidden();

    expect(Department::query()->whereKey($department->id)->exists())->toBeTrue()
        ->and(Position::query()->whereKey($position->id)->exists())->toBeTrue();
})->with([Role::Manager, Role::Karyawan]);

test('perintah humanix:assign-role memberi role ke akun', function () {
    $user = User::factory()->create();
    (new RolesAndPermissionsSeeder)->run();

    $this->artisan('humanix:assign-role', ['email' => $user->email, 'role' => 'HRD'])->assertSuccessful();
    $this->artisan('humanix:assign-role', ['email' => $user->email, 'role' => 'Superadmin'])->assertFailed();
    $this->artisan('humanix:assign-role', ['email' => 'tidak@ada.test', 'role' => 'HRD'])->assertFailed();

    expect($user->fresh()->primaryRole())->toBe(Role::Hrd);
});
