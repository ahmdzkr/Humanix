<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Models\Applicant;
use App\Models\ApplicationStatusHistory;
use App\Models\Department;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\Position;
use App\Services\RecruitmentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Lowongan & pelamar contoh (hanya lokal). CV berupa PDF contoh satu halaman.
 */
class RecruitmentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $deadline = now(config('app.display_timezone'))->addDays(30)->toDateString();

        $vacancies = [
            ['programmer-laravel', 'Programmer Laravel', 'IT', 'IT-DEV', 'contract', 'Bandung',
                "Mengembangkan dan memelihara aplikasi internal berbasis Laravel & React.\nBekerja sama dengan tim produk dan QA.",
                "Minimal S1 Informatika atau setara.\nMenguasai PHP 8, Laravel, dan MySQL.\nMemahami Git dan pengujian otomatis."],
            ['operator-produksi', 'Operator Produksi', 'PRD', 'PRD-OPR', 'probation', 'Karawang',
                "Menjalankan mesin produksi sesuai SOP.\nMencatat hasil produksi harian.",
                "Minimal SMA/SMK.\nBersedia bekerja shift.\nTeliti dan disiplin."],
        ];

        foreach ($vacancies as [$slug, $title, $dept, $pos, $status, $location, $description, $requirements]) {
            JobVacancy::query()->firstOrCreate(['slug' => $slug], [
                'title' => $title,
                'department_id' => Department::query()->where('code', $dept)->value('id'),
                'position_id' => Position::query()->where('code', $pos)->value('id'),
                'employment_status' => $status,
                'location' => $location,
                'description' => $description,
                'requirements' => $requirements,
                'quota' => 2,
                'deadline' => $deadline,
                'status' => 'published',
                'published_at' => now(),
            ]);
        }

        $vacancy = JobVacancy::query()->where('slug', 'programmer-laravel')->first();

        foreach ([['Rizky Pratama', 'rizky.demo@contoh.com'], ['Nadia Putri', 'nadia.demo@contoh.com']] as [$name, $email]) {
            $applicant = Applicant::query()->firstOrCreate(['email' => $email], [
                'full_name' => $name, 'phone' => '0812'.random_int(10000000, 99999999), 'last_education' => 'S1 Informatika',
            ]);

            // CV ditulis ulang setiap seed agar file placeholder lama (PDF tanpa halaman, dianggap rusak oleh browser) ikut terganti.
            $path = 'cv/demo-'.$applicant->id.'.pdf';
            Storage::disk(RecruitmentService::CV_DISK)->put($path, $this->demoCv($applicant));

            if (JobApplication::query()->where('applicant_id', $applicant->id)->exists()) {
                continue;
            }

            $application = JobApplication::create([
                'job_vacancy_id' => $vacancy->id,
                'applicant_id' => $applicant->id,
                'cv_path' => $path,
                'cover_letter' => 'Saya tertarik bergabung sebagai programmer.',
                'consent_at' => now(),
                'status' => ApplicationStatus::Applied,
            ]);

            ApplicationStatusHistory::create(['job_application_id' => $application->id, 'to_status' => 'applied', 'note' => 'Data demo.']);
        }
    }

    /** CV contoh satu halaman yang valid, agar pratinjau PDF di aplikasi bisa menampilkannya. */
    private function demoCv(Applicant $applicant): string
    {
        $name = e($applicant->full_name);
        $email = e($applicant->email);
        $phone = e($applicant->phone);
        $education = e($applicant->last_education);

        return Pdf::loadHTML(<<<HTML
            <style>body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#1f2937}h1{font-size:20px;margin:0}h2{font-size:13px;border-bottom:1px solid #cbd5e1;padding-bottom:3px;margin-top:18px}</style>
            <h1>{$name}</h1>
            <p>{$email} &middot; {$phone} &middot; Bandung</p>
            <p><em>CV contoh untuk data demo HUMANIX.</em></p>
            <h2>Ringkasan</h2>
            <p>Programmer web dengan pengalaman membangun aplikasi internal berbasis Laravel, React, dan MySQL.</p>
            <h2>Pengalaman</h2>
            <p><strong>Programmer</strong> &mdash; PT Contoh Digital (2023&ndash;sekarang)<br>Mengembangkan modul HR dan pelaporan, menulis tes otomatis.</p>
            <h2>Pendidikan</h2>
            <p>{$education}</p>
            <h2>Keahlian</h2>
            <p>PHP 8, Laravel, React, TypeScript, MySQL, Git</p>
            HTML)->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->output();
    }
}
