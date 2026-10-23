<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole(Role::Administrator)->create());
});

test('Administrator bisa melihat daftar jabatan dan memfilter per departemen', function () {
    $hr = Department::factory()->create();
    $it = Department::factory()->create();
    Position::factory()->create(['department_id' => $hr->id, 'name' => 'Staff HR']);
    Position::factory()->create(['department_id' => $it->id, 'name' => 'Programmer']);

    $this->get("/pengaturan/jabatan?department={$it->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organization/positions')
            ->has('positions.data', 1)
            ->where('positions.data.0.name', 'Programmer'));
});

test('jabatan bisa ditambah, dengan atau tanpa departemen', function () {
    $department = Department::factory()->create();

    $this->post('/pengaturan/jabatan', [
        'code' => 'hr-stf', 'name' => 'Staff HR', 'department_id' => $department->id, 'level' => 3,
    ])->assertSessionHasNoErrors();

    $this->post('/pengaturan/jabatan', [
        'code' => 'DIR', 'name' => 'Direktur Utama', 'department_id' => '', 'level' => 10,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('positions', ['code' => 'HR-STF', 'department_id' => $department->id, 'level' => 3]);
    $this->assertDatabaseHas('positions', ['code' => 'DIR', 'department_id' => null]);
});

test('nama jabatan unik per departemen, tapi boleh sama di departemen lain', function () {
    $hr = Department::factory()->create();
    $it = Department::factory()->create();
    Position::factory()->create(['department_id' => $hr->id, 'name' => 'Staff']);

    $this->post('/pengaturan/jabatan', ['code' => 'A1', 'name' => 'Staff', 'department_id' => $hr->id, 'level' => 1])
        ->assertSessionHasErrors(['name' => 'Nama jabatan sudah ada di departemen ini.']);

    $this->post('/pengaturan/jabatan', ['code' => 'A2', 'name' => 'Staff', 'department_id' => $it->id, 'level' => 1])
        ->assertSessionHasNoErrors();
});

test('level jabatan harus 1 sampai 10 dan departemen harus ada', function () {
    $this->post('/pengaturan/jabatan', ['code' => 'X1', 'name' => 'A', 'level' => 0])
        ->assertSessionHasErrors('level');
    $this->post('/pengaturan/jabatan', ['code' => 'X2', 'name' => 'B', 'level' => 11])
        ->assertSessionHasErrors('level');
    $this->post('/pengaturan/jabatan', ['code' => 'X3', 'name' => 'C', 'level' => 1, 'department_id' => 99999])
        ->assertSessionHasErrors('department_id');
});

test('jabatan bisa diubah dan dihapus', function () {
    $position = Position::factory()->create(['level' => 2]);

    $this->put("/pengaturan/jabatan/{$position->id}", [
        'code' => $position->code,
        'name' => 'Supervisor',
        'department_id' => $position->department_id,
        'level' => 5,
        'is_active' => false,
    ])->assertSessionHasNoErrors();

    expect($position->fresh())->name->toBe('Supervisor')->level->toBe(5)->is_active->toBeFalse();

    $this->delete("/pengaturan/jabatan/{$position->id}")->assertSessionHas('success');
    $this->assertDatabaseMissing('positions', ['id' => $position->id]);
});
