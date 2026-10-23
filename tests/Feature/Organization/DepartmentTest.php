<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole(Role::Hrd)->create());
});

test('HRD bisa melihat daftar departemen dengan pencarian dan filter status', function () {
    Department::factory()->create(['code' => 'HR', 'name' => 'Human Resources']);
    Department::factory()->create(['code' => 'FIN', 'name' => 'Keuangan']);
    Department::factory()->inactive()->create(['code' => 'OLD', 'name' => 'Divisi Lama']);

    $this->get('/pengaturan/departemen?search=keu')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organization/departments')
            ->has('departments.data', 1)
            ->where('departments.data.0.code', 'FIN'));

    $this->get('/pengaturan/departemen?status=inactive')
        ->assertInertia(fn (Assert $page) => $page
            ->has('departments.data', 1)
            ->where('departments.data.0.code', 'OLD'));
});

test('HRD bisa menambah departemen; kode diubah ke huruf kapital', function () {
    $parent = Department::factory()->create();

    $this->post('/pengaturan/departemen', [
        'code' => 'it',
        'name' => 'Teknologi Informasi',
        'parent_id' => $parent->id,
        'description' => 'Pengelola sistem',
        'is_active' => true,
    ])->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseHas('departments', [
        'code' => 'IT',
        'name' => 'Teknologi Informasi',
        'parent_id' => $parent->id,
        'is_active' => true,
    ]);
});

test('kode dan nama departemen wajib dan unik', function () {
    Department::factory()->create(['code' => 'HR', 'name' => 'Human Resources']);

    $this->post('/pengaturan/departemen', ['code' => '', 'name' => ''])
        ->assertSessionHasErrors(['code', 'name']);

    $this->post('/pengaturan/departemen', ['code' => 'hr', 'name' => 'Human Resources'])
        ->assertSessionHasErrors([
            'code' => 'Kode sudah digunakan.',
            'name' => 'Nama departemen sudah digunakan.',
        ]);

    $this->post('/pengaturan/departemen', ['code' => 'H R!', 'name' => 'Baru'])
        ->assertSessionHasErrors(['code' => 'Kode hanya boleh berisi huruf kapital, angka, dan tanda strip.']);
});

test('HRD bisa mengubah departemen tanpa bentrok dengan kodenya sendiri', function () {
    $department = Department::factory()->create(['code' => 'HR', 'name' => 'HR']);

    $this->put("/pengaturan/departemen/{$department->id}", [
        'code' => 'HR',
        'name' => 'Human Resources',
        'is_active' => false,
    ])->assertSessionHasNoErrors();

    expect($department->fresh())
        ->name->toBe('Human Resources')
        ->is_active->toBeFalse();
});

test('departemen induk tidak boleh dirinya sendiri atau turunannya', function () {
    $root = Department::factory()->create();
    $child = Department::factory()->create(['parent_id' => $root->id]);
    $grandchild = Department::factory()->create(['parent_id' => $child->id]);

    foreach ([$root, $grandchild] as $invalidParent) {
        $this->put("/pengaturan/departemen/{$root->id}", [
            'code' => $root->code,
            'name' => $root->name,
            'parent_id' => $invalidParent->id,
        ])->assertSessionHasErrors(['parent_id' => 'Departemen induk tidak boleh departemen ini sendiri atau turunannya.']);
    }

    expect($root->fresh()->parent_id)->toBeNull();
});

test('departemen yang masih punya sub-departemen atau jabatan tidak bisa dihapus', function () {
    $withChild = Department::factory()->create();
    Department::factory()->create(['parent_id' => $withChild->id]);
    $withPosition = Department::factory()->create();
    Position::factory()->create(['department_id' => $withPosition->id]);

    foreach ([$withChild, $withPosition] as $department) {
        $this->delete("/pengaturan/departemen/{$department->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('departments', ['id' => $department->id]);
    }
});

test('departemen kosong bisa dihapus', function () {
    $department = Department::factory()->create();

    $this->delete("/pengaturan/departemen/{$department->id}")->assertSessionHas('success');

    $this->assertDatabaseMissing('departments', ['id' => $department->id]);
});

test('departemen baru tanpa field is_active tetap aktif, dan update tanpa field tidak mengubah status', function () {
    $this->post('/pengaturan/departemen', ['code' => 'NEW', 'name' => 'Baru'])->assertSessionHasNoErrors();
    $department = Department::query()->where('code', 'NEW')->firstOrFail();
    expect($department->is_active)->toBeTrue();

    $department->update(['is_active' => false]);
    $this->put("/pengaturan/departemen/{$department->id}", ['code' => 'NEW', 'name' => 'Baru Lagi'])->assertSessionHasNoErrors();
    expect($department->fresh()->is_active)->toBeFalse();
});
