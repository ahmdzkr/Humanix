<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\SystemSettingsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(SystemSettingsSeeder::class);
    $this->admin = User::factory()->withRole(Role::Administrator)->create();
});

function validSettings(array $overrides = []): array
{
    return [
        'company_name' => 'PT Humanix Nusantara',
        'late_tolerance_minutes' => 10,
        'max_minutes_per_day' => 180,
        'overtime_hourly_divisor' => 173,
        'overtime_first_hour_multiplier' => '1.5',
        'overtime_next_hour_multiplier' => '2',
        'interview_indicators' => ['Kompetensi teknis', 'Komunikasi'],
        ...$overrides,
    ];
}

test('administrator melihat dan menyimpan pengaturan sistem, tercatat di audit log', function () {
    $this->actingAs($this->admin)->get('/pengaturan/sistem')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/system')
            ->where('settings.company_name', 'PT Contoh Indonesia')
            ->where('settings.late_tolerance_minutes', 15));

    $this->put('/pengaturan/sistem', validSettings(['company_name' => '  PT Humanix Nusantara ']))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect(SystemSetting::get('company_name'))->toBe('PT Humanix Nusantara')
        ->and(SystemSetting::get('late_tolerance_minutes'))->toBe(10)
        ->and(SystemSetting::get('max_minutes_per_day'))->toBe(180)
        ->and(SystemSetting::get('interview_indicators'))->toBe(['Kompetensi teknis', 'Komunikasi']);

    // Hanya nilai yang berubah yang dicatat (pembagi & pengali tidak berubah).
    $audited = AuditLog::query()->where('auditable_type', SystemSetting::class)->where('event', 'updated')->count();
    expect($audited)->toBe(4);
});

test('nilai di luar batas ditolak', function () {
    $this->actingAs($this->admin)->put('/pengaturan/sistem', validSettings([
        'company_name' => '',
        'late_tolerance_minutes' => -1,
        'max_minutes_per_day' => 5000,
        'overtime_first_hour_multiplier' => '0.5',
        'interview_indicators' => ['Sama', 'Sama'],
    ]))->assertSessionHasErrors([
        'company_name', 'late_tolerance_minutes', 'max_minutes_per_day',
        'overtime_first_hour_multiplier', 'interview_indicators.0',
    ]);

    expect(SystemSetting::get('company_name'))->toBe('PT Contoh Indonesia');
});

test('dashboard admin menandai nama perusahaan sudah diisi setelah disimpan', function () {
    $this->actingAs($this->admin)->put('/pengaturan/sistem', validSettings());

    $this->get('/admin/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('metrics.config.0.ok', true));
});

test('selain administrator tidak dapat membuka atau mengubah pengaturan sistem', function (Role $role) {
    $user = User::factory()->withRole($role)->create();

    $this->actingAs($user)->get('/pengaturan/sistem')->assertForbidden();
    $this->put('/pengaturan/sistem', validSettings())->assertForbidden();

    expect(SystemSetting::get('company_name'))->toBe('PT Contoh Indonesia');
})->with([Role::Hrd, Role::Manager, Role::Karyawan]);
