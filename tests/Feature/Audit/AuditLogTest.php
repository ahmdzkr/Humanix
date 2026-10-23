<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\NotificationDelivery;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\SystemSettingsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->withRole(Role::Administrator)->create();
});

test('hanya Administrator yang bisa membuka audit log dan log email', function (Role $role) {
    $user = User::factory()->withRole($role)->create();

    $this->actingAs($user)->get('/audit-log')->assertForbidden();
    $this->actingAs($user)->get('/audit-log/email')->assertForbidden();
})->with([Role::Hrd, Role::Manager, Role::Karyawan]);

test('audit log menampilkan pelaku, modul, dan nilai sebelum/sesudah; filter modul, aksi, dan tanggal bekerja', function () {
    $this->actingAs($this->admin);
    $department = Department::factory()->create(['name' => 'Gudang']);
    $department->update(['name' => 'Logistik']);

    $this->get('/audit-log?module=Department&event=updated')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audit/index')
            ->where('logs.total', 1)
            ->where('logs.data.0.actor', $this->admin->name)
            ->where('logs.data.0.module', 'Departemen')
            ->where('logs.data.0.changes', fn ($changes) => collect($changes)->contains(fn ($c) => $c['field'] === 'name' && $c['old'] === 'Gudang' && $c['new'] === 'Logistik')));

    $this->get('/audit-log?module=Department&from=2000-01-01&to=2000-01-02')
        ->assertInertia(fn (Assert $page) => $page->where('logs.total', 0));
});

test('kata sandi, token, dan nomor rekening tidak pernah tampil di audit log', function () {
    $this->actingAs($this->admin);
    $employee = Employee::factory()->create();
    $employee->bankAccount()->create(['bank_name' => 'BCA', 'account_number' => '9988776655', 'account_holder' => 'X']);
    $employee->user->update(['password' => 'kata-sandi-baru-123']);

    // Juga bila data lama sempat tersimpan tanpa disaring.
    AuditLog::create(['event' => 'updated', 'auditable_type' => User::class, 'auditable_id' => $employee->user_id,
        'old_values' => ['remember_token' => 'abc', 'name' => 'A'], 'new_values' => ['remember_token' => 'def', 'name' => 'B']]);

    $response = $this->get('/audit-log')->assertOk();
    $json = json_encode($response->viewData('page')['props']['logs']);

    expect($json)->not->toContain('9988776655')
        ->and($json)->not->toContain('kata-sandi-baru-123')
        ->and($json)->not->toContain('remember_token')
        ->and($json)->not->toContain('"password"');
});

test('aksi kunci tercatat: keputusan persetujuan cuti dan perubahan pengaturan sistem', function () {
    $this->seed(SystemSettingsSeeder::class);
    $this->actingAs($this->admin)->put('/pengaturan/sistem', [
        'company_name' => 'PT Baru', 'late_tolerance_minutes' => 15, 'max_minutes_per_day' => 240,
        'overtime_hourly_divisor' => 173, 'overtime_first_hour_multiplier' => '1.5', 'overtime_next_hour_multiplier' => '2',
        'interview_indicators' => ['Komunikasi'],
    ])->assertSessionHasNoErrors();

    expect(AuditLog::query()->where('auditable_type', SystemSetting::class)->where('user_id', $this->admin->id)->count())->toBe(2);
});

test('log email menampilkan status apa adanya, termasuk yang gagal', function () {
    NotificationDelivery::create(['email' => 'a@x.test', 'category' => 'payroll', 'subject' => 'Slip', 'status' => 'failed', 'attempts' => 3, 'error' => 'Connection refused']);
    NotificationDelivery::create(['email' => 'b@x.test', 'category' => 'payroll', 'subject' => 'Slip', 'status' => 'sent', 'attempts' => 1]);

    $this->actingAs($this->admin)->get('/audit-log/email?status=failed')
        ->assertInertia(fn (Assert $page) => $page
            ->component('audit/emails')
            ->where('deliveries.total', 1)
            ->where('deliveries.data.0.status_label', 'Gagal')
            ->where('deliveries.data.0.error', 'Connection refused')
            ->where('counts.sent', 1));
});
