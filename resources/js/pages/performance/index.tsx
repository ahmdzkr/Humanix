import { Head, Link, useForm } from '@inertiajs/react';
import { Award, ClipboardCheck, Plus, Users } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { ReviewStatusValue } from '@/lib/performance';
import { formatScore, periodTone, reviewTone } from '@/lib/performance';
import { formatRange } from '@/lib/requests';
import periods from '@/routes/performance/periods';
import reviews from '@/routes/performance/reviews';

type ReviewRow = {
    id: number;
    period: string;
    period_status: string;
    employee_name: string;
    nik: string;
    status: ReviewStatusValue;
    status_label: string;
    final_score: string | null;
    rating: string | null;
};

type PeriodRow = {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    status: string;
    status_label: string;
    reviews_count: number;
};

type Props = {
    myReviews: ReviewRow[];
    teamReviews: ReviewRow[];
    periods: PeriodRow[];
    isHr: boolean;
};

function Section({
    title,
    icon,
    children,
}: {
    title: string;
    icon: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="rounded-card border border-border bg-surface">
            <h2 className="flex items-center gap-2 border-b border-border p-4 font-semibold text-navy">
                {icon}
                {title}
            </h2>
            {children}
        </section>
    );
}

function ReviewList({
    rows,
    showEmployee,
    empty,
}: {
    rows: ReviewRow[];
    showEmployee: boolean;
    empty: string;
}) {
    if (rows.length === 0) {
        return (
            <p className="p-6 text-center text-sm text-text-muted">{empty}</p>
        );
    }

    return (
        <ul className="divide-y divide-border">
            {rows.map((r) => (
                <li
                    key={r.id}
                    className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p className="font-semibold text-navy">
                            {showEmployee
                                ? `${r.employee_name} (${r.nik})`
                                : r.period}
                        </p>
                        <p className="text-sm text-text-muted">
                            {showEmployee ? r.period : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {r.final_score !== null && (
                            <span className="text-sm font-semibold text-navy">
                                {formatScore(r.final_score)} · {r.rating}
                            </span>
                        )}
                        <StatusBadge tone={reviewTone[r.status]}>
                            {r.status_label}
                        </StatusBadge>
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            className="rounded-control"
                        >
                            <Link href={reviews.show.url(r.id)}>Buka</Link>
                        </Button>
                    </div>
                </li>
            ))}
        </ul>
    );
}

export default function PerformanceIndex({
    myReviews,
    teamReviews,
    periods: periodRows,
    isHr,
}: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: '',
        start_date: '',
        end_date: '',
        self_assessment_enabled: true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(periods.store.url(), { onSuccess: () => setOpen(false) });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Penilaian Kinerja', href: '/penilaian-kinerja' },
            ]}
        >
            <Head title="Penilaian Kinerja" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Penilaian Kinerja"
                    description="Evaluasi diri → penilaian atasan → pemeriksaan HRD → hasil dipublikasikan."
                    actions={
                        isHr && (
                            <Button
                                className="rounded-control"
                                onClick={() => setOpen(true)}
                            >
                                <Plus className="size-4" />
                                Periode Baru
                            </Button>
                        )
                    }
                />

                {isHr && (
                    <Section
                        title="Periode Penilaian"
                        icon={<Award className="size-4 text-primary" />}
                    >
                        {periodRows.length === 0 ? (
                            <p className="p-6 text-center text-sm text-text-muted">
                                Belum ada periode penilaian.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {periodRows.map((p) => (
                                    <li
                                        key={p.id}
                                        className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <Link
                                                href={periods.show.url(p.id)}
                                                className="font-semibold text-navy hover:text-primary"
                                            >
                                                {p.name}
                                            </Link>
                                            <p className="text-sm text-text-muted">
                                                {formatRange(
                                                    p.start_date,
                                                    p.end_date,
                                                )}{' '}
                                                · {p.reviews_count} lembar
                                                penilaian
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <StatusBadge
                                                tone={periodTone[p.status]}
                                            >
                                                {p.status_label}
                                            </StatusBadge>
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                                className="rounded-control"
                                            >
                                                <Link
                                                    href={periods.show.url(
                                                        p.id,
                                                    )}
                                                >
                                                    Kelola
                                                </Link>
                                            </Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Section>
                )}

                {(teamReviews.length > 0 || !isHr) && (
                    <Section
                        title="Perlu Saya Nilai"
                        icon={<Users className="size-4 text-primary" />}
                    >
                        <ReviewList
                            rows={teamReviews}
                            showEmployee
                            empty="Tidak ada penilaian yang ditugaskan kepada Anda."
                        />
                    </Section>
                )}

                <Section
                    title="Penilaian Saya"
                    icon={<ClipboardCheck className="size-4 text-primary" />}
                >
                    <ReviewList
                        rows={myReviews}
                        showEmployee={false}
                        empty="Belum ada periode penilaian untuk Anda."
                    />
                </Section>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="rounded-card sm:max-w-md">
                    <form onSubmit={submit} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle className="text-navy">
                                Periode Penilaian Baru
                            </DialogTitle>
                            <DialogDescription>
                                Mis. "Semester 2 2026".
                            </DialogDescription>
                        </DialogHeader>
                        <FormField
                            id="name"
                            label="Nama periode"
                            required
                            error={form.errors.name}
                        >
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                        </FormField>
                        <div className="grid grid-cols-2 gap-4">
                            <FormField
                                id="start_date"
                                label="Mulai"
                                required
                                error={form.errors.start_date}
                            >
                                <Input
                                    id="start_date"
                                    type="date"
                                    value={form.data.start_date}
                                    onChange={(e) =>
                                        form.setData(
                                            'start_date',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="end_date"
                                label="Selesai"
                                required
                                error={form.errors.end_date}
                            >
                                <Input
                                    id="end_date"
                                    type="date"
                                    value={form.data.end_date}
                                    onChange={(e) =>
                                        form.setData('end_date', e.target.value)
                                    }
                                />
                            </FormField>
                        </div>
                        <label className="flex items-center gap-3 text-sm">
                            <Checkbox
                                checked={form.data.self_assessment_enabled}
                                onCheckedChange={(c) =>
                                    form.setData(
                                        'self_assessment_enabled',
                                        c === true,
                                    )
                                }
                            />
                            Aktifkan evaluasi diri karyawan
                        </label>
                        <DialogFooter>
                            <Button
                                type="submit"
                                className="rounded-control"
                                disabled={form.processing}
                            >
                                {form.processing && <Spinner />}
                                Buat periode
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
