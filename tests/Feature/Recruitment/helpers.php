<?php

use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/* Helper bersama tes rekrutmen (RecruitmentFlowTest, RecruitmentEmailTest). */

function createVacancy(array $overrides = []): JobVacancy
{
    test()->actingAs(test()->hrd)->post('/rekruitmen/lowongan', array_merge([
        'title' => 'Programmer Laravel',
        'department_id' => test()->department->id,
        'position_id' => test()->position->id,
        'employment_status' => 'contract',
        'location' => 'Bandung',
        'description' => 'Membangun aplikasi internal.',
        'requirements' => 'Menguasai PHP & Laravel.',
        'quota' => 1,
        'deadline' => '2026-10-31',
    ], $overrides))->assertSessionHas('success');

    return JobVacancy::query()->latest('id')->firstOrFail();
}

function applyTo(JobVacancy $vacancy, array $overrides = [])
{
    auth()->logout();

    return test()->post("/karier/{$vacancy->slug}/lamar", array_merge([
        'full_name' => 'Rizky Pratama',
        'email' => 'Rizky@contoh.com',
        'phone' => '0812-1111-2222',
        'last_education' => 'S1 Informatika',
        'cv' => UploadedFile::fake()->create('cv.pdf', 300, 'application/pdf'),
        'consent' => '1',
    ], $overrides));
}

// ---- helper ----

function scoreInterview(Interview $interview, User $interviewer): void
{
    test()->actingAs($interviewer)->post("/rekruitmen/wawancara/{$interview->id}/nilai", [
        'scores' => array_fill_keys(['Kompetensi teknis', 'Komunikasi', 'Pemecahan masalah', 'Kerja sama tim', 'Kesesuaian budaya kerja'], 4),
        'recommendation' => 'hire',
    ])->assertSessionHas('success');
}

/** @return array{0: JobApplication, 1: Interview} */
function interviewedApplication(bool $scored = true): array
{
    $vacancy = createVacancy();
    test()->actingAs(test()->hrd)->post("/rekruitmen/lowongan/{$vacancy->id}/status", ['status' => 'published']);
    applyTo($vacancy);
    $application = JobApplication::query()->latest('id')->first();
    test()->actingAs(test()->hrd)->post("/rekruitmen/lamaran/{$application->id}/seleksi", ['decision' => 'pass']);
    test()->post("/rekruitmen/lamaran/{$application->id}/wawancara", [
        'scheduled_at' => '2026-10-14T10:00', 'duration_minutes' => 60, 'mode' => 'offline', 'location' => 'Ruang 1', 'interviewer_id' => test()->manager->id,
    ]);
    $interview = Interview::query()->latest('id')->first();

    if ($scored) {
        scoreInterview($interview, test()->manager);
    }

    return [$application->fresh(), $interview];
}

function acceptedApplication(): JobApplication
{
    [$application] = interviewedApplication();
    test()->actingAs(test()->hrd)->post("/rekruitmen/lamaran/{$application->id}/keputusan", ['decision' => 'accept'])->assertSessionHas('success');

    return $application->fresh();
}

/** @return array<string, mixed> */
function onboardPayload(): array
{
    return [
        'nik' => 'EMP-9001', 'full_name' => 'Rizky Pratama', 'email' => 'rizky@contoh.com', 'phone' => '0812',
        'department_id' => test()->department->id, 'position_id' => test()->position->id,
        'employment_status' => 'permanent', 'join_date' => '2026-11-02',
    ];
}
