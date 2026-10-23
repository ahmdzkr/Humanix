<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\Position;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();

    $this->hrd = User::factory()->withRole(Role::Hrd)->create();
    $this->department = Department::factory()->create();
    $this->position = Position::factory()->create(['department_id' => $this->department->id]);
});

function employeePayload(array $overrides = []): array
{
    return array_merge([
        'nik' => 'emp-1001',
        'full_name' => 'Budi Santoso',
        'email' => 'Budi.Santoso@Contoh.co.id',
        'gender' => 'L',
        'phone' => '0812-3456-7890',
        'department_id' => test()->department->id,
        'position_id' => test()->position->id,
        'employment_status' => 'permanent',
        'join_date' => '2026-01-05',
        'bank_name' => 'BCA',
        'account_number' => '1234567890',
        'account_holder' => 'Budi Santoso',
    ], $overrides);
}

test('HRD membuat karyawan sekaligus akun login berrole Karyawan dalam satu langkah', function () {
    $response = $this->actingAs($this->hrd)->post('/karyawan', employeePayload([
        'photo' => UploadedFile::fake()->image('foto.jpg', 300, 400),
    ]));

    $employee = Employee::query()->where('nik', 'EMP-1001')->firstOrFail();
    $response->assertRedirect(route('employees.show', $employee))->assertSessionHas('success');

    expect($employee->user->email)->toBe('budi.santoso@contoh.co.id')
        ->and($employee->user->name)->toBe('Budi Santoso')
        ->and($employee->user->primaryRole())->toBe(Role::Karyawan)
        ->and($employee->qr_token)->toHaveLength(48)
        ->and($employee->bankAccount->account_number)->toBe('1234567890')
        ->and($employee->histories()->first()->type)->toBe('created');

    Storage::disk('local')->assertExists($employee->photo_path);
    Notification::assertSentTo($employee->user, ResetPassword::class);

    // Nomor rekening tersimpan terenkripsi, bukan teks biasa.
    $raw = DB::table('bank_accounts')->where('employee_id', $employee->id)->value('account_number');
    expect($raw)->not->toContain('1234567890');
});

test('jika penyimpanan karyawan gagal, akun login dan foto ikut dibatalkan (tanpa akun yatim)', function () {
    // NIK duplikat yang lolos validasi (mis. dua HRD menyimpan bersamaan) memicu error database.
    Employee::factory()->create(['nik' => 'EMP-1001']);
    $usersBefore = User::query()->count();

    $this->actingAs($this->hrd);

    expect(fn () => app(EmployeeService::class)->create(
        [...employeePayload(), 'nik' => 'EMP-1001', 'email' => 'baru@contoh.co.id'],
        UploadedFile::fake()->image('foto.jpg'),
    ))->toThrow(QueryException::class);

    expect(User::query()->count())->toBe($usersBefore)
        ->and(User::query()->where('email', 'baru@contoh.co.id')->exists())->toBeFalse()
        ->and(Storage::disk('local')->allFiles('employee-photos'))->toBeEmpty();
    Notification::assertNothingSent();
});

test('validasi: NIK & email unik, akhir kontrak wajib untuk kontrak, jabatan sesuai departemen', function () {
    $existing = Employee::factory()->create(['nik' => 'EMP-2000']);
    $otherDept = Department::factory()->create();
    $otherPosition = Position::factory()->create(['department_id' => $otherDept->id]);

    $this->actingAs($this->hrd)->post('/karyawan', employeePayload([
        'nik' => 'emp-2000',
        'email' => $existing->user->email,
        'employment_status' => 'contract',
        'position_id' => $otherPosition->id,
    ]))->assertSessionHasErrors([
        'nik' => 'NIK sudah digunakan.',
        'email' => 'Email sudah digunakan.',
        'contract_end_date' => 'Tanggal akhir kontrak wajib diisi untuk karyawan kontrak.',
        'position_id' => 'Jabatan tidak termasuk dalam departemen yang dipilih.',
    ]);
});

test('atasan tidak boleh diri sendiri atau bawahannya', function () {
    $boss = Employee::factory()->create(['department_id' => $this->department->id, 'position_id' => $this->position->id]);
    $staff = Employee::factory()->reportsTo($boss)->create(['department_id' => $this->department->id, 'position_id' => $this->position->id]);

    foreach ([$boss, $staff] as $invalid) {
        $this->actingAs($this->hrd)->post("/karyawan/{$boss->id}", employeePayload([
            'nik' => $boss->nik,
            'email' => $boss->user->email,
            'manager_id' => $invalid->id,
        ]))->assertSessionHasErrors(['manager_id' => 'Atasan tidak boleh karyawan ini sendiri atau bawahannya.']);
    }
});

test('perubahan data tercatat di riwayat dengan nilai lama dan baru; rekening kosong tidak menimpa', function () {
    $this->actingAs($this->hrd)->post('/karyawan', employeePayload());
    $employee = Employee::query()->where('nik', 'EMP-1001')->firstOrFail();
    $newPosition = Position::factory()->create(['department_id' => $this->department->id, 'name' => 'Supervisor']);

    $this->post("/karyawan/{$employee->id}", employeePayload([
        'nik' => 'EMP-1001',
        'email' => 'budi.santoso@contoh.co.id',
        'position_id' => $newPosition->id,
        'phone' => '0813-0000-0000',
        'account_number' => '',
    ]))->assertRedirect(route('employees.show', $employee));

    $history = $employee->histories()->where('type', 'updated')->firstOrFail();

    expect($history->changes)->toMatchArray([
        'Jabatan' => [$this->position->name, 'Supervisor'],
        'Telepon' => ['0812-3456-7890', '0813-0000-0000'],
    ])->and($history->changes)->not->toHaveKey('Rekening bank')
        ->and($history->user_id)->toBe($this->hrd->id)
        ->and($employee->fresh()->bankAccount->account_number)->toBe('1234567890');
});

test('karyawan yang dinonaktifkan tidak bisa login dan sesinya diakhiri', function () {
    $employee = Employee::factory()->create();

    // Sesi karyawan masih terbuka sebelum dinonaktifkan.
    $this->actingAs($employee->user)->get('/settings/profile')->assertOk();

    $this->actingAs($this->hrd)->patch("/karyawan/{$employee->id}/status", ['is_active' => false, 'note' => 'Resign'])
        ->assertSessionHas('success');

    expect($employee->fresh())->is_active->toBeFalse()->deactivated_at->not->toBeNull()
        ->and($employee->histories()->first())->type->toBe('deactivated')->note->toBe('Resign');

    // Pengguna dimuat ulang seperti pada request sungguhan.
    $this->actingAs($employee->user->fresh())->get('/settings/profile')->assertRedirect(route('login'));
    $this->assertGuest();

    $this->post(route('login.store'), ['email' => $employee->user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Akun Anda sudah dinonaktifkan. Hubungi HRD untuk informasi lebih lanjut.']);
    $this->assertGuest();
});

test('HRD tidak bisa menonaktifkan dirinya sendiri', function () {
    $self = Employee::factory()->withRole(Role::Hrd)->create();

    $this->actingAs($self->user)->patch("/karyawan/{$self->id}/status", ['is_active' => false])->assertForbidden();
});

test('ganti QR membuat token baru dan tercatat; ID Card bisa diunduh sebagai PDF', function () {
    $employee = Employee::factory()->create();
    $oldToken = $employee->qr_token;

    $this->actingAs($this->hrd)->post("/karyawan/{$employee->id}/qr")->assertSessionHas('success');

    expect($employee->fresh()->qr_token)->not->toBe($oldToken)
        ->and($employee->histories()->first()->type)->toBe('qr_rotated');

    $response = $this->get("/karyawan/{$employee->id}/id-card");
    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))->toContain("id-card-{$employee->nik}.pdf");
});

test('HRD bisa mengekspor daftar karyawan ke Excel', function () {
    Employee::factory()->count(2)->create();

    $response = $this->actingAs($this->hrd)->get('/karyawan/ekspor?status=active');

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');
});

test('daftar karyawan mendukung pencarian dan filter, tanpa membocorkan token QR', function () {
    Employee::factory()->create(['full_name' => 'Andi Wijaya', 'department_id' => $this->department->id, 'position_id' => $this->position->id]);
    Employee::factory()->create(['full_name' => 'Citra Kirana']);
    Employee::factory()->inactive()->create(['full_name' => 'Doni Mantan']);

    $this->actingAs($this->hrd)->get('/karyawan?search=andi')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
            ->where('employees.data.0.full_name', 'Andi Wijaya')
            ->missing('employees.data.0.qr_token')
            ->where('can.create', true));

    $this->get('/karyawan?status=inactive')
        ->assertInertia(fn (Assert $page) => $page->has('employees.data', 1)->where('employees.data.0.full_name', 'Doni Mantan'));

    $this->get("/karyawan?department={$this->department->id}")
        ->assertInertia(fn (Assert $page) => $page->has('employees.data', 1));
});

test('hanya Administrator yang bisa mengubah role akun karyawan', function () {
    $employee = Employee::factory()->withRole(Role::Karyawan)->create();
    $admin = User::factory()->withRole(Role::Administrator)->create();

    $this->actingAs($this->hrd)->put("/karyawan/{$employee->id}/role", ['role' => 'Manager'])->assertForbidden();
    $this->actingAs($admin)->put("/karyawan/{$employee->id}/role", ['role' => 'Manager'])->assertSessionHas('success');

    expect($employee->user->fresh()->primaryRole())->toBe(Role::Manager)
        ->and(EmployeeHistory::query()->where('employee_id', $employee->id)->where('type', 'role_changed')->exists())->toBeTrue();
});

test('HRD bisa mengirim ulang tautan atur kata sandi', function () {
    $employee = Employee::factory()->create();

    $this->actingAs($this->hrd)->post("/karyawan/{$employee->id}/tautan-kata-sandi")->assertSessionHas('success');

    Notification::assertSentTo($employee->user, ResetPassword::class);
});

test('kepala departemen harus karyawan aktif di departemen itu; departemen berisi karyawan tidak bisa dihapus', function () {
    $inside = Employee::factory()->create(['department_id' => $this->department->id, 'position_id' => $this->position->id]);
    $outside = Employee::factory()->create();

    $payload = ['code' => $this->department->code, 'name' => $this->department->name];

    $this->actingAs($this->hrd)->put("/pengaturan/departemen/{$this->department->id}", [...$payload, 'head_employee_id' => $outside->id])
        ->assertSessionHasErrors(['head_employee_id' => 'Kepala departemen harus karyawan aktif di departemen ini.']);

    $this->put("/pengaturan/departemen/{$this->department->id}", [...$payload, 'head_employee_id' => $inside->id])
        ->assertSessionHasNoErrors();
    expect($this->department->fresh()->head_employee_id)->toBe($inside->id);

    $this->delete("/pengaturan/departemen/{$this->department->id}")->assertSessionHas('error');
    $this->delete("/pengaturan/jabatan/{$this->position->id}")->assertSessionHas('error');
    $this->assertDatabaseHas('departments', ['id' => $this->department->id]);
    $this->assertDatabaseHas('positions', ['id' => $this->position->id]);
});
