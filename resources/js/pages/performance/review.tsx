import { Head, router, useForm } from '@inertiajs/react';
import { EyeOff, Send, Trophy } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { FormField, selectClassName } from '@/components/form-field';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { formatJam, formatTanggal } from '@/lib/format';
import type { ReviewStatusValue } from '@/lib/performance';
import { formatScore, reviewTone } from '@/lib/performance';
import { formatRange } from '@/lib/requests';
import reviewRoutes from '@/routes/performance/reviews';

type Item = {
    indicator_id: number;
    name: string;
    description: string | null;
    weight: string;
    scale_min: number;
    scale_max: number;
    target: string | null;
    self_score: number | null;
    self_comment: string | null;
    manager_score: number | null;
    manager_comment: string | null;
};

type Props = {
    review: {
        id: number;
        version: number;
        status: ReviewStatusValue;
        status_label: string;
        period: { id: number; name: string; status: string };
        self_assessment_enabled: boolean;
        employee: {
            full_name: string;
            nik: string;
            position: string | null;
            department: string | null;
        };
        reviewer: string;
        items: Item[];
        self_assessment: {
            achievements: string;
            challenges: string | null;
        } | null;
        manager_comment: string | null;
        hr_comment: string | null;
        final_score: string | null;
        rating: string | null;
        result_hidden: boolean;
        development_plans: {
            id: number;
            type: string;
            description: string;
            target_date: string | null;
            status: string;
            created_by: string | null;
        }[];
        logs: {
            action: string;
            note: string | null;
            actor: string;
            at: string | null;
        }[];
    };
    can: {
        self: boolean;
        manager: boolean;
        hrCheck: boolean;
        publish: boolean;
        plan: boolean;
    };
    planTypes: Record<string, string>;
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

function ScoreInputs({
    items,
    scores,
    comments,
    errors,
    onScore,
    onComment,
}: {
    items: Item[];
    scores: Record<string, string>;
    comments: Record<string, string>;
    errors: Record<string, string | undefined>;
    onScore: (id: number, v: string) => void;
    onComment: (id: number, v: string) => void;
}) {
    return (
        <div className="grid gap-4">
            {items.map((item) => {
                const range = Array.from(
                    { length: item.scale_max - item.scale_min + 1 },
                    (_, i) => item.scale_min + i,
                );

                return (
                    <div
                        key={item.indicator_id}
                        className="grid gap-2 rounded-control border border-border p-3"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p className="text-sm font-medium">
                                    {item.name}
                                </p>
                                <p className="text-xs text-text-muted">
                                    Bobot{' '}
                                    {Number(item.weight).toLocaleString(
                                        'id-ID',
                                    )}
                                    %
                                    {item.target && ` · target: ${item.target}`}
                                </p>
                            </div>
                            <div
                                role="radiogroup"
                                aria-label={item.name}
                                className="flex flex-wrap gap-1"
                            >
                                {range.map((n) => (
                                    <button
                                        key={n}
                                        type="button"
                                        role="radio"
                                        aria-checked={
                                            scores[item.indicator_id] ===
                                            String(n)
                                        }
                                        onClick={() =>
                                            onScore(
                                                item.indicator_id,
                                                String(n),
                                            )
                                        }
                                        className={
                                            scores[item.indicator_id] ===
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
                        <Input
                            aria-label={`Komentar ${item.name}`}
                            placeholder="Komentar (opsional)"
                            value={comments[item.indicator_id] ?? ''}
                            onChange={(e) =>
                                onComment(item.indicator_id, e.target.value)
                            }
                        />
                        <InputError
                            message={errors[`scores.${item.indicator_id}`]}
                        />
                    </div>
                );
            })}
        </div>
    );
}

export default function ReviewSheet({ review, can, planTypes }: Props) {
    const initialScores = (key: 'self_score' | 'manager_score') =>
        Object.fromEntries(
            review.items.map((i) => [
                i.indicator_id,
                i[key] !== null ? String(i[key]) : '',
            ]),
        );
    const initialComments = (key: 'self_comment' | 'manager_comment') =>
        Object.fromEntries(
            review.items.map((i) => [i.indicator_id, i[key] ?? '']),
        );

    const self = useForm({
        scores: initialScores('self_score'),
        comments: initialComments('self_comment'),
        achievements: review.self_assessment?.achievements ?? '',
        challenges: review.self_assessment?.challenges ?? '',
        version: review.version,
    });
    const manager = useForm({
        scores: initialScores('manager_score'),
        comments: initialComments('manager_comment'),
        manager_comment: review.manager_comment ?? '',
        version: review.version,
    });
    const hr = useForm({ decision: '', note: '', version: review.version });
    const plan = useForm({
        type: 'training',
        description: '',
        target_date: '',
    });

    const selfErrors = self.errors as Record<string, string | undefined>;
    const managerErrors = manager.errors as Record<string, string | undefined>;
    const hrErrors = hr.errors as Record<string, string | undefined>;

    const submitSelf = (e: FormEvent) => {
        e.preventDefault();
        self.post(reviewRoutes.self.url(review.id), { preserveScroll: true });
    };
    const submitManager = (e: FormEvent) => {
        e.preventDefault();
        manager.post(reviewRoutes.manager.url(review.id), {
            preserveScroll: true,
        });
    };
    const hrSend = (decision: 'finalize' | 'return') => {
        hr.transform((d) => ({ ...d, decision }));
        hr.post(reviewRoutes.hr.url(review.id), { preserveScroll: true });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Penilaian Kinerja', href: '/penilaian-kinerja' },
                {
                    title: `${review.employee.full_name} — ${review.period.name}`,
                    href: '#',
                },
            ]}
        >
            <Head title={`Penilaian ${review.employee.full_name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-2xl font-bold text-navy">
                        {review.employee.full_name}
                    </h1>
                    <StatusBadge tone={reviewTone[review.status]}>
                        {review.status_label}
                    </StatusBadge>
                </div>
                <p className="-mt-4 text-sm text-text-muted">
                    {review.period.name} · {review.employee.nik} ·{' '}
                    {review.employee.position ?? '—'} ·{' '}
                    {review.employee.department ?? '—'} · penilai{' '}
                    {review.reviewer}
                </p>

                <div className="grid gap-6 xl:grid-cols-3">
                    <div className="flex flex-col gap-6 xl:col-span-2">
                        {can.self && (
                            <Card title="Evaluasi Diri">
                                <form
                                    onSubmit={submitSelf}
                                    className="grid gap-4"
                                >
                                    <ScoreInputs
                                        items={review.items}
                                        scores={self.data.scores}
                                        comments={self.data.comments}
                                        errors={selfErrors}
                                        onScore={(id, v) =>
                                            self.setData('scores', {
                                                ...self.data.scores,
                                                [id]: v,
                                            })
                                        }
                                        onComment={(id, v) =>
                                            self.setData('comments', {
                                                ...self.data.comments,
                                                [id]: v,
                                            })
                                        }
                                    />
                                    <FormField
                                        id="achievements"
                                        label="Pencapaian utama"
                                        required
                                        error={self.errors.achievements}
                                    >
                                        <textarea
                                            id="achievements"
                                            rows={4}
                                            className={textarea}
                                            value={self.data.achievements}
                                            onChange={(e) =>
                                                self.setData(
                                                    'achievements',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        id="challenges"
                                        label="Kendala / kebutuhan dukungan"
                                        error={self.errors.challenges}
                                    >
                                        <textarea
                                            id="challenges"
                                            rows={3}
                                            className={textarea}
                                            value={self.data.challenges}
                                            onChange={(e) =>
                                                self.setData(
                                                    'challenges',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <InputError
                                        message={
                                            selfErrors.status ??
                                            selfErrors.version
                                        }
                                    />
                                    <Button
                                        type="submit"
                                        className="rounded-control"
                                        disabled={self.processing}
                                    >
                                        {self.processing ? (
                                            <Spinner />
                                        ) : (
                                            <Send className="size-4" />
                                        )}
                                        Kirim evaluasi diri
                                    </Button>
                                </form>
                            </Card>
                        )}

                        {can.manager && (
                            <Card title="Penilaian Atasan">
                                {review.hr_comment &&
                                    review.status === 'returned' && (
                                        <p className="mb-4 rounded-control bg-danger/10 p-3 text-sm text-danger">
                                            Catatan HRD: {review.hr_comment}
                                        </p>
                                    )}
                                <form
                                    onSubmit={submitManager}
                                    className="grid gap-4"
                                >
                                    <ScoreInputs
                                        items={review.items}
                                        scores={manager.data.scores}
                                        comments={manager.data.comments}
                                        errors={managerErrors}
                                        onScore={(id, v) =>
                                            manager.setData('scores', {
                                                ...manager.data.scores,
                                                [id]: v,
                                            })
                                        }
                                        onComment={(id, v) =>
                                            manager.setData('comments', {
                                                ...manager.data.comments,
                                                [id]: v,
                                            })
                                        }
                                    />
                                    <FormField
                                        id="manager_comment"
                                        label="Catatan umum"
                                        error={manager.errors.manager_comment}
                                    >
                                        <textarea
                                            id="manager_comment"
                                            rows={3}
                                            className={textarea}
                                            value={manager.data.manager_comment}
                                            onChange={(e) =>
                                                manager.setData(
                                                    'manager_comment',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <InputError
                                        message={
                                            managerErrors.status ??
                                            managerErrors.version
                                        }
                                    />
                                    <Button
                                        type="submit"
                                        className="rounded-control"
                                        disabled={manager.processing}
                                    >
                                        {manager.processing ? (
                                            <Spinner />
                                        ) : (
                                            <Send className="size-4" />
                                        )}
                                        Kirim ke HRD
                                    </Button>
                                </form>
                            </Card>
                        )}

                        <Card title="Rincian Nilai">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="text-left text-xs text-text-muted uppercase">
                                            <th className="py-2 pr-3">
                                                Indikator
                                            </th>
                                            <th className="py-2 pr-3">Bobot</th>
                                            <th className="py-2 pr-3">Diri</th>
                                            <th className="py-2">Atasan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {review.items.map((i) => (
                                            <tr
                                                key={i.indicator_id}
                                                className="border-t border-border align-top"
                                            >
                                                <td className="py-2 pr-3">
                                                    {i.name}
                                                    <span className="block text-xs text-text-muted">
                                                        skala {i.scale_min}–
                                                        {i.scale_max}
                                                    </span>
                                                </td>
                                                <td className="py-2 pr-3">
                                                    {Number(
                                                        i.weight,
                                                    ).toLocaleString('id-ID')}
                                                    %
                                                </td>
                                                <td className="py-2 pr-3">
                                                    {i.self_score ?? '—'}
                                                    {i.self_comment && (
                                                        <span className="block text-xs text-text-muted">
                                                            {i.self_comment}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-2">
                                                    {review.result_hidden ? (
                                                        <EyeOff
                                                            className="size-4 text-text-muted"
                                                            aria-label="Disembunyikan"
                                                        />
                                                    ) : (
                                                        (i.manager_score ?? '—')
                                                    )}
                                                    {i.manager_comment && (
                                                        <span className="block text-xs text-text-muted">
                                                            {i.manager_comment}
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {review.self_assessment && (
                                <div className="mt-4 grid gap-2 text-sm">
                                    <p>
                                        <span className="text-text-muted">
                                            Pencapaian:
                                        </span>{' '}
                                        {review.self_assessment.achievements}
                                    </p>
                                    {review.self_assessment.challenges && (
                                        <p>
                                            <span className="text-text-muted">
                                                Kendala:
                                            </span>{' '}
                                            {review.self_assessment.challenges}
                                        </p>
                                    )}
                                </div>
                            )}
                        </Card>

                        {(review.development_plans.length > 0 || can.plan) && (
                            <Card title="Rencana Pengembangan">
                                {review.development_plans.length > 0 && (
                                    <ul className="mb-4 space-y-2">
                                        {review.development_plans.map((p) => (
                                            <li
                                                key={p.id}
                                                className="rounded-control border border-border p-3 text-sm"
                                            >
                                                <p className="font-semibold text-navy">
                                                    {p.type}{' '}
                                                    <span className="font-normal text-text-muted">
                                                        · {p.status}
                                                    </span>
                                                </p>
                                                <p>{p.description}</p>
                                                {p.target_date && (
                                                    <p className="text-xs text-text-muted">
                                                        Target{' '}
                                                        {formatRange(
                                                            p.target_date,
                                                            p.target_date,
                                                        )}
                                                    </p>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {can.plan && (
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            plan.post(
                                                reviewRoutes.plans.url(
                                                    review.id,
                                                ),
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        plan.reset(),
                                                },
                                            );
                                        }}
                                        className="grid gap-3 sm:grid-cols-3"
                                    >
                                        <FormField
                                            id="plan-type"
                                            label="Jenis"
                                            required
                                            error={plan.errors.type}
                                        >
                                            <select
                                                id="plan-type"
                                                value={plan.data.type}
                                                onChange={(e) =>
                                                    plan.setData(
                                                        'type',
                                                        e.target.value,
                                                    )
                                                }
                                                className={selectClassName}
                                            >
                                                {Object.entries(planTypes).map(
                                                    ([v, l]) => (
                                                        <option
                                                            key={v}
                                                            value={v}
                                                        >
                                                            {l}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </FormField>
                                        <div className="sm:col-span-2">
                                            <FormField
                                                id="plan-target"
                                                label="Target tanggal"
                                                error={plan.errors.target_date}
                                            >
                                                <Input
                                                    id="plan-target"
                                                    type="date"
                                                    value={
                                                        plan.data.target_date
                                                    }
                                                    onChange={(e) =>
                                                        plan.setData(
                                                            'target_date',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </FormField>
                                        </div>
                                        <div className="sm:col-span-3">
                                            <FormField
                                                id="plan-desc"
                                                label="Deskripsi"
                                                required
                                                error={plan.errors.description}
                                            >
                                                <textarea
                                                    id="plan-desc"
                                                    rows={2}
                                                    className={textarea}
                                                    value={
                                                        plan.data.description
                                                    }
                                                    onChange={(e) =>
                                                        plan.setData(
                                                            'description',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </FormField>
                                        </div>
                                        <Button
                                            type="submit"
                                            className="rounded-control sm:w-fit"
                                            disabled={plan.processing}
                                        >
                                            Tambah rencana
                                        </Button>
                                    </form>
                                )}
                            </Card>
                        )}
                    </div>

                    <div className="flex flex-col gap-6">
                        <section className="rounded-card bg-navy p-5 text-white">
                            <p className="flex items-center gap-2 text-sm text-white/70">
                                <Trophy className="size-4 text-cyan" /> Nilai
                                akhir
                            </p>
                            {review.result_hidden ? (
                                <p className="mt-2 text-sm text-white/80">
                                    Hasil akan terlihat setelah HRD
                                    mempublikasikan penilaian ini.
                                </p>
                            ) : review.final_score !== null ? (
                                <>
                                    <p className="mt-1 text-4xl font-bold">
                                        {formatScore(review.final_score)}
                                    </p>
                                    <p className="text-cyan">{review.rating}</p>
                                    {review.hr_comment && (
                                        <p className="mt-2 text-sm text-white/80">
                                            {review.hr_comment}
                                        </p>
                                    )}
                                </>
                            ) : (
                                <p className="mt-2 text-sm text-white/80">
                                    Belum difinalkan.
                                </p>
                            )}
                        </section>

                        {can.hrCheck && (
                            <Card title="Pemeriksaan HRD">
                                <div className="grid gap-3">
                                    <Label htmlFor="hr-note">
                                        Catatan{' '}
                                        <span className="font-normal text-text-muted">
                                            (wajib jika dikembalikan)
                                        </span>
                                    </Label>
                                    <textarea
                                        id="hr-note"
                                        rows={3}
                                        className={textarea}
                                        value={hr.data.note}
                                        onChange={(e) =>
                                            hr.setData('note', e.target.value)
                                        }
                                    />
                                    <InputError
                                        message={
                                            hrErrors.note ??
                                            hrErrors.status ??
                                            hrErrors.version
                                        }
                                    />
                                    <div className="flex gap-2">
                                        <Button
                                            className="flex-1 rounded-control bg-success hover:bg-success/90"
                                            disabled={hr.processing}
                                            onClick={() => hrSend('finalize')}
                                        >
                                            Finalkan
                                        </Button>
                                        <Button
                                            variant="outline"
                                            className="flex-1 rounded-control text-danger hover:text-danger"
                                            disabled={hr.processing}
                                            onClick={() => hrSend('return')}
                                        >
                                            Kembalikan
                                        </Button>
                                    </div>
                                </div>
                            </Card>
                        )}

                        {can.publish && (
                            <Button
                                className="rounded-control"
                                onClick={() =>
                                    router.post(
                                        reviewRoutes.publish.url(review.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Send className="size-4" />
                                Publikasikan ke karyawan
                            </Button>
                        )}

                        {review.logs.length > 0 && (
                            <Card title="Riwayat Tindakan">
                                <ol className="space-y-3">
                                    {review.logs.map((l, i) => (
                                        <li
                                            key={i}
                                            className="border-l-2 border-primary/20 pl-3 text-sm"
                                        >
                                            <p className="font-semibold text-navy">
                                                {l.action}
                                            </p>
                                            <p className="text-xs text-text-muted">
                                                {l.actor}
                                                {l.at &&
                                                    ` · ${formatTanggal(l.at)}, ${formatJam(l.at)}`}
                                            </p>
                                            {l.note && (
                                                <p className="text-text-muted italic">
                                                    {l.note}
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
