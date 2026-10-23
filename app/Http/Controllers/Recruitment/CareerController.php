<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\JobVacancy;
use App\Models\SystemSetting;
use App\Services\RecruitmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman karier publik (tanpa login).
 */
class CareerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('careers/index', [
            'company' => SystemSetting::get('company_name', config('app.name')),
            'vacancies' => JobVacancy::query()->open()->with(['department:id,name'])->orderBy('deadline')->get()
                ->map(fn (JobVacancy $v) => $this->summary($v)),
        ]);
    }

    public function show(string $slug): Response
    {
        // Tautan lama ke lowongan yang sudah ditutup (mis. kuota terpenuhi) menampilkan pesan "ditutup", bukan 404.
        $vacancy = JobVacancy::query()->where('slug', $slug)->where('status', '!=', 'draft')->with(['department:id,name', 'position:id,name'])->firstOrFail();

        return Inertia::render('careers/show', [
            'company' => SystemSetting::get('company_name', config('app.name')),
            'vacancy' => [
                ...$this->summary($vacancy),
                'position' => $vacancy->position?->name,
                'description' => $vacancy->description,
                'requirements' => $vacancy->requirements,
                'closed' => ! $vacancy->isOpen(),
            ],
        ]);
    }

    public function apply(Request $request, string $slug, RecruitmentService $recruitment): RedirectResponse
    {
        $vacancy = JobVacancy::query()->where('slug', $slug)->firstOrFail();

        // Honeypot: bot yang mengisi field tersembunyi diabaikan tanpa pesan error.
        if (filled($request->input('website'))) {
            return redirect()->route('careers.thanks', $slug);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'last_education' => ['nullable', 'string', 'max:100'],
            'cover_letter' => ['nullable', 'string', 'max:3000'],
            'cv' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'Anda perlu menyetujui pemrosesan data pribadi untuk melamar.',
            'cv.mimes' => 'CV harus berupa file PDF.',
            'cv.max' => 'Ukuran CV maksimal 5 MB.',
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, +, -, dan tanda kurung.',
        ], [
            'full_name' => 'nama lengkap', 'email' => 'email', 'phone' => 'nomor telepon',
            'address' => 'alamat', 'last_education' => 'pendidikan terakhir',
            'cover_letter' => 'surat lamaran', 'cv' => 'CV',
        ]);

        $recruitment->apply($vacancy, $data, $request->file('cv'), $request->ip());

        return redirect()->route('careers.thanks', $slug);
    }

    public function thanks(string $slug): Response
    {
        $vacancy = JobVacancy::query()->where('slug', $slug)->firstOrFail();

        return Inertia::render('careers/thanks', [
            'company' => SystemSetting::get('company_name', config('app.name')),
            'title' => $vacancy->title,
        ]);
    }

    /** @return array<string, mixed> */
    private function summary(JobVacancy $v): array
    {
        return [
            'slug' => $v->slug,
            'title' => $v->title,
            'department' => $v->department?->name,
            'employment_status' => $v->employment_status->label(),
            'location' => $v->location,
            'deadline' => $v->deadline->toDateString(),
        ];
    }
}
