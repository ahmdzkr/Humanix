<?php

use App\Enums\ReviewStatus;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Manager → Staff (bawahan). Outsider tanpa atasan. Dua indikator: bobot 40 & 60, skala 1–5.
| Nilai atasan 4 & 5 → (75 × 40% + 100 × 60%) = 90 → "Istimewa".
*/
beforeEach(function () {
    $this->hrdEmployee = Employee::factory()->withRole(Role::Hrd)->create();
    $this->hrd = $this->hrdEmployee->user;
    $this->manager = Employee::factory()->withRole(Role::Manager)->create();
    $this->staff = Employee::factory()->withRole(Role::Karyawan)->reportsTo($this->manager)->create(['full_name' => 'Staff Tim']);
    $this->outsider = Employee::factory()->withRole(Role::Karyawan)->create(['full_name' => 'Tanpa Atasan']);

    $this->actingAs($this->hrd)->post('/penilaian-kinerja/periode', [
        'name' => 'Semester 2 2026', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'self_assessment_enabled' => true,
    ]);
    $this->period = PerformancePeriod::query()->sole();
});

function addIndicators(array $weights = [40, 60]): void
{
    foreach ($weights as $i => $weight) {
        test()->actingAs(test()->hrd)->post('/penilaian-kinerja/periode/'.test()->period->id.'/indikator', [
            'name' => 'Indikator '.($i + 1), 'weight' => $weight, 'scale_min' => 1, 'scale_max' => 5,
        ])->assertSessionHasNoErrors();
    }
}

function activatePeriod(): void
{
    test()->actingAs(test()->hrd)->post('/penilaian-kinerja/periode/'.test()->period->id.'/aktifkan')->assertSessionHas('success');
}

function reviewOf(Employee $employee): PerformanceReview
{
    return PerformanceReview::query()->where('employee_id', $employee->id)->sole();
}

/** @return array<int, int> indicator_id => skor */
function scoresFor(PerformanceReview $review, array $values): array
{
    $ids = $review->items()->orderBy('performance_indicator_id')->pluck('performance_indicator_id')->all();

    return array_combine($ids, $values);
}

function selfAssess(PerformanceReview $review, array $values = [4, 4])
{
    return test()->actingAs($review->employee->user)->post("/penilaian-kinerja/lembar/{$review->id}/evaluasi-diri", [
        'scores' => scoresFor($review, $values), 'achievements' => 'Menyelesaikan proyek X.', 'version' => $review->fresh()->version,
    ]);
}

function managerAssess(PerformanceReview $review, User $user, array $values = [4, 5])
{
    return test()->actingAs($user)->post("/penilaian-kinerja/lembar/{$review->id}/penilaian-atasan", [
        'scores' => scoresFor($review, $values), 'manager_comment' => 'Kinerja sangat baik.', 'version' => $review->fresh()->version,
    ]);
}

test('periode tidak bisa diaktifkan bila total bobot indikator bukan 100%', function () {
    $this->actingAs($this->hrd)->post("/penilaian-kinerja/periode/{$this->period->id}/aktifkan")
        ->assertSessionHasErrors(['period' => 'Tambahkan minimal satu indikator sebelum mengaktifkan periode.']);

    addIndicators([40, 50]);
    $this->post("/penilaian-kinerja/periode/{$this->period->id}/aktifkan")
        ->assertSessionHasErrors(['period' => 'Total bobot indikator harus tepat 100% (saat ini 90%).']);
    expect($this->period->fresh()->status)->toBe('draft')->and(PerformanceReview::query()->count())->toBe(0);

    addIndicators([10]);
    activatePeriod();

    expect($this->period->fresh()->status)->toBe('active')
        ->and(PerformanceReview::query()->count())->toBe(4)
        ->and(reviewOf($this->staff)->reviewer_id)->toBe($this->manager->user_id)
        ->and(reviewOf($this->outsider)->reviewer_id)->toBeNull()
        ->and(reviewOf($this->staff)->status)->toBe(ReviewStatus::Draft)
        ->and(reviewOf($this->staff)->items()->count())->toBe(3);

    // Indikator terkunci setelah aktif.
    $this->post("/penilaian-kinerja/periode/{$this->period->id}/indikator", ['name' => 'Baru', 'weight' => 5, 'scale_min' => 1, 'scale_max' => 5])
        ->assertSessionHasErrors(['period' => 'Indikator hanya bisa diubah saat periode masih Draft.']);
});

test('alur lengkap: evaluasi diri → atasan → HRD final 90 Istimewa → hasil tersembunyi sampai dipublikasikan', function () {
    addIndicators();
    activatePeriod();
    $review = reviewOf($this->staff);
    $titles = fn (Employee|User $who) => ($who instanceof Employee ? $who->user : $who)->notifications()->get()->pluck('data.title')->all();

    // Aktivasi: setiap karyawan diminta mengisi evaluasi diri.
    expect($titles($this->staff))->toContain('Isi evaluasi diri')
        ->and($titles($this->outsider))->toContain('Isi evaluasi diri');

    selfAssess($review)->assertSessionHas('success');
    expect($review->fresh()->status)->toBe(ReviewStatus::SelfAssessed)
        ->and($titles($this->manager))->toContain('Penilaian atasan diperlukan');

    managerAssess($review, $this->manager->user)->assertSessionHas('success');
    expect($review->fresh()->status)->toBe(ReviewStatus::ManagerReviewed)
        ->and($titles($this->hrd))->toContain('Penilaian menunggu pemeriksaan HRD');

    $this->actingAs($this->hrd)->post("/penilaian-kinerja/lembar/{$review->id}/keputusan-hrd", ['decision' => 'finalize', 'version' => $review->fresh()->version])
        ->assertSessionHas('success', 'Penilaian difinalkan: 90.00 (Istimewa).');

    // Sebelum publikasi, karyawan tidak melihat nilai atasan maupun nilai akhir.
    $this->actingAs($this->staff->user)->get("/penilaian-kinerja/lembar/{$review->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('review.result_hidden', true)
            ->where('review.final_score', null)
            ->where('review.rating', null)
            ->where('review.items.0.manager_score', null)
            ->where('review.items.0.self_score', 4)
            ->where('review.logs', []));
    $this->get('/penilaian-kinerja')->assertInertia(fn (Assert $page) => $page->where('myReviews.0.final_score', null));

    $this->actingAs($this->hrd)->post("/penilaian-kinerja/lembar/{$review->id}/publikasikan")->assertSessionHas('success');
    $published = $this->staff->user->notifications()->get()->firstWhere('data.title', 'Hasil penilaian kinerja tersedia');
    expect($published)->not->toBeNull();
    $published = $published->data;
    expect($published['title'])->toBe('Hasil penilaian kinerja tersedia')
        ->and($published['body'])->not->toContain('90');

    $this->actingAs($this->staff->user)->get("/penilaian-kinerja/lembar/{$review->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('review.result_hidden', false)
            ->where('review.final_score', '90.00')
            ->where('review.rating', 'Istimewa')
            ->where('review.items.1.manager_score', 5));

    expect($review->logs()->pluck('action')->all())->toBe(['published', 'finalized', 'manager_reviewed', 'self_assessed', 'created']);
});

test('skor di luar skala ditolak untuk evaluasi diri maupun penilaian atasan', function () {
    addIndicators();
    activatePeriod();
    $review = reviewOf($this->staff);

    selfAssess($review, [6, 3])->assertSessionHasErrors();
    expect($review->fresh()->status)->toBe(ReviewStatus::Draft);

    $ids = array_keys(scoresFor($review, [1, 1]));
    selfAssess($review, [3, 4]);

    managerAssess($review, $this->manager->user, [0, 3])
        ->assertSessionHasErrors(["scores.{$ids[0]}" => 'Nilai "Indikator 1" harus angka bulat 1–5.']);
    managerAssess($review, $this->manager->user, [2.5, 3])->assertSessionHasErrors("scores.{$ids[0]}");

    expect($review->fresh()->status)->toBe(ReviewStatus::SelfAssessed)
        ->and($review->items()->whereNotNull('manager_score')->count())->toBe(0);
});

test('atasan hanya bisa menilai bawahan dalam timnya', function () {
    addIndicators();
    activatePeriod();
    $staffReview = reviewOf($this->staff);
    $outsiderReview = reviewOf($this->outsider);
    selfAssess($staffReview);
    selfAssess($outsiderReview);

    $otherManager = Employee::factory()->withRole(Role::Manager)->create()->user;

    managerAssess($staffReview, $otherManager)->assertForbidden();
    managerAssess($outsiderReview, $this->manager->user)->assertForbidden();
    $this->actingAs($otherManager)->get("/penilaian-kinerja/lembar/{$staffReview->id}")->assertForbidden();

    // Karyawan tanpa atasan dinilai HRD.
    managerAssess($outsiderReview, $this->hrd)->assertSessionHas('success');

    // Bila staf dipindah ke tim lain setelah periode aktif, atasan lama tidak bisa menilai lagi.
    $this->staff->update(['manager_id' => null]);
    managerAssess($staffReview, $this->manager->user)->assertForbidden();
});

test('HRD bisa mengembalikan penilaian dengan catatan wajib, lalu atasan merevisi', function () {
    addIndicators();
    activatePeriod();
    $review = reviewOf($this->staff);
    selfAssess($review);
    managerAssess($review, $this->manager->user, [2, 2]);

    $this->actingAs($this->hrd)->post("/penilaian-kinerja/lembar/{$review->id}/keputusan-hrd", ['decision' => 'return', 'version' => $review->fresh()->version])
        ->assertSessionHasErrors(['note' => 'Catatan wajib diisi saat mengembalikan ke atasan.']);
    $this->post("/penilaian-kinerja/lembar/{$review->id}/keputusan-hrd", ['decision' => 'return', 'note' => 'Nilai tidak sesuai bukti capaian.', 'version' => $review->fresh()->version])
        ->assertSessionHas('success');
    expect($review->fresh()->status)->toBe(ReviewStatus::Returned);

    managerAssess($review, $this->manager->user, [3, 4])->assertSessionHas('success');
    expect($review->fresh()->status)->toBe(ReviewStatus::ManagerReviewed);
});

test('versi lama ditolak dan HRD tidak bisa memfinalkan penilaiannya sendiri', function () {
    addIndicators();
    activatePeriod();
    $review = reviewOf($this->staff);
    $stale = $review->version;
    selfAssess($review);

    $this->actingAs($this->manager->user)->post("/penilaian-kinerja/lembar/{$review->id}/penilaian-atasan", [
        'scores' => scoresFor($review, [3, 3]), 'version' => $stale,
    ])->assertSessionHasErrors(['version' => 'Penilaian ini baru saja diubah. Muat ulang halaman untuk melihat versi terbaru.']);

    $own = reviewOf($this->hrdEmployee);
    selfAssess($own);
    $otherHrd = Employee::factory()->withRole(Role::Hrd)->create()->user;
    managerAssess($own, $otherHrd)->assertSessionHas('success'); // HRD tanpa atasan dinilai HRD lain
    $this->actingAs($this->hrd)->post("/penilaian-kinerja/lembar/{$own->id}/keputusan-hrd", ['decision' => 'finalize', 'version' => $own->fresh()->version])
        ->assertForbidden();
});

test('tanpa evaluasi diri, penilaian langsung menunggu atasan', function () {
    $period = PerformancePeriod::create(['name' => 'Tanpa Evaluasi Diri', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'self_assessment_enabled' => false, 'status' => 'draft']);
    $period->indicators()->create(['name' => 'Tunggal', 'weight' => 100, 'scale_min' => 1, 'scale_max' => 5]);

    $this->actingAs($this->hrd)->post("/penilaian-kinerja/periode/{$period->id}/aktifkan")->assertSessionHas('success');

    expect($period->reviews()->where('employee_id', $this->staff->id)->sole()->status)->toBe(ReviewStatus::SelfAssessed);
});

test('rencana pengembangan dibuat setelah final dan periode hanya bisa ditutup setelah semua dipublikasikan', function () {
    addIndicators([100]);
    activatePeriod();
    $review = reviewOf($this->staff);

    $this->actingAs($this->hrd)->post("/penilaian-kinerja/lembar/{$review->id}/rencana", ['type' => 'training', 'description' => 'Kursus Laravel'])
        ->assertSessionHasErrors(['status' => 'Rencana pengembangan dibuat setelah penilaian difinalkan.']);

    selfAssess($review, [3]);
    managerAssess($review, $this->manager->user, [3]);
    $this->actingAs($this->hrd)->post("/penilaian-kinerja/lembar/{$review->id}/keputusan-hrd", ['decision' => 'finalize', 'version' => $review->fresh()->version]);

    $this->actingAs($this->manager->user)->post("/penilaian-kinerja/lembar/{$review->id}/rencana", ['type' => 'training', 'description' => 'Kursus Laravel', 'target_date' => '2027-03-31'])
        ->assertSessionHas('success');

    $this->actingAs($this->hrd)->post("/penilaian-kinerja/periode/{$this->period->id}/tutup")->assertSessionHasErrors('period');
});

test('hak akses halaman: karyawan tidak bisa membuka periode atau penilaian orang lain', function () {
    addIndicators();
    activatePeriod();

    $this->actingAs($this->staff->user)->get("/penilaian-kinerja/periode/{$this->period->id}")->assertForbidden();
    $this->actingAs($this->staff->user)->get('/penilaian-kinerja/lembar/'.reviewOf($this->outsider)->id)->assertForbidden();
    $this->actingAs($this->staff->user)->get('/penilaian-kinerja/lembar/'.reviewOf($this->staff)->id)->assertOk();

    $this->actingAs($this->manager->user)->get('/penilaian-kinerja')
        ->assertInertia(fn (Assert $page) => $page->has('teamReviews', 1)->where('teamReviews.0.employee_name', 'Staff Tim')->where('isHr', false));

    $this->actingAs(User::factory()->withRole(Role::Administrator)->create())->get('/penilaian-kinerja')->assertForbidden();

    $this->actingAs($this->hrd)->get("/penilaian-kinerja/periode/{$this->period->id}")
        ->assertInertia(fn (Assert $page) => $page->component('performance/period')->has('reviews', 4)->where('period.total_weight', '100.00'));
});

test('pengingat mingguan: karyawan yang belum evaluasi diri dan penilai menerima satu ringkasan', function () {
    addIndicators();
    activatePeriod();
    selfAssess(reviewOf($this->staff))->assertSessionHas('success');

    $this->artisan('humanix:remind-reviews')->assertSuccessful();

    // Staff sudah mengisi → tidak diingatkan; outsider belum → diingatkan.
    expect($this->outsider->user->notifications()->get()->pluck('data.title')->all())->toContain('Pengingat: Isi evaluasi diri')
        ->and($this->staff->user->notifications()->pluck('data')->pluck('title')->all())->not->toContain('Pengingat: Isi evaluasi diri')
        ->and($this->manager->user->notifications()->get()->firstWhere('data.title', 'Pengingat: Penilaian atasan diperlukan')->data['body'])->toBe('1 lembar penilaian kinerja menunggu penilaian Anda.');
});
