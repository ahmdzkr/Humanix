<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Position;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\SystemSettingsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('struktur organisasi menyusun departemen aktif sebagai pohon', function () {
    $ops = Department::factory()->create(['name' => 'Operasional']);
    $prd = Department::factory()->create(['name' => 'Produksi', 'parent_id' => $ops->id]);
    Department::factory()->inactive()->create(['name' => 'Divisi Lama']);
    Position::factory()->create(['department_id' => $prd->id, 'name' => 'Operator', 'level' => 1]);
    Position::factory()->create(['department_id' => $prd->id, 'name' => 'Jabatan Lama', 'is_active' => false]);
    Position::factory()->create(['department_id' => null, 'name' => 'Direktur Utama', 'level' => 10]);

    $this->actingAs(User::factory()->withRole(Role::Karyawan)->create())
        ->get('/organisasi/struktur')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organization/structure')
            ->has('tree', 1)
            ->where('tree.0.name', 'Operasional')
            ->where('tree.0.children.0.name', 'Produksi')
            ->has('tree.0.children.0.positions', 1)
            ->where('tree.0.children.0.positions.0.name', 'Operator')
            ->where('unassignedPositions.0.name', 'Direktur Utama'));
});

test('create, update, dan delete departemen tercatat di audit log beserta pelakunya', function () {
    $hrd = User::factory()->withRole(Role::Hrd)->create();
    $this->actingAs($hrd);

    $this->post('/pengaturan/departemen', ['code' => 'QA', 'name' => 'Quality']);
    $department = Department::query()->where('code', 'QA')->firstOrFail();
    $this->put("/pengaturan/departemen/{$department->id}", ['code' => 'QA', 'name' => 'Quality Assurance', 'is_active' => true]);
    $this->delete("/pengaturan/departemen/{$department->id}");

    $logs = AuditLog::query()
        ->where('auditable_type', $department->getMorphClass())
        ->where('auditable_id', $department->id)
        ->orderBy('id')
        ->get();

    expect($logs->pluck('event')->all())->toBe(['created', 'updated', 'deleted'])
        ->and($logs->pluck('user_id')->unique()->all())->toBe([$hrd->id])
        ->and($logs[1]->old_values)->toBe(['name' => 'Quality'])
        ->and($logs[1]->new_values)->toBe(['name' => 'Quality Assurance'])
        ->and($logs[0]->ip_address)->toBe('127.0.0.1');
});

test('audit log tidak pernah menyimpan password atau rahasia 2FA', function () {
    $user = User::factory()->create();
    $user->update(['password' => 'kata-sandi-baru-123', 'name' => 'Nama Baru']);
    $user->forceFill(['two_factor_secret' => encrypt('rahasia')])->save();

    $logs = AuditLog::query()->where('auditable_type', $user->getMorphClass())->where('auditable_id', $user->id)->get();
    $json = $logs->toJson();

    expect($logs)->not->toBeEmpty()
        ->and($json)->not->toContain('password')
        ->and($json)->not->toContain('two_factor_secret')
        ->and($json)->not->toContain('remember_token')
        ->and($logs->firstWhere('event', 'updated')->new_values)->toBe(['name' => 'Nama Baru']);
});

test('pengaturan sistem punya nilai awal dan bisa dibaca dari cache', function () {
    $this->seed(SystemSettingsSeeder::class);

    expect(SystemSetting::get('late_tolerance_minutes'))->toBe(15)
        ->and(SystemSetting::get('leave.approval_flow.tidak-ada', 'default'))->toBe('default')
        ->and(SystemSetting::get('approval_flow'))->toBe(['manager', 'hrd']);

    SystemSetting::set('late_tolerance_minutes', 10);

    expect(SystemSetting::get('late_tolerance_minutes'))->toBe(10);
});
