<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->employee = Employee::factory()->withRole(Role::Karyawan)->create(['nik' => 'EMP-0100', 'phone' => '0811']);
});

test('karyawan bisa melihat profilnya sendiri tanpa token QR', function () {
    $this->actingAs($this->employee->user)->get('/profil-saya')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employees/my-profile')
            ->where('employee.nik', 'EMP-0100')
            ->missing('employee.qr_token'));
});

test('karyawan bisa mengubah telepon, alamat, dan foto miliknya', function () {
    $this->actingAs($this->employee->user)->post('/profil-saya', [
        'phone' => '0812 1111 2222',
        'address' => 'Jl. Merdeka No. 1, Bandung',
        'photo' => UploadedFile::fake()->image('saya.png'),
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $employee = $this->employee->fresh();

    expect($employee->phone)->toBe('0812 1111 2222')
        ->and($employee->address)->toBe('Jl. Merdeka No. 1, Bandung')
        ->and($employee->histories()->first()->type)->toBe('profile_updated');
    Storage::disk('local')->assertExists($employee->photo_path);
});

test('NIK, jabatan, departemen, dan status ditolak di server saat diubah karyawan sendiri', function () {
    $otherDepartment = Department::factory()->create();

    $this->actingAs($this->employee->user)->post('/profil-saya', [
        'phone' => '0899',
        'nik' => 'HACK-1',
        'department_id' => $otherDepartment->id,
        'position_id' => 1,
        'employment_status' => 'permanent',
        'is_active' => true,
    ])->assertSessionHasErrors([
        'nik' => 'NIK hanya dapat diubah oleh HRD.',
        'department_id' => 'Departemen hanya dapat diubah oleh HRD.',
        'position_id',
        'employment_status',
        'is_active',
    ]);

    $employee = $this->employee->fresh();
    expect($employee->nik)->toBe('EMP-0100')
        ->and($employee->phone)->toBe('0811')
        ->and($employee->department_id)->not->toBe($otherDepartment->id);
});

test('karyawan bisa mengunduh ID Card miliknya sendiri', function () {
    $this->actingAs($this->employee->user)->get('/profil-saya/id-card')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('akun tanpa data karyawan mendapat 404 di Profil Saya', function () {
    $this->actingAs(User::factory()->withRole(Role::Administrator)->create())
        ->get('/profil-saya')
        ->assertNotFound();
});
