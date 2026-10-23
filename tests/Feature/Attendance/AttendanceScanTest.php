<?php

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\EmployeeService;
use Carbon\CarbonImmutable;

/*
| Rabu 7 Oktober 2026, jadwal 08:00–17:00 WIB (Senin–Jumat), toleransi 10 menit.
| Semua waktu uji ditulis dalam WIB lalu dikirim ke server sebagai instant.
*/
function wib(string $datetime): CarbonImmutable
{
    return CarbonImmutable::parse($datetime, 'Asia/Jakarta');
}

beforeEach(function () {
    $this->schedule = WorkSchedule::create([
        'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00',
        'work_days' => [1, 2, 3, 4, 5], 'late_tolerance_minutes' => 10,
        'check_in_opens_minutes' => 120, 'check_out_closes_minutes' => 360,
    ]);
    $this->employee = Employee::factory()->create(['join_date' => '2026-01-01']);
    EmployeeSchedule::create(['employee_id' => $this->employee->id, 'work_schedule_id' => $this->schedule->id, 'effective_from' => '2026-01-01']);
    $this->kiosk = User::factory()->withRole(Role::Hrd)->create();
});

function scanAt(string $wibTime, ?string $token = null)
{
    test()->travelTo(wib($wibTime));

    return test()->actingAs(test()->kiosk)->postJson('/absensi/scan', ['token' => $token ?? test()->employee->qr_token]);
}

test('scan pertama tepat waktu tercatat absen masuk Hadir', function () {
    scanAt('2026-10-07 07:55')
        ->assertOk()
        ->assertJson(['ok' => true, 'type' => 'check_in', 'time' => '07:55', 'late_minutes' => 0]);

    $record = AttendanceRecord::query()->sole();
    expect($record->status)->toBe(AttendanceStatus::Present)
        ->and($record->work_date->toDateString())->toBe('2026-10-07')
        ->and($record->check_in_at->utc()->format('H:i'))->toBe('00:55');
});

test('terlambat dihitung dari jam masuk hanya jika melewati toleransi', function (string $time, string $status, int $late) {
    scanAt("2026-10-07 {$time}")->assertOk();

    expect(AttendanceRecord::query()->sole())
        ->status->toBe(AttendanceStatus::from($status))
        ->late_minutes->toBe($late);
})->with([
    'dalam toleransi' => ['08:10', 'present', 0],
    'lewat toleransi' => ['08:11', 'late', 11],
    'jauh terlambat' => ['09:30', 'late', 90],
]);

test('scan ganda ditolak, scan berikutnya menjadi absen pulang, lalu scan ketiga ditolak', function () {
    scanAt('2026-10-07 08:00')->assertOk();

    scanAt('2026-10-07 08:05')->assertStatus(422)
        ->assertJson(['ok' => false, 'message' => 'Anda sudah absen masuk pukul 08:00 WIB.']);

    scanAt('2026-10-07 17:02')->assertOk()->assertJson(['type' => 'check_out', 'time' => '17:02']);

    scanAt('2026-10-07 17:10')->assertStatus(422)
        ->assertJson(['message' => 'Anda sudah absen masuk dan pulang hari ini.']);

    expect(AttendanceRecord::query()->count())->toBe(1);
});

test('scan di luar jendela waktu ditolak', function (string $time, string $message) {
    scanAt("2026-10-07 {$time}")->assertStatus(422)->assertJson(['message' => $message]);
    expect(AttendanceRecord::query()->count())->toBe(0);
})->with([
    'terlalu pagi' => ['05:59', 'Absen masuk baru dibuka pukul 06:00 WIB.'],
    'jam kerja selesai tanpa absen masuk' => ['17:01', 'Jam kerja hari ini sudah berakhir. Hubungi HRD untuk koreksi absensi.'],
]);

test('absen pulang setelah batas ditolak', function () {
    scanAt('2026-10-07 08:00')->assertOk();
    scanAt('2026-10-07 23:01')->assertStatus(422)
        ->assertJson(['message' => 'Batas waktu absen pulang sudah lewat. Hubungi HRD untuk koreksi.']);
});

test('hari libur, akhir pekan, dan tanpa jadwal ditolak', function () {
    Holiday::create(['date' => '2026-10-07', 'name' => 'Libur Uji']);
    scanAt('2026-10-07 08:00')->assertStatus(422)->assertJson(['message' => 'Hari ini bukan hari kerja sesuai jadwal Anda.']);

    scanAt('2026-10-10 08:00')->assertStatus(422)->assertJson(['message' => 'Hari ini bukan hari kerja sesuai jadwal Anda.']);

    EmployeeSchedule::query()->delete();
    scanAt('2026-10-08 08:00')->assertStatus(422)->assertJson(['message' => 'Belum ada jadwal kerja aktif untuk hari ini. Hubungi HRD.']);
});

test('QR tidak dikenal, QR lama setelah dirotasi, dan karyawan nonaktif ditolak', function () {
    scanAt('2026-10-07 08:00', 'token-palsu')->assertStatus(422)->assertJson(['message' => 'QR tidak dikenali. Gunakan ID Card terbaru atau hubungi HRD.']);

    $oldToken = $this->employee->qr_token;
    app(EmployeeService::class)->rotateQrToken($this->employee);
    scanAt('2026-10-07 08:00', $oldToken)->assertStatus(422);

    $this->employee->update(['is_active' => false]);
    scanAt('2026-10-07 08:00', $this->employee->fresh()->qr_token)->assertStatus(422)->assertJson(['message' => 'Karyawan sudah nonaktif.']);
});

test('tanggal kerja mengikuti WIB, bukan UTC', function () {
    // 06:30 WIB Kamis = 23:30 UTC Rabu.
    scanAt('2026-10-08 06:30')->assertOk();

    expect(AttendanceRecord::query()->sole()->work_date->toDateString())->toBe('2026-10-08');
});

test('hanya akun berizin kios yang bisa membuka kios dan mengirim scan', function () {
    $karyawan = User::factory()->withRole(Role::Karyawan)->create();

    $this->actingAs($karyawan)->get('/absensi/kios')->assertForbidden();
    $this->actingAs($karyawan)->postJson('/absensi/scan', ['token' => $this->employee->qr_token])->assertForbidden();
    $this->actingAs($this->kiosk)->get('/absensi/kios')->assertOk();
    expect(AttendanceRecord::query()->count())->toBe(0);
});

test('penandaan malam hari: yang terjadwal dan tidak absen menjadi Tidak Hadir', function () {
    $present = Employee::factory()->create(['join_date' => '2026-01-01']);
    $inactive = Employee::factory()->inactive()->create(['join_date' => '2026-01-01']);
    $newHire = Employee::factory()->create(['join_date' => '2026-10-20']);
    $noSchedule = Employee::factory()->create(['join_date' => '2026-01-01']);

    foreach ([$present, $inactive, $newHire] as $e) {
        EmployeeSchedule::create(['employee_id' => $e->id, 'work_schedule_id' => $this->schedule->id, 'effective_from' => '2026-01-01']);
    }

    AttendanceRecord::create(['employee_id' => $present->id, 'work_date' => '2026-10-07', 'status' => 'present', 'check_in_at' => wib('2026-10-07 08:00')]);

    $this->artisan('humanix:mark-absent', ['--date' => '2026-10-07'])
        ->expectsOutputToContain('1 tidak hadir')
        ->assertSuccessful();

    expect(AttendanceRecord::query()->where('employee_id', $this->employee->id)->sole()->status)->toBe(AttendanceStatus::Absent)
        ->and(AttendanceRecord::query()->where('employee_id', $present->id)->sole()->status)->toBe(AttendanceStatus::Present)
        ->and(AttendanceRecord::query()->whereIn('employee_id', [$inactive->id, $newHire->id, $noSchedule->id])->count())->toBe(0);

    // Akhir pekan & hari libur tidak menghasilkan Tidak Hadir; menjalankan ulang tidak menggandakan.
    Holiday::create(['date' => '2026-10-08', 'name' => 'Libur']);
    $this->artisan('humanix:mark-absent', ['--date' => '2026-10-10'])->assertSuccessful();
    $this->artisan('humanix:mark-absent', ['--date' => '2026-10-08'])->assertSuccessful();
    $this->artisan('humanix:mark-absent', ['--date' => '2026-10-07'])->assertSuccessful();

    expect(AttendanceRecord::query()->count())->toBe(2);
});
