<?php

namespace App\Http\Controllers\Recruitment;

use App\Enums\ApplicationStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\OnboardRequest;
use App\Models\Department;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\NotificationDelivery;
use App\Models\OnboardingRecord;
use App\Models\Position;
use App\Models\User;
use App\Services\EmployeeService;
use App\Services\OnboardingService;
use App\Services\RecruitmentService;
use App\Support\FilePreview;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecruitmentController extends Controller
{
    public function __construct(private readonly RecruitmentService $recruitment) {}

    /**
     * HRD: daftar lowongan. Interviewer (Manager): wawancara yang ditugaskan kepadanya.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tz = config('app.display_timezone');
        $canManage = $user->can('recruitment.manage');

        $myInterviews = Interview::query()
            ->where('interviewer_id', $user->id)
            ->whereIn('status', ['scheduled', 'completed'])
            ->with(['application.applicant:id,full_name', 'application.vacancy:id,title'])
            ->orderByRaw("status = 'completed'")
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get()
            ->map(fn (Interview $i) => [
                'id' => $i->id,
                'application_id' => $i->job_application_id,
                'applicant' => $i->application->applicant->full_name,
                'vacancy' => $i->application->vacancy->title,
                'scheduled_at' => $i->scheduled_at->setTimezone($tz)->toIso8601String(),
                'mode' => $i->mode,
                'location' => $i->location,
                'status' => $i->status,
            ]);

        $vacancies = $canManage
            ? JobVacancy::query()
                ->with(['department:id,name', 'position:id,name'])
                ->withCount([
                    'applications',
                    'applications as new_count' => fn ($q) => $q->where('status', ApplicationStatus::Applied->value),
                    'applications as hired_count' => fn ($q) => $q->whereIn('status', [ApplicationStatus::Accepted->value, ApplicationStatus::Hired->value]),
                ])
                ->latest('id')
                ->get()
                ->map(fn (JobVacancy $v) => [
                    ...$this->vacancyForm($v),
                    'department' => $v->department?->name,
                    'position' => $v->position?->name,
                    'status' => $v->status,
                    'status_label' => JobVacancy::STATUS_LABELS[$v->status],
                    'closed_reason' => $v->closed_reason,
                    'is_open' => $v->isOpen(),
                    'applications_count' => $v->applications_count,
                    'new_count' => $v->new_count,
                    'hired_count' => $v->hired_count,
                ])
            : [];

        return Inertia::render('recruitment/index', [
            'vacancies' => $vacancies,
            'myInterviews' => $myInterviews,
            'canManage' => $canManage,
            'options' => $canManage ? $this->vacancyOptions() : null,
        ]);
    }

    public function storeVacancy(Request $request): RedirectResponse
    {
        $data = $this->validateVacancy($request);

        JobVacancy::create([
            ...$data,
            'slug' => $this->recruitment->uniqueSlug($data['title']),
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Lowongan disimpan sebagai draft. Publikasikan agar tampil di halaman karier.');
    }

    public function updateVacancy(Request $request, JobVacancy $vacancy): RedirectResponse
    {
        abort_if($vacancy->status === 'closed', 422, 'Lowongan yang sudah ditutup tidak bisa diubah.');

        $vacancy->update($this->validateVacancy($request));

        // Kuota diturunkan sampai sama/di bawah jumlah yang sudah diterima → lowongan langsung ditutup.
        if ($vacancy->status === 'published' && $this->recruitment->closeIfQuotaFilled($vacancy)) {
            return back()->with('success', 'Lowongan diperbarui dan ditutup otomatis karena kuota sudah terpenuhi.');
        }

        return back()->with('success', 'Lowongan diperbarui.');
    }

    public function setVacancyStatus(Request $request, JobVacancy $vacancy): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['published', 'closed'])]]);
        $today = now(config('app.display_timezone'))->toDateString();

        if ($data['status'] === 'published') {
            if ($vacancy->status !== 'draft') {
                return back()->with('error', 'Hanya lowongan draft yang bisa dipublikasikan.');
            }

            if ($vacancy->deadline->toDateString() < $today) {
                return back()->with('error', 'Tenggat lowongan sudah lewat. Ubah tenggat sebelum mempublikasikan.');
            }

            $vacancy->update(['status' => 'published', 'published_at' => now()]);

            return back()->with('success', 'Lowongan dipublikasikan di halaman karier.');
        }

        if ($vacancy->status === 'closed') {
            return back()->with('error', 'Lowongan sudah ditutup.');
        }

        $vacancy->update(['status' => 'closed', 'closed_at' => now(), 'closed_reason' => 'manual']);

        return back()->with('success', 'Lowongan ditutup dan tidak lagi menerima lamaran.');
    }

    public function showVacancy(Request $request, JobVacancy $vacancy): Response
    {
        $status = ApplicationStatus::tryFrom((string) $request->query('status'))?->value ?? '';
        $search = trim((string) $request->query('search', ''));

        $applications = $vacancy->applications()
            ->with('applicant')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->whereHas('applicant', fn ($a) => $a->where('full_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (JobApplication $a) => [
                'id' => $a->id,
                'full_name' => $a->applicant->full_name,
                'email' => $a->applicant->email,
                'phone' => $a->applicant->phone,
                'last_education' => $a->applicant->last_education,
                'status' => $a->status->value,
                'status_label' => $a->status->label(),
                'applied_at' => $a->created_at?->toIso8601String(),
            ]);

        $counts = $vacancy->applications()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('recruitment/vacancy', [
            'vacancy' => [
                'id' => $vacancy->id,
                'title' => $vacancy->title,
                'slug' => $vacancy->slug,
                'status' => $vacancy->status,
                'status_label' => JobVacancy::STATUS_LABELS[$vacancy->status],
                'quota' => $vacancy->quota,
                'filled' => $this->recruitment->filledCount($vacancy),
                'closed_reason' => $vacancy->closed_reason,
                'deadline' => $vacancy->deadline->toDateString(),
                'is_open' => $vacancy->isOpen(),
            ],
            'applications' => $applications,
            'filters' => ['search' => $search, 'status' => $status],
            'counts' => $counts,
            'statusOptions' => ApplicationStatus::options(),
        ]);
    }

    public function showApplication(Request $request, JobApplication $application): Response
    {
        Gate::authorize('view', $application);

        $user = $request->user();
        $canManage = $user->can('recruitment.manage');
        $tz = config('app.display_timezone');
        $application->load(['applicant', 'vacancy.department', 'vacancy.position', 'interviews.interviewer:id,name', 'interviews.scores', 'histories.user:id,name', 'onboarding.employee:id,nik,full_name']);

        return Inertia::render('recruitment/application', [
            'application' => [
                'id' => $application->id,
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
                'applied_at' => $application->created_at?->toIso8601String(),
                'consent_at' => $application->consent_at?->toIso8601String(),
                'cover_letter' => $application->cover_letter,
                'decision_note' => $application->decision_note,
                'employee_id' => $application->employee_id,
                'applicant' => $application->applicant->only(['full_name', 'email', 'phone', 'address', 'last_education']),
                'vacancy' => [
                    'id' => $application->vacancy->id,
                    'title' => $application->vacancy->title,
                    'department_id' => $application->vacancy->department_id,
                    'department' => $application->vacancy->department?->name,
                    'position_id' => $application->vacancy->position_id,
                    'position' => $application->vacancy->position?->name,
                    'employment_status' => $application->vacancy->employment_status->value,
                ],
                'onboarding' => $application->onboarding ? [
                    'id' => $application->onboarding->id,
                    'start_date' => $application->onboarding->start_date->toDateString(),
                    'checklist' => $application->onboarding->checklist,
                    'employee' => $application->onboarding->employee?->only(['id', 'nik', 'full_name']),
                ] : null,
            ],
            'interviews' => $application->interviews
                ->filter(fn (Interview $i) => $canManage || $i->interviewer_id === $user->id)
                ->map(fn (Interview $i) => [
                    'id' => $i->id,
                    'scheduled_at' => $i->scheduled_at->setTimezone($tz)->toIso8601String(),
                    'duration_minutes' => $i->duration_minutes,
                    'mode' => $i->mode,
                    'location' => $i->location,
                    'notes' => $i->notes,
                    'interviewer' => $i->interviewer?->name,
                    'is_mine' => $i->interviewer_id === $user->id,
                    'status' => $i->status,
                    'recommendation' => $i->recommendation,
                    'recommendation_label' => $i->recommendation ? Interview::RECOMMENDATIONS[$i->recommendation] : null,
                    'summary' => $i->summary,
                    'average' => $i->averageScore(),
                    'scores' => $i->scores->map(fn ($s) => $s->only(['indicator', 'score', 'comment']))->values(),
                ])->values(),
            'histories' => $canManage ? $application->histories->map(fn ($h) => [
                'to' => ApplicationStatus::from($h->to_status)->label(),
                'note' => $h->note,
                'actor' => $h->user?->name ?? 'Pelamar',
                'at' => $h->created_at?->toIso8601String(),
            ])->values() : [],
            'indicators' => $this->recruitment->indicators(),
            'scale' => ['min' => RecruitmentService::SCORE_MIN, 'max' => RecruitmentService::SCORE_MAX],
            'recommendations' => Interview::RECOMMENDATIONS,
            'interviewers' => $canManage ? $this->interviewerOptions() : [],
            'onboardingOptions' => $canManage && $application->status === ApplicationStatus::Accepted
                ? app(EmployeeService::class)->formOptions()
                : null,
            'defaultPassword' => $canManage && $application->status === ApplicationStatus::Accepted ? OnboardingService::DEFAULT_PASSWORD : null,
            'canManage' => $canManage,
        ]);
    }

    public function cv(JobApplication $application): StreamedResponse
    {
        Gate::authorize('view', $application);
        abort_unless(Storage::disk(RecruitmentService::CV_DISK)->exists($application->cv_path), 404);

        $name = 'CV-'.str($application->applicant->full_name)->slug().'.pdf';

        return FilePreview::stored(RecruitmentService::CV_DISK, $application->cv_path, $name);
    }

    public function screen(Request $request, JobApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['pass', 'reject'])],
            'note' => [Rule::requiredIf($request->input('decision') === 'reject'), 'nullable', 'string', 'max:255'],
        ], ['note.required' => 'Catatan wajib diisi saat menolak.'], ['note' => 'catatan']);

        $this->recruitment->screen($application, $data['decision'] === 'pass', $data['note'] ?? null);

        return back()->with('success', ($data['decision'] === 'pass' ? 'Pelamar lolos seleksi administrasi.' : 'Pelamar ditolak.')
            .$this->emailNote($this->recruitment->lastApplicantEmail()));
    }

    public function scheduleInterview(Request $request, JobApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'scheduled_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'mode' => ['required', Rule::in(['online', 'offline'])],
            'location' => ['required', 'string', 'max:255'],
            'interviewer_id' => ['required', 'integer', Rule::in(array_column($this->interviewerOptions(), 'id'))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['interviewer_id.in' => 'Interviewer harus pengguna aktif dengan role HRD atau Manager.'], [
            'scheduled_at' => 'jadwal', 'duration_minutes' => 'durasi', 'mode' => 'jenis wawancara',
            'location' => 'lokasi/tautan', 'interviewer_id' => 'interviewer', 'notes' => 'catatan',
        ]);

        $scheduled = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['scheduled_at'], config('app.display_timezone'));

        if ($scheduled->isPast()) {
            return back()->withErrors(['scheduled_at' => 'Jadwal wawancara harus di masa depan.']);
        }

        $this->recruitment->scheduleInterview($application, [...$data, 'scheduled_at' => $scheduled]);

        return back()->with('success', 'Jadwal wawancara ditetapkan.'.$this->emailNote($this->recruitment->lastApplicantEmail()));
    }

    public function cancelInterview(Interview $interview): RedirectResponse
    {
        $this->recruitment->cancelInterview($interview);

        return back()->with('success', 'Wawancara dibatalkan.'.$this->emailNote($this->recruitment->lastApplicantEmail()));
    }

    public function scoreInterview(Request $request, Interview $interview): RedirectResponse
    {
        abort_unless($interview->interviewer_id === $request->user()->id, 403);

        $data = $request->validate([
            'scores' => ['required', 'array'],
            'comments' => ['nullable', 'array'],
            'comments.*' => ['nullable', 'string', 'max:255'],
            'recommendation' => ['required', Rule::in(array_keys(Interview::RECOMMENDATIONS))],
            'summary' => ['nullable', 'string', 'max:2000'],
        ], [], ['recommendation' => 'rekomendasi', 'summary' => 'ringkasan']);

        $this->recruitment->submitScores($interview, $request->user(), $data['scores'], $data['comments'] ?? [], $data['recommendation'], $data['summary'] ?? null);

        return back()->with('success', 'Penilaian wawancara disimpan.');
    }

    public function decide(Request $request, JobApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'reject'])],
            'note' => [Rule::requiredIf($request->input('decision') === 'reject'), 'nullable', 'string', 'max:255'],
        ], ['note.required' => 'Catatan wajib diisi saat menolak.'], ['note' => 'catatan']);

        $application = $this->recruitment->decide($application, $data['decision'] === 'accept', $data['note'] ?? null);
        $closed = $data['decision'] === 'accept' && $application->vacancy->fresh()->closed_reason === 'quota';

        return back()->with('success', ($data['decision'] === 'accept' ? 'Pelamar diterima. Lanjutkan ke onboarding.' : 'Pelamar ditolak.')
            .$this->emailNote($this->recruitment->lastApplicantEmail())
            .($closed ? ' Kuota lowongan sudah terpenuhi, lowongan ditutup otomatis.' : ''));
    }

    public function onboard(OnboardRequest $request, JobApplication $application, OnboardingService $onboarding): RedirectResponse
    {
        $employee = $onboarding->onboard($application, $request->validated(), $request->user());

        return redirect()->route('employees.show', $employee)
            ->with('success', "{$employee->full_name} resmi menjadi karyawan dan akunnya sudah dibuat.".$this->emailNote($onboarding->accountEmail(), 'Info login'));
    }

    public function toggleChecklist(Request $request, OnboardingRecord $onboarding, OnboardingService $service): RedirectResponse
    {
        $data = $request->validate(['index' => ['required', 'integer', 'min:0'], 'done' => ['required', 'boolean']]);
        $service->toggleChecklist($onboarding, (int) $data['index'], (bool) $data['done']);

        return back();
    }

    /** @return array<string, mixed> */
    private function validateVacancy(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'position_id' => ['required', 'integer', Rule::exists('positions', 'id')->where('is_active', true)],
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'location' => ['nullable', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'requirements' => ['required', 'string', 'max:5000'],
            'quota' => ['required', 'integer', 'min:1', 'max:500'],
            'deadline' => ['required', 'date'],
        ], [], [
            'title' => 'judul', 'department_id' => 'departemen', 'position_id' => 'jabatan',
            'employment_status' => 'status kepegawaian', 'location' => 'lokasi', 'description' => 'deskripsi',
            'requirements' => 'kualifikasi', 'quota' => 'kuota', 'deadline' => 'tenggat',
        ]);

        $position = Position::query()->find($data['position_id']);

        if ($position?->department_id !== null && (int) $position->department_id !== (int) $data['department_id']) {
            throw ValidationException::withMessages(['position_id' => 'Jabatan tidak termasuk dalam departemen yang dipilih.']);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function vacancyForm(JobVacancy $v): array
    {
        return [
            'id' => $v->id,
            'slug' => $v->slug,
            'title' => $v->title,
            'department_id' => $v->department_id,
            'position_id' => $v->position_id,
            'employment_status' => $v->employment_status->value,
            'location' => $v->location,
            'description' => $v->description,
            'requirements' => $v->requirements,
            'quota' => $v->quota,
            'deadline' => $v->deadline->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    private function vacancyOptions(): array
    {
        return [
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
            'positions' => Position::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'department_id']),
            'employmentStatuses' => EmploymentStatus::options(),
        ];
    }

    /** Kalimat tambahan untuk pesan sukses: ke mana email diantrekan, atau kenapa tidak dikirim. */
    private function emailNote(?NotificationDelivery $delivery, string $what = 'Email'): string
    {
        return match ($delivery?->status) {
            null => '',
            'skipped' => " {$what} tidak dikirim karena SMTP belum diatur.",
            default => " {$what} dikirim ke {$delivery->recipientLabel()}.",
        };
    }

    /** @return list<array{id: int, name: string}> */
    private function interviewerOptions(): array
    {
        return User::query()
            ->role([Role::Hrd->value, Role::Manager->value])
            ->where(fn ($q) => $q->whereDoesntHave('employee')->orWhereHas('employee', fn ($e) => $e->where('is_active', true)))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }
}
