<?php

use App\Enums\PaymentStatus;
use App\Enums\PayrollStatus;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\Payment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\CarbonImmutable;
use Database\Seeders\SalaryComponentsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Periode Oktober 2026. "Hari ini" 2 November 2026.
| Karyawan: gaji pokok 10.000.000, transport 500.000, BPJS Kes 1%, PPh 21 150.000,
| lembur disetujui 120 menit → 10.000.000 ÷ 173 × (1 × 1,5 + 1 × 2) = 202.312,14.
*/
beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Jakarta'));
    $this->seed([SystemSettingsSeeder::class, SalaryComponentsSeeder::class]);

    $this->hrd = Employee::factory()->withRole(Role::Hrd)->create()->user;
    $this->employee = Employee::factory()->withRole(Role::Karyawan)->create(['full_name' => 'Budi Santoso', 'join_date' => '2025-01-01']);
    $this->employee->bankAccount()->create(['bank_name' => 'BCA', 'account_number' => '1234567890', 'account_holder' => 'Budi Santoso']);

    $structure = SalaryStructure::create(['employee_id' => $this->employee->id, 'base_salary' => '10000000', 'effective_from' => '2026-01-01']);
    $component = fn (string $code) => SalaryComponent::query()->where('code', $code)->sole()->id;
    $structure->items()->createMany([
        ['salary_component_id' => $component('TJ-TRP'), 'amount' => '500000'],
        ['salary_component_id' => $component('BPJS-KES'), 'rate' => '1'],
        ['salary_component_id' => $component('PPH21'), 'amount' => '150000'],
    ]);

    OvertimeRequest::create([
        'employee_id' => $this->employee->id, 'work_date' => '2026-10-07',
        'start_at' => CarbonImmutable::parse('2026-10-07 17:00', 'Asia/Jakarta'),
        'end_at' => CarbonImmutable::parse('2026-10-07 19:00', 'Asia/Jakarta'),
        'duration_minutes' => 120, 'reason' => 'Laporan', 'status' => 'approved', 'payable' => true,
    ]);

    $this->period = app(PayrollService::class)->createPeriod('2026-10', '2026-10-31');
});

function calculatePayroll(?PayrollPeriod $period = null)
{
    $period ??= test()->period;

    return test()->actingAs(test()->hrd)->post("/gaji/periode/{$period->id}/hitung", ['version' => $period->fresh()->version]);
}

function advancePayroll(string $target, ?User $user = null, ?int $version = null)
{
    return test()->actingAs($user ?? test()->hrd)->post('/gaji/periode/'.test()->period->id.'/status', [
        'target' => $target,
        'version' => $version ?? test()->period->fresh()->version,
    ]);
}

test('perhitungan gaji: pokok + tunjangan + lembur − potongan, presisi desimal', function () {
    calculatePayroll()->assertSessionHas('success', '1 slip gaji dihitung.');

    $record = PayrollRecord::query()->sole();
    $items = $record->items()->get()->mapWithKeys(fn ($i) => [$i->name => $i->amount]);

    expect($items->all())->toBe([
        'Gaji Pokok' => '10000000.00',
        'Tunjangan Transport' => '500000.00',
        'Lembur' => '202312.14',
        'BPJS Kesehatan (karyawan)' => '100000.00',
        'PPh 21' => '150000.00',
    ])
        ->and($record->total_earnings)->toBe('10702312.14')
        ->and($record->total_deductions)->toBe('250000.00')
        ->and($record->net_pay)->toBe('10452312.14')
        ->and($record->overtime_minutes)->toBe(120)
        ->and($record->maskedAccount())->toBe('****7890')
        ->and(OvertimeRequest::query()->sole()->payroll_record_id)->toBe($record->id)
        ->and($this->period->fresh()->status)->toBe(PayrollStatus::Calculated);
});

test('hitung ulang tidak menggandakan lembur dan mempertahankan penyesuaian manual', function () {
    calculatePayroll();
    $record = PayrollRecord::query()->sole();

    $this->post("/gaji/slip/{$record->id}/penyesuaian", ['name' => 'Kasbon', 'type' => 'deduction', 'amount' => '452312.14'])
        ->assertSessionHas('success');
    expect($record->fresh()->net_pay)->toBe('10000000.00');

    calculatePayroll();

    $record->refresh();
    expect($record->items()->where('source', 'overtime')->count())->toBe(1)
        ->and($record->items()->where('source', 'manual')->sole()->name)->toBe('Kasbon')
        ->and($record->net_pay)->toBe('10000000.00');
});

test('karyawan tanpa struktur gaji dilewati dan dilaporkan; yang nonaktif sebelum periode tidak dihitung', function () {
    Employee::factory()->create(['full_name' => 'Tanpa Struktur', 'nik' => 'EMP-X1', 'join_date' => '2025-01-01']);
    $resigned = Employee::factory()->inactive()->create(['deactivated_at' => '2026-09-15', 'join_date' => '2025-01-01']);
    SalaryStructure::create(['employee_id' => $resigned->id, 'base_salary' => '5000000', 'effective_from' => '2026-01-01']);

    calculatePayroll()->assertSessionHas('error', fn ($m) => str_contains($m, 'Tanpa Struktur (EMP-X1)'));

    expect(PayrollRecord::query()->pluck('employee_id')->all())->toBe([$this->employee->id]);
});

test('status hanya bisa maju satu langkah dan periode yang sudah direview terkunci', function () {
    advancePayroll('reviewed')->assertSessionHasErrors(['status' => 'Status tidak bisa diubah dari Draft ke Sudah Direview.']);

    calculatePayroll();
    advancePayroll('approved')->assertSessionHasErrors('status');
    advancePayroll('reviewed')->assertSessionHas('success');

    $record = PayrollRecord::query()->sole();
    calculatePayroll()->assertSessionHasErrors(['status' => 'Periode yang sudah direview tidak bisa dihitung ulang.']);
    $this->post("/gaji/slip/{$record->id}/penyesuaian", ['name' => 'Bonus', 'type' => 'earning', 'amount' => '1'])
        ->assertSessionHasErrors(['status' => 'Periode berstatus Sudah Direview sudah terkunci.']);

    advancePayroll('approved')->assertSessionHas('success');
    expect($this->period->fresh())
        ->status->toBe(PayrollStatus::Approved)
        ->approved_by->toBe($this->hrd->id);

    // Tidak bisa mundur.
    advancePayroll('reviewed')->assertSessionHasErrors('status');
});

test('versi lama ditolak sehingga dua pengguna tidak memproses periode bersamaan', function () {
    calculatePayroll();
    $stale = $this->period->fresh()->version;
    advancePayroll('reviewed', null, $stale)->assertSessionHas('success');
    advancePayroll('approved', null, $stale)->assertSessionHasErrors(['version' => 'Periode ini baru saja diubah oleh pengguna lain. Muat ulang halaman.']);
});

test('gaji bersih negatif menghalangi persetujuan', function () {
    calculatePayroll();
    $this->post('/gaji/slip/'.PayrollRecord::query()->sole()->id.'/penyesuaian', ['name' => 'Potongan besar', 'type' => 'deduction', 'amount' => '20000000']);
    advancePayroll('reviewed');

    advancePayroll('approved')->assertSessionHasErrors(['status' => 'Gaji bersih negatif untuk: Budi Santoso.']);
});

test('pembayaran: idempoten, Paid wajib bukti/referensi, gagal wajib alasan, dan periode ditutup setelah semua dibayar', function () {
    calculatePayroll();
    advancePayroll('reviewed');
    advancePayroll('approved');

    advancePayroll('closed')->assertSessionHasErrors(['status' => 'Masih ada 1 slip yang belum dibayar. Periode hanya bisa ditutup setelah semua dibayar.']);

    $this->post("/gaji/periode/{$this->period->id}/pembayaran")->assertSessionHas('success', '1 instruksi pembayaran dibuat.');
    $this->post("/gaji/periode/{$this->period->id}/pembayaran")->assertSessionHas('success', 'Semua slip sudah punya instruksi pembayaran.');
    $payment = Payment::query()->sole();
    expect($payment->amount)->toBe('10452312.14')->and($payment->status)->toBe(PaymentStatus::Pending);

    $this->post("/gaji/pembayaran/{$payment->id}", ['status' => 'paid'])
        ->assertSessionHasErrors(['reference' => 'Pembayaran hanya bisa ditandai Sudah Dibayar dengan nomor referensi transfer atau bukti pembayaran.']);
    $this->post("/gaji/pembayaran/{$payment->id}", ['status' => 'failed'])
        ->assertSessionHasErrors(['failure_reason' => 'Alasan kegagalan wajib diisi.']);
    $this->post("/gaji/pembayaran/{$payment->id}", ['status' => 'failed', 'failure_reason' => 'Rekening tidak aktif'])->assertSessionHas('success');

    // Percobaan kedua dibuat karena yang pertama gagal.
    $this->post("/gaji/periode/{$this->period->id}/pembayaran")->assertSessionHas('success', '1 instruksi pembayaran dibuat.');
    $retry = Payment::query()->where('status', 'pending')->sole();
    expect($retry->idempotency_key)->toEndWith('attempt-2');

    $this->post("/gaji/pembayaran/{$payment->id}", ['status' => 'paid', 'reference' => 'X'])->assertSessionHasErrors('status');
    $this->post("/gaji/pembayaran/{$retry->id}", [
        'status' => 'paid', 'proof' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
    ])->assertSessionHas('success');
    Storage::disk('local')->assertExists($retry->fresh()->proof_path);

    advancePayroll('closed')->assertSessionHas('success');
    $this->post("/gaji/pembayaran/{$retry->id}", ['status' => 'failed', 'failure_reason' => 'x'])
        ->assertSessionHasErrors(['status' => 'Pembayaran hanya bisa diubah selama periode berstatus Disetujui.']);
});

test('karyawan hanya melihat slip miliknya dan hanya setelah periode disetujui', function () {
    calculatePayroll();
    $record = PayrollRecord::query()->sole();
    $owner = $this->employee->user;

    $this->actingAs($owner)->get("/gaji/slip/{$record->id}")->assertForbidden();
    $this->actingAs($owner)->get('/gaji/slip-saya')->assertInertia(fn (Assert $page) => $page->has('slips', 0));

    advancePayroll('reviewed');
    expect($owner->notifications()->count())->toBe(0); // belum ada notifikasi sebelum disetujui

    advancePayroll('approved');

    // Notifikasi "slip tersedia" hanya ke pemilik slip, tanpa nominal gaji.
    $notification = $owner->notifications()->sole()->data;
    expect($notification['title'])->toBe('Slip gaji Oktober 2026 tersedia')
        ->and($notification['url'])->toBe("/gaji/slip/{$record->id}")
        ->and($notification['body'])->not->toContain('10.452')
        ->and($this->hrd->notifications()->count())->toBe(0);

    $this->actingAs($owner)->get('/gaji/slip-saya')
        ->assertInertia(fn (Assert $page) => $page->has('slips', 1)->where('slips.0.net_pay', '10452312.14'));
    $this->actingAs($owner)->get("/gaji/slip/{$record->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('record.bank_account', '****7890')
            ->missing('record.bank_account_number')
            ->where('payments', [])
            ->where('can.adjust', false));
    $this->actingAs($owner)->get("/gaji/slip/{$record->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('karyawan lain, atasan, dan Administrator tidak bisa melihat gaji orang lain', function () {
    calculatePayroll();
    advancePayroll('reviewed');
    advancePayroll('approved');
    $record = PayrollRecord::query()->sole();

    $colleague = Employee::factory()->withRole(Role::Karyawan)->create()->user;
    $boss = Employee::factory()->withRole(Role::Manager)->create();
    $this->employee->update(['manager_id' => $boss->id]);
    $admin = User::factory()->withRole(Role::Administrator)->create();

    foreach ([$colleague, $boss->user, $admin] as $user) {
        $this->actingAs($user)->get("/gaji/slip/{$record->id}")->assertForbidden();
        $this->actingAs($user)->get("/gaji/slip/{$record->id}/pdf")->assertForbidden();
        $this->actingAs($user)->get("/gaji/periode/{$this->period->id}")->assertForbidden();
        $this->actingAs($user)->get("/gaji/periode/{$this->period->id}/ekspor")->assertForbidden();
    }

    $this->actingAs($admin)->get('/gaji')->assertForbidden();
    $this->actingAs($colleague)->get('/gaji')->assertRedirect('/gaji/slip-saya');
    $this->actingAs($boss->user)->post("/gaji/periode/{$this->period->id}/hitung", ['version' => 1])->assertForbidden();
});

test('HRD bisa melihat periode, ekspor Excel, dan daftar slip', function () {
    calculatePayroll();

    $this->actingAs($this->hrd)->get("/gaji/periode/{$this->period->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('payroll/period')
            ->where('totals.count', 1)
            ->where('totals.net', '10452312.14')
            ->where('can.review', true)
            ->where('can.approve', false));

    expect($this->get("/gaji/periode/{$this->period->id}/ekspor")->assertOk()->headers->get('content-disposition'))->toContain('payroll-2026-10.xlsx');

    $this->post('/gaji/periode', ['month' => '2026-10', 'pay_date' => '2026-10-31'])
        ->assertSessionHasErrors(['month' => 'Periode payroll untuk bulan ini sudah ada.']);
});

test('struktur gaji baru menutup struktur lama dan tidak boleh mundur tanggal', function () {
    $component = SalaryComponent::query()->where('code', 'TJ-MKN')->sole();

    $this->actingAs($this->hrd)->post("/gaji/struktur/{$this->employee->id}", [
        'base_salary' => '12000000', 'effective_from' => '2026-11-01',
        'items' => [['salary_component_id' => $component->id, 'amount' => '750000']],
    ])->assertSessionHas('success');

    expect($this->employee->salaryStructureOn('2026-10-31')->base_salary)->toBe('10000000.00')
        ->and($this->employee->salaryStructureOn('2026-11-01')->base_salary)->toBe('12000000.00')
        ->and($this->employee->salaryStructureOn('2026-11-01')->items->sole()->amount)->toBe('750000.00');

    $this->post("/gaji/struktur/{$this->employee->id}", ['base_salary' => '1', 'effective_from' => '2026-10-15'])
        ->assertSessionHasErrors(['effective_from' => 'Sudah ada struktur gaji yang berlaku pada atau setelah tanggal ini.']);
    $this->post("/gaji/struktur/{$this->employee->id}", ['base_salary' => '-5', 'effective_from' => '2027-01-01'])
        ->assertSessionHasErrors('base_salary');
});
