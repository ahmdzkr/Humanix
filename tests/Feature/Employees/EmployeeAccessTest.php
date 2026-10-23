<?php

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Akses horizontal: mengganti ID di URL tidak boleh membuka data orang lain.
| Struktur: manager → supervisor → staff; outsider di luar tim.
*/
beforeEach(function () {
    Storage::fake('local');

    $this->manager = Employee::factory()->withRole(Role::Manager)->create(['full_name' => 'Manajer']);
    $this->supervisor = Employee::factory()->withRole(Role::Karyawan)->reportsTo($this->manager)->create(['full_name' => 'Supervisor']);
    $this->staff = Employee::factory()->withRole(Role::Karyawan)->reportsTo($this->supervisor)->create(['full_name' => 'Staff']);
    $this->outsider = Employee::factory()->withRole(Role::Karyawan)->create(['full_name' => 'Orang Luar']);
});

test('Manager hanya melihat bawahan langsung dan tidak langsung di daftar', function () {
    $this->actingAs($this->manager->user)->get('/karyawan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 2)
            ->where('employees.data', fn ($rows) => collect($rows)->pluck('full_name')->sort()->values()->all() === ['Staff', 'Supervisor'])
            ->where('can.create', false)
            ->where('can.export', false));
});

test('Manager bisa membuka profil bawahan tetapi tidak profil di luar timnya', function () {
    $this->actingAs($this->manager->user);

    $this->get("/karyawan/{$this->supervisor->id}")->assertOk();
    $this->get("/karyawan/{$this->staff->id}")->assertOk();
    $this->get("/karyawan/{$this->outsider->id}")->assertForbidden();
});

test('Manager tidak bisa mengubah data, mencetak ID Card, atau melihat rekening bawahan', function () {
    $this->staff->bankAccount()->create(['bank_name' => 'BRI', 'account_number' => '9876543210', 'account_holder' => 'Staff']);
    $this->actingAs($this->manager->user);

    $this->get("/karyawan/{$this->staff->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('employee.bank_account', null)
            ->where('can.update', false)
            ->missing('employee.qr_token'));

    $this->get("/karyawan/{$this->staff->id}/ubah")->assertForbidden();
    $this->post("/karyawan/{$this->staff->id}", ['nik' => 'X'])->assertForbidden();
    $this->get("/karyawan/{$this->staff->id}/id-card")->assertForbidden();
    $this->patch("/karyawan/{$this->staff->id}/status", ['is_active' => false])->assertForbidden();
    $this->post("/karyawan/{$this->staff->id}/qr")->assertForbidden();
    $this->get('/karyawan/ekspor')->assertForbidden();
    $this->get('/karyawan/tambah')->assertForbidden();
});

test('Karyawan tidak bisa membuka data, foto, atau ID Card karyawan lain dengan mengganti ID', function () {
    $this->outsider->update(['photo_path' => UploadedFile::fake()->image('a.jpg')->store('employee-photos', 'local')]);
    $this->actingAs($this->staff->user);

    $this->get('/karyawan')->assertForbidden();
    $this->get("/karyawan/{$this->outsider->id}")->assertForbidden();
    $this->get("/karyawan/{$this->supervisor->id}")->assertForbidden();
    $this->get("/karyawan/{$this->outsider->id}/foto")->assertForbidden();
    $this->get("/karyawan/{$this->outsider->id}/id-card")->assertForbidden();
    $this->post("/karyawan/{$this->outsider->id}", ['phone' => '0'])->assertForbidden();
});

test('Administrator bisa melihat data karyawan tetapi tidak mengubahnya dan tidak melihat rekening', function () {
    $this->staff->bankAccount()->create(['bank_name' => 'BRI', 'account_number' => '9876543210', 'account_holder' => 'Staff']);
    $admin = User::factory()->withRole(Role::Administrator)->create();
    $this->actingAs($admin);

    $this->get('/karyawan')->assertInertia(fn (Assert $page) => $page->has('employees.data', 4));
    $this->get("/karyawan/{$this->staff->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('employee.bank_account', null)
            ->where('can.update', false)
            ->where('can.changeRole', true));
    $this->get("/karyawan/{$this->staff->id}/ubah")->assertForbidden();
});

test('HRD melihat rekening dalam bentuk tersamar', function () {
    $this->staff->bankAccount()->create(['bank_name' => 'BRI', 'account_number' => '9876543210', 'account_holder' => 'Staff']);

    $this->actingAs(User::factory()->withRole(Role::Hrd)->create())
        ->get("/karyawan/{$this->staff->id}")
        ->assertInertia(fn (Assert $page) => $page->where('employee.bank_account.account_number', '****3210'));
});

test('foto karyawan tidak bisa diakses tanpa login dan tidak berada di folder publik', function () {
    $path = UploadedFile::fake()->image('a.jpg')->store('employee-photos', 'local');
    $this->staff->update(['photo_path' => $path]);

    $this->get("/karyawan/{$this->staff->id}/foto")->assertRedirect(route('login'));
    $this->actingAs($this->staff->user)->get("/karyawan/{$this->staff->id}/foto")->assertOk();

    expect(file_exists(public_path('storage/'.$path)))->toBeFalse();
});
