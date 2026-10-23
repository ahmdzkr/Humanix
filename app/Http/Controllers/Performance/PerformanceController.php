<?php

namespace App\Http\Controllers\Performance;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\DevelopmentPlan;
use App\Models\PerformanceIndicator;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewLog;
use App\Services\PerformanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceController extends Controller
{
    public function __construct(private readonly PerformanceService $performance) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $isHr = $this->performance->isHr($user);
        $employee = $user->employee;

        $present = fn (PerformanceReview $r) => [
            'id' => $r->id,
            'period' => $r->period->name,
            'period_status' => $r->period->status,
            'employee_name' => $r->employee->full_name,
            'nik' => $r->employee->nik,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            // Nilai akhir hanya ditampilkan ke karyawan setelah dipublikasikan.
            'final_score' => $r->employee_id === $employee?->id && $r->status !== ReviewStatus::Published ? null : $r->final_score,
            'rating' => $r->employee_id === $employee?->id && $r->status !== ReviewStatus::Published ? null : $r->rating,
        ];

        $mine = $employee
            ? PerformanceReview::query()->where('employee_id', $employee->id)->with(['period', 'employee'])->latest('id')->get()->map($present)
            : collect();

        $team = PerformanceReview::query()
            ->where(fn ($q) => $q->where('reviewer_id', $user->id)
                ->when($isHr, fn ($w) => $w->orWhereNull('reviewer_id')))
            ->when($employee, fn ($q) => $q->where('employee_id', '!=', $employee->id))
            ->whereHas('period', fn ($q) => $q->where('status', 'active'))
            ->with(['period', 'employee'])
            ->orderByRaw("FIELD(status, 'self_assessed', 'returned', 'draft', 'manager_reviewed', 'final', 'published')")
            ->limit(100)
            ->get()
            ->map($present);

        return Inertia::render('performance/index', [
            'myReviews' => $mine,
            'teamReviews' => $team,
            'periods' => $isHr ? PerformancePeriod::query()->withCount('reviews')->latest('start_date')->get()->map(fn (PerformancePeriod $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'start_date' => $p->start_date->toDateString(),
                'end_date' => $p->end_date->toDateString(),
                'status' => $p->status,
                'status_label' => PerformancePeriod::STATUS_LABELS[$p->status],
                'reviews_count' => $p->reviews_count,
            ]) : [],
            'isHr' => $isHr,
        ]);
    }

    public function storePeriod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:performance_periods,name'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'self_assessment_enabled' => ['boolean'],
        ], [], ['name' => 'nama periode', 'start_date' => 'tanggal mulai', 'end_date' => 'tanggal akhir']);

        $period = PerformancePeriod::create([...$data, 'status' => 'draft', 'created_by' => $request->user()->id]);

        return redirect()->route('performance.periods.show', $period)->with('success', 'Periode dibuat. Tambahkan indikator hingga total bobot 100%, lalu aktifkan.');
    }

    public function showPeriod(Request $request, PerformancePeriod $period): Response
    {
        $status = ReviewStatus::tryFrom((string) $request->query('status'))?->value ?? '';

        return Inertia::render('performance/period', [
            'period' => [
                'id' => $period->id,
                'name' => $period->name,
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'self_assessment_enabled' => $period->self_assessment_enabled,
                'status' => $period->status,
                'status_label' => PerformancePeriod::STATUS_LABELS[$period->status],
                'total_weight' => $period->totalWeight(),
            ],
            'indicators' => $period->indicators()->get()->map(fn (PerformanceIndicator $i) => $i->only(['id', 'name', 'description', 'weight', 'scale_min', 'scale_max', 'target'])),
            'reviews' => $period->reviews()
                ->with(['employee:id,full_name,nik,department_id', 'employee.department:id,name', 'reviewer:id,name'])
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->get()
                ->sortBy('employee.full_name')
                ->values()
                ->map(fn (PerformanceReview $r) => [
                    'id' => $r->id,
                    'employee_name' => $r->employee->full_name,
                    'nik' => $r->employee->nik,
                    'department' => $r->employee->department?->name,
                    'reviewer' => $r->reviewer?->name ?? 'HRD',
                    'status' => $r->status->value,
                    'status_label' => $r->status->label(),
                    'final_score' => $r->final_score,
                    'rating' => $r->rating,
                ]),
            'counts' => $period->reviews()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'statusOptions' => ReviewStatus::options(),
            'filters' => ['status' => $status],
        ]);
    }

    public function storeIndicator(Request $request, PerformancePeriod $period): RedirectResponse
    {
        $this->performance->assertDraft($period);
        $period->indicators()->create($this->validateIndicator($request));

        return back()->with('success', 'Indikator ditambahkan.');
    }

    public function updateIndicator(Request $request, PerformancePeriod $period, PerformanceIndicator $indicator): RedirectResponse
    {
        abort_unless($indicator->performance_period_id === $period->id, 404);
        $this->performance->assertDraft($period);
        $indicator->update($this->validateIndicator($request));

        return back()->with('success', 'Indikator diperbarui.');
    }

    public function destroyIndicator(PerformancePeriod $period, PerformanceIndicator $indicator): RedirectResponse
    {
        abort_unless($indicator->performance_period_id === $period->id, 404);
        $this->performance->assertDraft($period);
        $indicator->delete();

        return back()->with('success', 'Indikator dihapus.');
    }

    public function activate(PerformancePeriod $period): RedirectResponse
    {
        $count = $this->performance->activate($period);

        return back()->with('success', "Periode aktif. {$count} lembar penilaian dibuat.");
    }

    public function close(PerformancePeriod $period): RedirectResponse
    {
        $this->performance->closePeriod($period);

        return back()->with('success', 'Periode ditutup.');
    }

    public function publishAll(Request $request, PerformancePeriod $period): RedirectResponse
    {
        $count = 0;

        foreach ($period->reviews()->where('status', ReviewStatus::Final->value)->get() as $review) {
            if (! $this->performance->isOwner($request->user(), $review)) {
                $this->performance->publish($review, $request->user());
                $count++;
            }
        }

        return back()->with('success', "{$count} hasil penilaian dipublikasikan.");
    }

    public function showReview(Request $request, PerformanceReview $review): Response
    {
        $user = $request->user();
        abort_unless($this->performance->canView($user, $review), 403);

        $review->load(['period', 'employee.department', 'employee.position', 'reviewer:id,name', 'items.indicator', 'selfAssessment', 'developmentPlans.creator:id,name', 'logs.user:id,name']);

        $isOwner = $this->performance->isOwner($user, $review);
        $isHr = $this->performance->isHr($user) && ! $isOwner;
        // Hasil (nilai atasan, nilai akhir, komentar) disembunyikan dari karyawan sebelum dipublikasikan.
        $showResult = ! $isOwner || $review->status === ReviewStatus::Published;
        $status = $review->status;

        return Inertia::render('performance/review', [
            'review' => [
                'id' => $review->id,
                'version' => $review->version,
                'status' => $status->value,
                'status_label' => $status->label(),
                'period' => $review->period->only(['id', 'name', 'status']),
                'self_assessment_enabled' => $review->period->self_assessment_enabled,
                'employee' => [
                    'full_name' => $review->employee->full_name,
                    'nik' => $review->employee->nik,
                    'position' => $review->employee->position?->name,
                    'department' => $review->employee->department?->name,
                ],
                'reviewer' => $review->reviewer?->name ?? 'HRD',
                'items' => $review->items->sortBy(fn ($i) => [$i->indicator->sort_order, $i->indicator->id])->values()->map(fn ($i) => [
                    'indicator_id' => $i->indicator->id,
                    'name' => $i->indicator->name,
                    'description' => $i->indicator->description,
                    'weight' => $i->indicator->weight,
                    'scale_min' => $i->indicator->scale_min,
                    'scale_max' => $i->indicator->scale_max,
                    'target' => $i->indicator->target,
                    'self_score' => $i->self_score,
                    'self_comment' => $i->self_comment,
                    'manager_score' => $showResult ? $i->manager_score : null,
                    'manager_comment' => $showResult ? $i->manager_comment : null,
                ]),
                'self_assessment' => $review->selfAssessment?->only(['achievements', 'challenges']),
                'manager_comment' => $showResult ? $review->manager_comment : null,
                'hr_comment' => $showResult ? $review->hr_comment : null,
                'final_score' => $showResult ? $review->final_score : null,
                'rating' => $showResult ? $review->rating : null,
                'result_hidden' => ! $showResult,
                'development_plans' => $showResult ? $review->developmentPlans->map(fn (DevelopmentPlan $p) => [
                    'id' => $p->id,
                    'type' => DevelopmentPlan::TYPES[$p->type] ?? $p->type,
                    'description' => $p->description,
                    'target_date' => $p->target_date?->toDateString(),
                    'status' => DevelopmentPlan::STATUSES[$p->status] ?? $p->status,
                    'created_by' => $p->creator?->name,
                ]) : [],
                'logs' => $isOwner ? [] : $review->logs->map(fn (PerformanceReviewLog $l) => [
                    'action' => PerformanceReviewLog::ACTION_LABELS[$l->action] ?? $l->action,
                    'note' => $l->note,
                    'actor' => $l->user?->name ?? 'Sistem',
                    'at' => $l->created_at?->toIso8601String(),
                ]),
            ],
            'can' => [
                'self' => $isOwner && $status === ReviewStatus::Draft && $review->period->status === 'active',
                'manager' => $this->performance->canManagerReview($user, $review) && $status->awaitsManager(),
                'hrCheck' => $isHr && $status === ReviewStatus::ManagerReviewed,
                'publish' => $isHr && $status === ReviewStatus::Final,
                'plan' => in_array($status, [ReviewStatus::Final, ReviewStatus::Published], true)
                    && ($isHr || ($review->reviewer_id === $user->id && ! $isOwner)),
            ],
            'planTypes' => DevelopmentPlan::TYPES,
        ]);
    }

    public function submitSelf(Request $request, PerformanceReview $review): RedirectResponse
    {
        // Otorisasi sebelum validasi: pengguna lain mendapat 403, bukan pesan validasi.
        abort_unless($this->performance->isOwner($request->user(), $review), 403);

        $data = $request->validate([
            'scores' => ['required', 'array'],
            'comments' => ['nullable', 'array'],
            'comments.*' => ['nullable', 'string', 'max:500'],
            'achievements' => ['required', 'string', 'max:3000'],
            'challenges' => ['nullable', 'string', 'max:3000'],
            'version' => ['required', 'integer'],
        ], [], ['achievements' => 'pencapaian', 'challenges' => 'kendala']);

        $this->performance->submitSelf($review, $request->user(), $data['scores'], $data['comments'] ?? [], $data['achievements'], $data['challenges'] ?? null, (int) $data['version']);

        return back()->with('success', 'Evaluasi diri terkirim ke atasan.');
    }

    public function submitManager(Request $request, PerformanceReview $review): RedirectResponse
    {
        abort_unless($this->performance->canManagerReview($request->user(), $review), 403, 'Anda tidak berwenang menilai karyawan ini.');

        $data = $request->validate([
            'scores' => ['required', 'array'],
            'comments' => ['nullable', 'array'],
            'comments.*' => ['nullable', 'string', 'max:500'],
            'manager_comment' => ['nullable', 'string', 'max:3000'],
            'version' => ['required', 'integer'],
        ]);

        $this->performance->submitManager($review, $request->user(), $data['scores'], $data['comments'] ?? [], $data['manager_comment'] ?? null, (int) $data['version']);

        return back()->with('success', 'Penilaian atasan terkirim ke HRD.');
    }

    public function hrDecision(Request $request, PerformanceReview $review): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['finalize', 'return'])],
            'note' => [Rule::requiredIf($request->input('decision') === 'return'), 'nullable', 'string', 'max:500'],
            'version' => ['required', 'integer'],
        ], ['note.required' => 'Catatan wajib diisi saat mengembalikan ke atasan.'], ['note' => 'catatan']);

        if ($data['decision'] === 'return') {
            $this->performance->returnToManager($review, $request->user(), $data['note'], (int) $data['version']);

            return back()->with('success', 'Penilaian dikembalikan ke atasan untuk revisi.');
        }

        $review = $this->performance->finalize($review, $request->user(), $data['note'] ?? null, (int) $data['version']);

        return back()->with('success', "Penilaian difinalkan: {$review->final_score} ({$review->rating}).");
    }

    public function publish(Request $request, PerformanceReview $review): RedirectResponse
    {
        $this->performance->publish($review, $request->user());

        return back()->with('success', 'Hasil penilaian dipublikasikan ke karyawan.');
    }

    public function addPlan(Request $request, PerformanceReview $review): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->performance->isHr($user) || ($review->reviewer_id === $user->id && ! $this->performance->isOwner($user, $review)), 403);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(DevelopmentPlan::TYPES))],
            'description' => ['required', 'string', 'max:2000'],
            'target_date' => ['nullable', 'date'],
        ], [], ['type' => 'jenis', 'description' => 'deskripsi', 'target_date' => 'target']);

        $this->performance->addPlan($review, $request->user(), $data);

        return back()->with('success', 'Rencana pengembangan ditambahkan.');
    }

    /** @return array<string, mixed> */
    private function validateIndicator(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'weight' => ['required', 'numeric', 'gt:0', 'max:100', 'decimal:0,2'],
            'scale_min' => ['required', 'integer', 'min:0', 'max:9'],
            'scale_max' => ['required', 'integer', 'gt:scale_min', 'max:10'],
            'target' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], ['scale_max.gt' => 'Skala maksimum harus lebih besar dari skala minimum.'], [
            'name' => 'nama indikator', 'weight' => 'bobot', 'scale_min' => 'skala minimum', 'scale_max' => 'skala maksimum',
        ]);
    }
}
