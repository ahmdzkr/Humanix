<?php

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\Position;
use App\Notifications\HrisNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Lowongan ditutup otomatis saat kuota (pelamar berkeputusan akhir Diterima) terpenuhi.
*/

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00', 'Asia/Jakarta'));
    $this->seed(SystemSettingsSeeder::class);

    $this->hrd = Employee::factory()->withRole(Role::Hrd)->create()->user;
    $this->manager = Employee::factory()->withRole(Role::Manager)->create()->user;
    $this->department = Department::factory()->create(['name' => 'Teknologi Informasi']);
    $this->position = Position::factory()->create(['department_id' => $this->department->id, 'name' => 'Programmer']);
});

/** Pelamar sampai tahap wawancara yang sudah dinilai, pada lowongan tertentu. */
function interviewedOn(JobVacancy $vacancy, string $email): JobApplication
{
    applyTo($vacancy, ['email' => $email, 'full_name' => 'Pelamar '.$email]);
    $application = JobApplication::query()->latest('id')->first();
    test()->actingAs(test()->hrd)->post("/rekruitmen/lamaran/{$application->id}/seleksi", ['decision' => 'pass']);
    test()->post("/rekruitmen/lamaran/{$application->id}/wawancara", [
        'scheduled_at' => '2026-10-14T10:00', 'duration_minutes' => 60, 'mode' => 'offline', 'location' => 'Ruang 1', 'interviewer_id' => test()->manager->id,
    ]);
    scoreInterview($application->interviews()->latest('id')->first(), test()->manager);

    return $application->fresh();
}

function publishedVacancy(int $quota): JobVacancy
{
    $vacancy = createVacancy(['quota' => $quota]);
    test()->actingAs(test()->hrd)->post("/rekruitmen/lowongan/{$vacancy->id}/status", ['status' => 'published']);

    return $vacancy->fresh();
}

test('lowongan ditutup otomatis saat pelamar terakhir yang mengisi kuota diterima', function () {
    $vacancy = publishedVacancy(2);
    $first = interviewedOn($vacancy, 'a@contoh.com');
    $second = interviewedOn($vacancy, 'b@contoh.com');
    $pending = interviewedOn($vacancy, 'c@contoh.com');

    $this->actingAs($this->hrd)->post("/rekruitmen/lamaran/{$first->id}/keputusan", ['decision' => 'accept'])->assertSessionHas('success');
    expect($vacancy->fresh()->status)->toBe('published');

    $this->post("/rekruitmen/lamaran/{$second->id}/keputusan", ['decision' => 'accept'])
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'lowongan ditutup otomatis'));

    $vacancy->refresh();
    expect($vacancy->status)->toBe('closed')
        ->and($vacancy->closed_reason)->toBe('quota')
        ->and($vacancy->closed_at)->not->toBeNull()
        // Pelamar yang masih diproses tidak diubah.
        ->and($pending->fresh()->status)->toBe(ApplicationStatus::Interview)
        // Tercatat di audit log.
        ->and(AuditLog::query()->where('auditable_type', $vacancy->getMorphClass())->where('auditable_id', $vacancy->id)->latest('id')->first()->new_values)
        ->toMatchArray(['status' => 'closed', 'closed_reason' => 'quota']);

    // HRD diberi tahu (in-app).
    Notification::assertSentTo($this->hrd, HrisNotification::class,
        fn ($n, array $channels) => $n->title === 'Lowongan ditutup otomatis' && $channels === ['database']);

    // HRD melihat progres kuota dan penanda penutupan otomatis.
    $this->get('/rekruitmen')->assertInertia(fn (Assert $page) => $page
        ->where('vacancies.0.closed_reason', 'quota')->where('vacancies.0.hired_count', 2)->where('vacancies.0.quota', 2));
    $this->get("/rekruitmen/lowongan/{$vacancy->id}")->assertInertia(fn (Assert $page) => $page
        ->where('vacancy.filled', 2)->where('vacancy.closed_reason', 'quota'));

    // Publik: hilang dari daftar, tautan lama menampilkan "ditutup", lamaran baru ditolak.
    auth()->logout();
    $this->get('/karier')->assertInertia(fn (Assert $page) => $page->has('vacancies', 0));
    $this->get("/karier/{$vacancy->slug}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('vacancy.closed', true));
    applyTo($vacancy, ['email' => 'baru@contoh.com'])->assertSessionHasErrors('vacancy');
    expect(JobApplication::query()->where('job_vacancy_id', $vacancy->id)->count())->toBe(3);
});

test('menurunkan kuota sampai sama dengan jumlah diterima menutup lowongan', function () {
    $vacancy = publishedVacancy(3);
    $application = interviewedOn($vacancy, 'a@contoh.com');
    $this->actingAs($this->hrd)->post("/rekruitmen/lamaran/{$application->id}/keputusan", ['decision' => 'accept']);
    expect($vacancy->fresh()->status)->toBe('published');

    $this->put("/rekruitmen/lowongan/{$vacancy->id}", [
        'title' => $vacancy->title, 'department_id' => $this->department->id, 'position_id' => $this->position->id,
        'employment_status' => 'contract', 'location' => 'Bandung', 'description' => 'x', 'requirements' => 'x', 'quota' => 1, 'deadline' => '2026-10-31',
    ])->assertSessionHas('success', 'Lowongan diperbarui dan ditutup otomatis karena kuota sudah terpenuhi.');

    expect($vacancy->fresh())->status->toBe('closed')->closed_reason->toBe('quota');
});

test('menolak pelamar tidak menutup lowongan; penutupan manual tercatat sebagai manual', function () {
    $vacancy = publishedVacancy(1);
    $application = interviewedOn($vacancy, 'a@contoh.com');

    $this->actingAs($this->hrd)->post("/rekruitmen/lamaran/{$application->id}/keputusan", ['decision' => 'reject', 'note' => 'Belum sesuai']);
    expect($vacancy->fresh()->status)->toBe('published');

    $this->post("/rekruitmen/lowongan/{$vacancy->id}/status", ['status' => 'closed'])->assertSessionHas('success');
    expect($vacancy->fresh())->status->toBe('closed')->closed_reason->toBe('manual');
});

test('hanya HRD yang bisa memicu keputusan yang menutup lowongan', function () {
    $vacancy = publishedVacancy(1);
    $application = interviewedOn($vacancy, 'a@contoh.com');

    $this->actingAs($this->manager)->post("/rekruitmen/lamaran/{$application->id}/keputusan", ['decision' => 'accept'])->assertForbidden();
    expect($vacancy->fresh()->status)->toBe('published');
});
