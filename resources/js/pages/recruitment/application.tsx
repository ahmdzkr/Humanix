import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CalendarPlus,
    Check,
    CheckCircle2,
    Circle,
    FileText,
    KeyRound,
    Star,
    UserPlus,
    X,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { FilePreviewButton } from '@/components/file-preview';
import { FormField, selectClassName } from '@/components/form-field';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { formatJam, formatTanggal } from '@/lib/format';
import type { ApplicationStatusValue } from '@/lib/recruitment';
import { applicationTone } from '@/lib/recruitment';
import { show as employeeShow } from '@/routes/employees';
import applications from '@/routes/recruitment/applications';
import interviewRoutes from '@/routes/recruitment/interviews';
import onboardingRoutes from '@/routes/recruitment/onboarding';
import vacancies from '@/routes/recruitment/vacancies';

type InterviewView = {
    id: number;
    scheduled_at: string;
    duration_minutes: number;
    mode: 'online' | 'offline';
    location: string;
    notes: string | null;
    interviewer: string | null;
    is_mine: boolean;
    status: 'scheduled' | 'completed' | 'cancelled';
    recommendation: string | null;
    recommendation_label: string | null;
    summary: string | null;
    average: number | null;
    scores: { indicator: string; score: number; comment: string | null }[];
};

type Option = { value: string; label: string };

type Props = {
    application: {
        id: number;
        status: ApplicationStatusValue;
        status_label: string;
        applied_at: string | null;
        consent_at: string | null;
        cover_letter: string | null;
        decision_note: string | null;
        employee_id: number | null;
        applicant: {
            full_name: string;
            email: string;
            phone: string;
            address: string | null;
            last_education: string | null;
        };
        vacancy: {
            id: number;
            title: string;
            department_id: number;
            department: string | null;
            position_id: number;
            position: string | null;
            employment_status: string;
        };
        onboarding: {
            id: number;
            start_date: string;
            checklist: { item: string; done: boolean }[];
            employee: { id: number; nik: string; full_name: string } | null;
        } | null;
    };
    interviews: InterviewView[];
    histories: {
        to: string;
        note: string | null;
        actor: string;
        at: string | null;
    }[];
    indicators: string[];
    scale: { min: number; max: number };
    recommendations: Record<string, string>;
    interviewers: { id: number; name: string }[];
    onboardingOptions: {
        departments: { id: number; name: string }[];
        positions: { id: number; name: string; department_id: number | null }[];
        managers: { id: number; label: string }[];
        employmentStatuses: Option[];
        workSchedules: { id: number; label: string }[];
    } | null;
    defaultPassword: string | null;
    canManage: boolean;
};

const textarea =
    'w-full rounded-control border border-input bg-surface px-3 py-2 text-sm focus:border-primary-bright focus:ring-2 focus:ring-primary-bright/20 focus:outline-none';

function Card({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="rounded-card border border-border bg-surface p-5">
            <h2 className="mb-4 font-semibold text-navy">{title}</h2>
            {children}
        </section>
    );
}

function DecisionButtons({
    url,
    approveLabel,
    approveValue,
    rejectValue,
}: {
    url: string;
    approveLabel: string;
    approveValue: string;
    rejectValue: string;
}) {
    const form = useForm({ decision: '', note: '' });
    const errors = form.errors as Record<string, string | undefined>;

    const send = (decision: string) => {
        form.transform((d) => ({ ...d, decision }));
        form.post(url, { preserveScroll: true });
    };

    return (
        <div className="grid gap-3">
            <div className="grid gap-2">
                <Label htmlFor={`note-${url}`}>
                    Catatan{' '}
                    <span className="font-normal text-text-muted">
                        (wajib jika menolak)
                    </span>
                </Label>
                <textarea
                    id={`note-${url}`}
                    rows={2}
                    maxLength={255}
                    className={textarea}
                    value={form.data.note}
                    onChange={(e) => form.setData('note', e.target.value)}
                />
                <InputError message={form.errors.note ?? errors.status} />
            </div>
            <div className="flex gap-2">
                <Button
                    className="flex-1 rounded-control bg-success hover:bg-success/90"
                    disabled={form.processing}
                    onClick={() => send(approveValue)}
                >
                    {form.processing ? (
                        <Spinner />
                    ) : (
                        <Check className="size-4" />
                    )}
                    {approveLabel}
                </Button>
                <Button
                    variant="outline"
                    className="flex-1 rounded-control text-danger hover:text-danger"
                    disabled={form.processing}
                    onClick={() => send(rejectValue)}
                >
                    <X className="size-4" />
                    Tolak
                </Button>
            </div>
        </div>
    );
}

function ScoreForm({
    interview,
    indicators,
    scale,
    recommendations,
}: {
    interview: InterviewView;
    indicators: string[];
    scale: { min: number; max: number };
    recommendations: Record<string, string>;
}) {
    const existing = Object.fromEntries(
        interview.scores.map((s) => [s.indicator, s]),
    );
    const form = useForm({
        scores: Object.fromEntries(
            indicators.map((i) => [
                i,
                existing[i]?.score ? String(existing[i].score) : '',
            ]),
        ),
        comments: Object.fromEntries(
            indicators.map((i) => [i, existing[i]?.comment ?? '']),
        ),
        recommendation: interview.recommendation ?? '',
        summary: interview.summary ?? '',
    });
    const errors = form.errors as Record<string, string | undefined>;
    const range = Array.from(
        { length: scale.max - scale.min + 1 },
        (_, i) => scale.min + i,
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(interviewRoutes.score.url(interview.id), {
            preserveScroll: true,
        });
    };

    return (
        <form
            onSubmit={submit}
            className="mt-4 grid gap-3 rounded-control border border-primary/20 bg-primary/5 p-4"
        >
            <p className="text-sm font-semibold text-navy">
                Penilaian Anda (skala {scale.min}–{scale.max})
            </p>
            {indicators.map((indicator, i) => (
                <div key={indicator} className="grid gap-1">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <span className="text-sm">{indicator}</span>
                        <div
                            role="radiogroup"
                            aria-label={indicator}
                            className="flex gap-1"
                        >
                            {range.map((n) => (
                                <button
                                    key={n}
                                    type="button"
                                    role="radio"
                                    aria-checked={
                                        form.data.scores[indicator] ===
                                        String(n)
                                    }
                                    onClick={() =>
                                        form.setData('scores', {
                                            ...form.data.scores,
                                            [indicator]: String(n),
                                        })
                                    }
                                    className={
                                        form.data.scores[indicator] ===
                                        String(n)
                                            ? 'size-8 rounded-md bg-primary text-sm font-semibold text-white'
                                            : 'size-8 rounded-md border border-border bg-surface text-sm hover:bg-accent'
                                    }
                                >
                                    {n}
                                </button>
                            ))}
                        </div>
                    </div>
                    <InputError message={errors[`scores.${i}`]} />
                </div>
            ))}
            <FormField
                id={`rec-${interview.id}`}
                label="Rekomendasi"
                required
                error={form.errors.recommendation}
            >
                <select
                    id={`rec-${interview.id}`}
                    value={form.data.recommendation}
                    onChange={(e) =>
                        form.setData('recommendation', e.target.value)
                    }
                    className={selectClassName}
                >
                    <option value="">— Pilih —</option>
                    {Object.entries(recommendations).map(([v, l]) => (
                        <option key={v} value={v}>
                            {l}
                        </option>
                    ))}
                </select>
            </FormField>
            <FormField
                id={`sum-${interview.id}`}
                label="Ringkasan"
                error={form.errors.summary}
            >
                <textarea
                    id={`sum-${interview.id}`}
                    rows={3}
                    className={textarea}
                    value={form.data.summary}
                    onChange={(e) => form.setData('summary', e.target.value)}
                />
            </FormField>
            <InputError message={errors.status} />
            <Button
                type="submit"
                className="rounded-control"
                disabled={form.processing}
            >
                {form.processing && <Spinner />}
                Simpan penilaian
            </Button>
        </form>
    );
}

export default function ApplicationShow(props: Props) {
    const {
        application: app,
        interviews,
        histories,
        indicators,
        scale,
        recommendations,
        interviewers,
        onboardingOptions,
        defaultPassword,
        canManage,
    } = props;
    const [scheduleOpen, setScheduleOpen] = useState(false);
    const decided = ['accepted', 'hired', 'rejected'].includes(app.status);

    const schedule = useForm({
        scheduled_at: '',
        duration_minutes: '60',
        mode: 'offline',
        location: '',
        interviewer_id: '',
        notes: '',
    });

    const onboard = useForm({
        nik: '',
        full_name: app.applicant.full_name,
        email: app.applicant.email,
        phone: app.applicant.phone,
        address: app.applicant.address ?? '',
        department_id: String(app.vacancy.department_id),
        position_id: String(app.vacancy.position_id),
        manager_id: '',
        employment_status: app.vacancy.employment_status,
        join_date: '',
        contract_end_date: '',
        work_schedule_id:
            onboardingOptions?.workSchedules.length === 1
                ? String(onboardingOptions.workSchedules[0].id)
                : '',
    });
    const onboardErrors = onboard.errors as Record<string, string | undefined>;

    const positionOptions = useMemo(
        () =>
            (onboardingOptions?.positions ?? []).filter(
                (p) =>
                    p.department_id === null ||
                    String(p.department_id) === onboard.data.department_id,
            ),
        [onboardingOptions, onboard.data.department_id],
    );

    const submitSchedule = (event: FormEvent) => {
        event.preventDefault();
        schedule.post(applications.interview.url(app.id), {
            preserveScroll: true,
            onSuccess: () => {
                setScheduleOpen(false);
                schedule.reset();
            },
        });
    };

    const field = (
        name: keyof typeof onboard.data,
        label: string,
        input: ReactNode,
        required = false,
    ) => (
        <FormField
            id={`ob-${name}`}
            label={label}
            required={required}
            error={onboardErrors[name]}
        >
            {input}
        </FormField>
    );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Rekruitmen', href: '/rekruitmen' },
                ...(canManage
                    ? [
                          {
                              title: app.vacancy.title,
                              href: vacancies.show.url(app.vacancy.id),
                          },
                      ]
                    : []),
                { title: app.applicant.full_name, href: '#' },
            ]}
        >
            <Head title={`Lamaran ${app.applicant.full_name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-bold text-navy">
                                {app.applicant.full_name}
                            </h1>
                            <StatusBadge tone={applicationTone[app.status]}>
                                {app.status_label}
                            </StatusBadge>
                        </div>
                        <p className="mt-1 text-sm text-text-muted">
                            Melamar {app.vacancy.title} ·{' '}
                            {app.vacancy.department}
                        </p>
                    </div>
                    <FilePreviewButton
                        url={applications.cv.url(app.id)}
                        kind="pdf"
                        title={`CV ${app.applicant.full_name}`}
                        icon={FileText}
                        variant="outline"
                        className="rounded-control"
                    >
                        Lihat CV
                    </FilePreviewButton>
                </div>

                <div className="grid gap-6 xl:grid-cols-3">
                    <div className="flex flex-col gap-6 xl:col-span-2">
                        <Card title="Data Pelamar">
                            <dl className="grid gap-4 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-xs text-text-muted">
                                        Email
                                    </dt>
                                    <dd>{app.applicant.email}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-text-muted">
                                        Telepon
                                    </dt>
                                    <dd>{app.applicant.phone}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-text-muted">
                                        Pendidikan
                                    </dt>
                                    <dd>
                                        {app.applicant.last_education ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-text-muted">
                                        Melamar
                                    </dt>
                                    <dd>
                                        {app.applied_at
                                            ? `${formatTanggal(app.applied_at)}, ${formatJam(app.applied_at)}`
                                            : '—'}
                                    </dd>
                                </div>
                                <div className="sm:col-span-2">
                                    <dt className="text-xs text-text-muted">
                                        Alamat
                                    </dt>
                                    <dd>{app.applicant.address ?? '—'}</dd>
                                </div>
                                {app.cover_letter && (
                                    <div className="sm:col-span-2">
                                        <dt className="text-xs text-text-muted">
                                            Surat lamaran
                                        </dt>
                                        <dd className="whitespace-pre-line">
                                            {app.cover_letter}
                                        </dd>
                                    </div>
                                )}
                                <div className="text-xs text-text-muted sm:col-span-2">
                                    Persetujuan pemrosesan data diberikan{' '}
                                    {app.consent_at
                                        ? formatTanggal(app.consent_at)
                                        : '—'}
                                    .
                                </div>
                            </dl>
                        </Card>

                        <Card title="Wawancara">
                            {interviews.length === 0 ? (
                                <p className="text-sm text-text-muted">
                                    Belum ada wawancara.
                                </p>
                            ) : (
                                <ul className="space-y-4">
                                    {interviews.map((i) => (
                                        <li
                                            key={i.id}
                                            className="rounded-control border border-border p-4"
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-2">
                                                <div>
                                                    <p className="font-semibold text-navy">
                                                        {formatTanggal(
                                                            i.scheduled_at,
                                                        )}
                                                        ,{' '}
                                                        {formatJam(
                                                            i.scheduled_at,
                                                        )}{' '}
                                                        · {i.duration_minutes}{' '}
                                                        menit
                                                    </p>
                                                    <p className="text-sm text-text-muted">
                                                        {i.mode === 'online'
                                                            ? 'Online'
                                                            : 'Tatap muka'}{' '}
                                                        · {i.location} ·
                                                        Interviewer:{' '}
                                                        {i.interviewer}
                                                    </p>
                                                    {i.notes && (
                                                        <p className="mt-1 text-sm whitespace-pre-line text-text-muted">
                                                            Catatan: {i.notes}
                                                        </p>
                                                    )}
                                                </div>
                                                <StatusBadge
                                                    tone={
                                                        i.status === 'completed'
                                                            ? 'success'
                                                            : i.status ===
                                                                'cancelled'
                                                              ? 'neutral'
                                                              : 'warning'
                                                    }
                                                >
                                                    {i.status === 'completed'
                                                        ? 'Sudah dinilai'
                                                        : i.status ===
                                                            'cancelled'
                                                          ? 'Dibatalkan'
                                                          : 'Terjadwal'}
                                                </StatusBadge>
                                            </div>
                                            {i.status === 'completed' && (
                                                <div className="mt-3 text-sm">
                                                    <p className="flex items-center gap-1 font-semibold">
                                                        <Star className="size-4 text-warning" />{' '}
                                                        Rata-rata {i.average} ·{' '}
                                                        {i.recommendation_label}
                                                    </p>
                                                    <ul className="mt-2 grid gap-1 sm:grid-cols-2">
                                                        {i.scores.map((s) => (
                                                            <li
                                                                key={
                                                                    s.indicator
                                                                }
                                                                className="flex justify-between gap-2 text-text-muted"
                                                            >
                                                                <span>
                                                                    {
                                                                        s.indicator
                                                                    }
                                                                </span>
                                                                <strong className="text-navy">
                                                                    {s.score}
                                                                </strong>
                                                            </li>
                                                        ))}
                                                    </ul>
                                                    {i.summary && (
                                                        <p className="mt-2 text-text-muted italic">
                                                            “{i.summary}”
                                                        </p>
                                                    )}
                                                </div>
                                            )}
                                            {i.is_mine &&
                                                i.status !== 'cancelled' &&
                                                !decided && (
                                                    <ScoreForm
                                                        interview={i}
                                                        indicators={indicators}
                                                        scale={scale}
                                                        recommendations={
                                                            recommendations
                                                        }
                                                    />
                                                )}
                                            {canManage &&
                                                i.status === 'scheduled' && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="mt-3 rounded-control"
                                                        onClick={() =>
                                                            router.post(
                                                                interviewRoutes.cancel.url(
                                                                    i.id,
                                                                ),
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Batalkan wawancara
                                                    </Button>
                                                )}
                                        </li>
                                    ))}
                                </ul>
                            )}

                            {canManage &&
                                ['shortlisted', 'interview'].includes(
                                    app.status,
                                ) && (
                                    <div className="mt-4">
                                        {!scheduleOpen ? (
                                            <Button
                                                variant="outline"
                                                className="rounded-control"
                                                onClick={() =>
                                                    setScheduleOpen(true)
                                                }
                                            >
                                                <CalendarPlus className="size-4" />
                                                Jadwalkan wawancara
                                            </Button>
                                        ) : (
                                            <form
                                                onSubmit={submitSchedule}
                                                className="grid gap-4 rounded-control border border-border p-4 sm:grid-cols-2"
                                            >
                                                <FormField
                                                    id="scheduled_at"
                                                    label="Jadwal (WIB)"
                                                    required
                                                    error={
                                                        schedule.errors
                                                            .scheduled_at
                                                    }
                                                >
                                                    <Input
                                                        id="scheduled_at"
                                                        type="datetime-local"
                                                        value={
                                                            schedule.data
                                                                .scheduled_at
                                                        }
                                                        onChange={(e) =>
                                                            schedule.setData(
                                                                'scheduled_at',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                                <FormField
                                                    id="duration_minutes"
                                                    label="Durasi (menit)"
                                                    required
                                                    error={
                                                        schedule.errors
                                                            .duration_minutes
                                                    }
                                                >
                                                    <Input
                                                        id="duration_minutes"
                                                        type="number"
                                                        min={15}
                                                        value={
                                                            schedule.data
                                                                .duration_minutes
                                                        }
                                                        onChange={(e) =>
                                                            schedule.setData(
                                                                'duration_minutes',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                                <FormField
                                                    id="mode"
                                                    label="Jenis"
                                                    required
                                                    error={schedule.errors.mode}
                                                >
                                                    <select
                                                        id="mode"
                                                        value={
                                                            schedule.data.mode
                                                        }
                                                        onChange={(e) =>
                                                            schedule.setData(
                                                                'mode',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className={
                                                            selectClassName
                                                        }
                                                    >
                                                        <option value="offline">
                                                            Tatap muka
                                                        </option>
                                                        <option value="online">
                                                            Online
                                                        </option>
                                                    </select>
                                                </FormField>
                                                <FormField
                                                    id="location"
                                                    label={
                                                        schedule.data.mode ===
                                                        'online'
                                                            ? 'Tautan meeting'
                                                            : 'Lokasi/ruang'
                                                    }
                                                    required
                                                    error={
                                                        schedule.errors.location
                                                    }
                                                >
                                                    <Input
                                                        id="location"
                                                        value={
                                                            schedule.data
                                                                .location
                                                        }
                                                        onChange={(e) =>
                                                            schedule.setData(
                                                                'location',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                                <div className="sm:col-span-2">
                                                    <FormField
                                                        id="interviewer_id"
                                                        label="Interviewer"
                                                        required
                                                        error={
                                                            schedule.errors
                                                                .interviewer_id
                                                        }
                                                    >
                                                        <select
                                                            id="interviewer_id"
                                                            value={
                                                                schedule.data
                                                                    .interviewer_id
                                                            }
                                                            onChange={(e) =>
                                                                schedule.setData(
                                                                    'interviewer_id',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className={
                                                                selectClassName
                                                            }
                                                        >
                                                            <option value="">
                                                                — Pilih
                                                                HRD/Manager —
                                                            </option>
                                                            {interviewers.map(
                                                                (u) => (
                                                                    <option
                                                                        key={
                                                                            u.id
                                                                        }
                                                                        value={
                                                                            u.id
                                                                        }
                                                                    >
                                                                        {u.name}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>
                                                    </FormField>
                                                </div>
                                                <div className="sm:col-span-2">
                                                    <FormField
                                                        id="interview-notes"
                                                        label="Catatan untuk pelamar"
                                                        error={
                                                            schedule.errors
                                                                .notes
                                                        }
                                                    >
                                                        <textarea
                                                            id="interview-notes"
                                                            rows={3}
                                                            maxLength={1000}
                                                            className={textarea}
                                                            placeholder="Mis. bawa KTP dan portofolio, tanyakan resepsionis lantai 2."
                                                            value={
                                                                schedule.data
                                                                    .notes
                                                            }
                                                            onChange={(e) =>
                                                                schedule.setData(
                                                                    'notes',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </FormField>
                                                    <p className="mt-1 text-xs text-text-muted">
                                                        Jadwal dan catatan ini
                                                        dikirim ke email{' '}
                                                        {app.applicant.email}{' '}
                                                        saat jadwal ditetapkan.
                                                    </p>
                                                </div>
                                                <InputError
                                                    message={
                                                        (
                                                            schedule.errors as Record<
                                                                string,
                                                                | string
                                                                | undefined
                                                            >
                                                        ).status
                                                    }
                                                />
                                                <div className="flex gap-2 sm:col-span-2">
                                                    <Button
                                                        type="submit"
                                                        className="rounded-control"
                                                        disabled={
                                                            schedule.processing
                                                        }
                                                    >
                                                        {schedule.processing && (
                                                            <Spinner />
                                                        )}
                                                        Tetapkan Jadwal
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        className="rounded-control"
                                                        onClick={() =>
                                                            setScheduleOpen(
                                                                false,
                                                            )
                                                        }
                                                    >
                                                        Batal
                                                    </Button>
                                                </div>
                                            </form>
                                        )}
                                    </div>
                                )}
                        </Card>

                        {canManage &&
                            app.status === 'accepted' &&
                            onboardingOptions && (
                                <Card title="Onboarding: jadikan karyawan">
                                    <p className="mb-4 text-sm text-text-muted">
                                        Membuat data karyawan dan akun login
                                        (role Karyawan) dalam satu langkah.
                                    </p>
                                    <div
                                        className="mb-4 rounded-control border border-primary/20 bg-primary/5 p-4 text-sm"
                                        data-testid="onboarding-account"
                                    >
                                        <p className="flex items-center gap-2 font-semibold text-navy">
                                            <KeyRound className="size-4" />
                                            Akun karyawan yang akan dibuat
                                        </p>
                                        <dl className="mt-2 grid gap-1 sm:grid-cols-[auto_1fr] sm:gap-x-4">
                                            <dt className="text-text-muted">
                                                Email login
                                            </dt>
                                            <dd className="font-medium break-all">
                                                {onboard.data.email || '—'}
                                            </dd>
                                            <dt className="text-text-muted">
                                                Kata sandi awal
                                            </dt>
                                            <dd className="font-mono font-medium">
                                                {defaultPassword}
                                            </dd>
                                        </dl>
                                        <p className="mt-2 text-xs text-text-muted">
                                            Info login dikirim ke email di atas
                                            saat klik "Jadikan Karyawan".
                                            Karyawan wajib mengganti kata sandi
                                            saat pertama kali masuk.
                                        </p>
                                    </div>
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            onboard.post(
                                                applications.onboard.url(
                                                    app.id,
                                                ),
                                            );
                                        }}
                                        className="grid gap-4 sm:grid-cols-2"
                                    >
                                        {field(
                                            'nik',
                                            'NIK',
                                            <Input
                                                id="ob-nik"
                                                className="uppercase"
                                                value={onboard.data.nik}
                                                onChange={(e) =>
                                                    onboard.setData(
                                                        'nik',
                                                        e.target.value,
                                                    )
                                                }
                                            />,
                                            true,
                                        )}
                                        {field(
                                            'email',
                                            'Email',
                                            <Input
                                                id="ob-email"
                                                type="email"
                                                value={onboard.data.email}
                                                onChange={(e) =>
                                                    onboard.setData(
                                                        'email',
                                                        e.target.value,
                                                    )
                                                }
                                            />,
                                            true,
                                        )}
                                        {field(
                                            'full_name',
                                            'Nama lengkap',
                                            <Input
                                                id="ob-full_name"
                                                value={onboard.data.full_name}
                                                onChange={(e) =>
                                                    onboard.setData(
                                                        'full_name',
                                                        e.target.value,
                                                    )
                                                }
                                            />,
                                            true,
                                        )}
                                        {field(
                                            'phone',
                                            'Telepon',
                                            <Input
                                                id="ob-phone"
                                                value={onboard.data.phone}
                                                onChange={(e) =>
                                                    onboard.setData(
                                                        'phone',
                                                        e.target.value,
                                                    )
                                                }
                                            />,
                                        )}
                                        {field(
                                            'department_id',
                                            'Departemen',
                                            <select
                                                id="ob-department_id"
                                                value={
                                                    onboard.data.department_id
                                                }
                                                onChange={(e) =>
                                                    onboard.setData((d) => ({
                                                        ...d,
                                                        department_id:
                                                            e.target.value,
                                                        position_id: '',
                                                    }))
                                                }
                                                className={selectClassName}
                                            >
                                                {onboardingOptions.departments.map(
                                                    (d) => (
                                                        <option
                                                            key={d.id}
                                                            value={d.id}
                                                        >
                                                            {d.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>,
                                            true,
                                        )}
                                        {field(
                                            'position_id',
                                            'Jabatan',
                                            <select
                                                id="ob-position_id"
                                                value={onboard.data.position_id}
                                                onChange={(e) =>
                                                    onboard.setData(
                                                        'position_id',
                                                        e.target.value,
                                                    )
                                                }
                                                className={selectClassName}
                                            >
                                                <option value="">
                                                    — Pilih —
                                                </option>
                                                {positionOptions.map((p) => (
                                                    <option
                                                        key={p.id}
                                                        value={p.id}
                                                    >
                                                        {p.name}
                                                    </option>
                                                ))}
                                            </select>,
                                            true,
                                        )}
                                        {field(
                                            'employment_status',
                                            'Status kepegawaian',
                                            <select
                                                id="ob-employment_status"
                                                value={
                                                    onboard.data
                                                        .employment_status
                                                }
                                                onChange={(e) =>
                                                    onboard.setData(
                                                        'employment_status',
                                                        e.target.value,
                                                    )
                                                }
                                                className={selectClassName}
                                            >
                                                {onboardingOptions.employmentStatuses.map(
                                                    (s) => (
                                                        <option
                                                            key={s.value}
                                                            value={s.value}
                                                        >
                                                            {s.label}
                                                        </option>
                                                    ),
                                                )}
                                            </select>,
                                            true,
                                        )}
                                        {field(
                                            'manager_id',
                                            'Atasan langsung',
                                            <select
                                                id="ob-manager_id"
                                                value={onboard.data.manager_id}
                                                onChange={(e) =>
                                                    onboard.setData(
                                                        'manager_id',
                                                        e.target.value,
                                                    )
                                                }
                                                className={selectClassName}
                                            >
                                                <option value="">
                                                    — Tidak ada —
                                                </option>
                                                {onboardingOptions.managers.map(
                                                    (m) => (
                                                        <option
                                                            key={m.id}
                                                            value={m.id}
                                                        >
                                                            {m.label}
                                                        </option>
                                                    ),
                                                )}
                                            </select>,
                                        )}
                                        {field(
                                            'join_date',
                                            'Tanggal masuk',
                                            <Input
                                                id="ob-join_date"
                                                type="date"
                                                value={onboard.data.join_date}
                                                onChange={(e) =>
                                                    onboard.setData(
                                                        'join_date',
                                                        e.target.value,
                                                    )
                                                }
                                            />,
                                            true,
                                        )}
                                        {field(
                                            'contract_end_date',
                                            'Akhir kontrak',
                                            <Input
                                                id="ob-contract_end_date"
                                                type="date"
                                                value={
                                                    onboard.data
                                                        .contract_end_date
                                                }
                                                onChange={(e) =>
                                                    onboard.setData(
                                                        'contract_end_date',
                                                        e.target.value,
                                                    )
                                                }
                                            />,
                                            onboard.data.employment_status ===
                                                'contract',
                                        )}
                                        {field(
                                            'work_schedule_id',
                                            'Jadwal kerja',
                                            <select
                                                id="ob-work_schedule_id"
                                                value={
                                                    onboard.data
                                                        .work_schedule_id
                                                }
                                                onChange={(e) =>
                                                    onboard.setData(
                                                        'work_schedule_id',
                                                        e.target.value,
                                                    )
                                                }
                                                className={selectClassName}
                                            >
                                                <option value="">
                                                    — Belum ada —
                                                </option>
                                                {onboardingOptions.workSchedules.map(
                                                    (s) => (
                                                        <option
                                                            key={s.id}
                                                            value={s.id}
                                                        >
                                                            {s.label}
                                                        </option>
                                                    ),
                                                )}
                                            </select>,
                                        )}
                                        <InputError
                                            message={onboardErrors.status}
                                            className="sm:col-span-2"
                                        />
                                        <div className="sm:col-span-2">
                                            <Button
                                                type="submit"
                                                className="rounded-control"
                                                disabled={onboard.processing}
                                            >
                                                {onboard.processing ? (
                                                    <Spinner />
                                                ) : (
                                                    <UserPlus className="size-4" />
                                                )}
                                                Jadikan Karyawan
                                            </Button>
                                        </div>
                                    </form>
                                </Card>
                            )}

                        {app.onboarding && (
                            <Card title="Checklist Onboarding">
                                {app.onboarding.employee && (
                                    <p className="mb-3 text-sm">
                                        Karyawan:{' '}
                                        <Link
                                            href={employeeShow.url(
                                                app.onboarding.employee.id,
                                            )}
                                            className="font-semibold text-primary hover:underline"
                                        >
                                            {app.onboarding.employee.full_name}{' '}
                                            ({app.onboarding.employee.nik})
                                        </Link>{' '}
                                        · mulai{' '}
                                        {formatTanggal(
                                            `${app.onboarding.start_date}T00:00:00+07:00`,
                                        )}
                                    </p>
                                )}
                                <ul className="space-y-2">
                                    {app.onboarding.checklist.map(
                                        (c, index) => (
                                            <li key={c.item}>
                                                <button
                                                    type="button"
                                                    disabled={!canManage}
                                                    onClick={() =>
                                                        router.post(
                                                            onboardingRoutes.checklist.url(
                                                                app.onboarding!
                                                                    .id,
                                                            ),
                                                            {
                                                                index,
                                                                done: !c.done,
                                                            },
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                    className="flex w-full items-center gap-2 rounded-control border border-border p-2 text-left text-sm hover:bg-accent disabled:cursor-default"
                                                >
                                                    {c.done ? (
                                                        <CheckCircle2 className="size-5 text-success" />
                                                    ) : (
                                                        <Circle className="size-5 text-text-muted" />
                                                    )}
                                                    <span
                                                        className={
                                                            c.done
                                                                ? 'text-text-muted line-through'
                                                                : ''
                                                        }
                                                    >
                                                        {c.item}
                                                    </span>
                                                </button>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </Card>
                        )}
                    </div>

                    <div className="flex flex-col gap-6">
                        {canManage && app.status === 'applied' && (
                            <Card title="Seleksi Administrasi">
                                <DecisionButtons
                                    url={applications.screen.url(app.id)}
                                    approveLabel="Lolos"
                                    approveValue="pass"
                                    rejectValue="reject"
                                />
                            </Card>
                        )}
                        {canManage && app.status === 'interview' && (
                            <Card title="Keputusan Akhir">
                                <DecisionButtons
                                    url={applications.decide.url(app.id)}
                                    approveLabel="Terima"
                                    approveValue="accept"
                                    rejectValue="reject"
                                />
                            </Card>
                        )}
                        {app.decision_note && (
                            <Card title="Catatan Keputusan">
                                <p className="text-sm">{app.decision_note}</p>
                            </Card>
                        )}
                        {histories.length > 0 && (
                            <Card title="Riwayat Tahapan">
                                <ol className="space-y-3">
                                    {histories.map((h, i) => (
                                        <li
                                            key={i}
                                            className="border-l-2 border-primary/20 pl-3 text-sm"
                                        >
                                            <p className="font-semibold text-navy">
                                                {h.to}
                                            </p>
                                            <p className="text-xs text-text-muted">
                                                {h.actor}
                                                {h.at &&
                                                    ` · ${formatTanggal(h.at)}, ${formatJam(h.at)}`}
                                            </p>
                                            {h.note && (
                                                <p className="text-text-muted italic">
                                                    {h.note}
                                                </p>
                                            )}
                                        </li>
                                    ))}
                                </ol>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
