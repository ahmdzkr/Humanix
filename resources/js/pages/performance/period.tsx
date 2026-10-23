import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, Play, Plus, Send, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormField, selectClassName } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
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
import { cn } from '@/lib/utils';
import indicators from '@/routes/performance/indicators';
import periods from '@/routes/performance/periods';
import reviews from '@/routes/performance/reviews';

type Indicator = {
    id: number;
    name: string;
    description: string | null;
    weight: string;
    scale_min: number;
    scale_max: number;
    target: string | null;
};

type Props = {
    period: {
        id: number;
        name: string;
        start_date: string;
        end_date: string;
        self_assessment_enabled: boolean;
        status: 'draft' | 'active' | 'closed';
        status_label: string;
        total_weight: string;
    };
    indicators: Indicator[];
    reviews: {
        id: number;
        employee_name: string;
        nik: string;
        department: string | null;
        reviewer: string;
        status: ReviewStatusValue;
        status_label: string;
        final_score: string | null;
        rating: string | null;
    }[];
    counts: Record<string, number>;
    statusOptions: { value: ReviewStatusValue; label: string }[];
    filters: { status: string };
};

const emptyIndicator = {
    name: '',
    description: '',
    weight: '',
    scale_min: '1',
    scale_max: '5',
    target: '',
};

export default function PerformancePeriod({
    period,
    indicators: rows,
    reviews: reviewRows,
    counts,
    statusOptions,
    filters,
}: Props) {
    const [editing, setEditing] = useState<Indicator | 'new' | null>(null);
    const [confirm, setConfirm] = useState<
        'activate' | 'publish' | 'close' | null
    >(null);
    const form = useForm(emptyIndicator);
    const total = Number(period.total_weight);
    const isDraft = period.status === 'draft';

    const open = (i: Indicator | 'new') => {
        form.clearErrors();
        form.setData(
            i === 'new'
                ? emptyIndicator
                : {
                      name: i.name,
                      description: i.description ?? '',
                      weight: i.weight,
                      scale_min: String(i.scale_min),
                      scale_max: String(i.scale_max),
                      target: i.target ?? '',
                  },
        );
        setEditing(i);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const opts = {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        };

        if (editing && editing !== 'new') {
            form.put(indicators.update.url([period.id, editing.id]), opts);
        } else {
            form.post(indicators.store.url(period.id), opts);
        }
    };

    const runConfirm = () => {
        const url =
            confirm === 'activate'
                ? periods.activate.url(period.id)
                : confirm === 'publish'
                  ? periods.publish.url(period.id)
                  : periods.close.url(period.id);
        router.post(
            url,
            {},
            { preserveScroll: true, onFinish: () => setConfirm(null) },
        );
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Penilaian Kinerja', href: '/penilaian-kinerja' },
                { title: period.name, href: '#' },
            ]}
        >
            <Head title={period.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={period.name}
                    description={`${formatRange(period.start_date, period.end_date)} · evaluasi diri ${period.self_assessment_enabled ? 'aktif' : 'tidak aktif'}`}
                    actions={
                        <>
                            <StatusBadge tone={periodTone[period.status]}>
                                {period.status_label}
                            </StatusBadge>
                            {isDraft && (
                                <Button
                                    className="rounded-control"
                                    onClick={() => setConfirm('activate')}
                                >
                                    <Play className="size-4" />
                                    Aktifkan
                                </Button>
                            )}
                            {period.status === 'active' &&
                                (counts.final ?? 0) > 0 && (
                                    <Button
                                        className="rounded-control"
                                        onClick={() => setConfirm('publish')}
                                    >
                                        <Send className="size-4" />
                                        Publikasikan semua yang final (
                                        {counts.final})
                                    </Button>
                                )}
                            {period.status === 'active' && (
                                <Button
                                    variant="outline"
                                    className="rounded-control"
                                    onClick={() => setConfirm('close')}
                                >
                                    Tutup periode
                                </Button>
                            )}
                        </>
                    }
                />

                <section className="rounded-card border border-border bg-surface">
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border p-4">
                        <h2 className="font-semibold text-navy">Indikator</h2>
                        <div className="flex items-center gap-3">
                            <span
                                className={cn(
                                    'text-sm font-semibold',
                                    total === 100
                                        ? 'text-success'
                                        : 'text-danger',
                                )}
                            >
                                Total bobot {total.toLocaleString('id-ID')}%{' '}
                                {total === 100 ? '✓' : '(harus 100%)'}
                            </span>
                            {isDraft && (
                                <Button
                                    size="sm"
                                    className="rounded-control"
                                    onClick={() => open('new')}
                                >
                                    <Plus className="size-4" />
                                    Indikator
                                </Button>
                            )}
                        </div>
                    </div>
                    {rows.length === 0 ? (
                        <p className="p-6 text-center text-sm text-text-muted">
                            Belum ada indikator.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {rows.map((i) => (
                                <li
                                    key={i.id}
                                    className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p className="font-medium text-navy">
                                            {i.name}
                                        </p>
                                        <p className="text-xs text-text-muted">
                                            Bobot{' '}
                                            {Number(i.weight).toLocaleString(
                                                'id-ID',
                                            )}
                                            % · skala {i.scale_min}–
                                            {i.scale_max}
                                            {i.target &&
                                                ` · target: ${i.target}`}
                                        </p>
                                    </div>
                                    {isDraft && (
                                        <div className="flex gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="rounded-control"
                                                onClick={() => open(i)}
                                                aria-label={`Ubah ${i.name}`}
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="rounded-control text-danger hover:text-danger"
                                                aria-label={`Hapus ${i.name}`}
                                                onClick={() =>
                                                    router.delete(
                                                        indicators.destroy.url([
                                                            period.id,
                                                            i.id,
                                                        ]),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {!isDraft && (
                    <section className="rounded-card border border-border bg-surface">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border p-4">
                            <h2 className="font-semibold text-navy">
                                Lembar Penilaian
                            </h2>
                            <select
                                aria-label="Filter status"
                                value={filters.status}
                                onChange={(e) =>
                                    router.get(
                                        periods.show.url(period.id),
                                        e.target.value
                                            ? { status: e.target.value }
                                            : {},
                                        { preserveState: true, replace: true },
                                    )
                                }
                                className={cn(selectClassName, 'w-auto')}
                            >
                                <option value="">Semua status</option>
                                {statusOptions.map((s) => (
                                    <option key={s.value} value={s.value}>
                                        {s.label} ({counts[s.value] ?? 0})
                                    </option>
                                ))}
                            </select>
                        </div>
                        <ul className="divide-y divide-border">
                            {reviewRows.map((r) => (
                                <li
                                    key={r.id}
                                    className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p className="font-semibold text-navy">
                                            {r.employee_name}
                                        </p>
                                        <p className="text-xs text-text-muted">
                                            {r.nik} · {r.department ?? '—'} ·
                                            penilai {r.reviewer}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {r.final_score !== null && (
                                            <span className="text-sm font-semibold">
                                                {formatScore(r.final_score)} ·{' '}
                                                {r.rating}
                                            </span>
                                        )}
                                        <StatusBadge
                                            tone={reviewTone[r.status]}
                                        >
                                            {r.status_label}
                                        </StatusBadge>
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                            className="rounded-control"
                                        >
                                            <Link href={reviews.show.url(r.id)}>
                                                Buka
                                            </Link>
                                        </Button>
                                    </div>
                                </li>
                            ))}
                            {reviewRows.length === 0 && (
                                <li className="p-6 text-center text-sm text-text-muted">
                                    Tidak ada.
                                </li>
                            )}
                        </ul>
                    </section>
                )}
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(o) => !o && setEditing(null)}
            >
                <DialogContent className="rounded-card sm:max-w-lg">
                    <form onSubmit={submit} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle className="text-navy">
                                {editing === 'new'
                                    ? 'Tambah Indikator'
                                    : 'Ubah Indikator'}
                            </DialogTitle>
                        </DialogHeader>
                        <FormField
                            id="name"
                            label="Nama indikator"
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
                        <FormField
                            id="description"
                            label="Deskripsi"
                            error={form.errors.description}
                        >
                            <Input
                                id="description"
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                            />
                        </FormField>
                        <div className="grid grid-cols-3 gap-4">
                            <FormField
                                id="weight"
                                label="Bobot (%)"
                                required
                                error={form.errors.weight}
                            >
                                <Input
                                    id="weight"
                                    inputMode="decimal"
                                    value={form.data.weight}
                                    onChange={(e) =>
                                        form.setData('weight', e.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                id="scale_min"
                                label="Skala min"
                                required
                                error={form.errors.scale_min}
                            >
                                <select
                                    id="scale_min"
                                    value={form.data.scale_min}
                                    onChange={(e) =>
                                        form.setData(
                                            'scale_min',
                                            e.target.value,
                                        )
                                    }
                                    className={selectClassName}
                                >
                                    {[0, 1].map((n) => (
                                        <option key={n} value={n}>
                                            {n}
                                        </option>
                                    ))}
                                </select>
                            </FormField>
                            <FormField
                                id="scale_max"
                                label="Skala maks"
                                required
                                error={form.errors.scale_max}
                            >
                                <select
                                    id="scale_max"
                                    value={form.data.scale_max}
                                    onChange={(e) =>
                                        form.setData(
                                            'scale_max',
                                            e.target.value,
                                        )
                                    }
                                    className={selectClassName}
                                >
                                    {[4, 5, 10].map((n) => (
                                        <option key={n} value={n}>
                                            {n}
                                        </option>
                                    ))}
                                </select>
                            </FormField>
                        </div>
                        <FormField
                            id="target"
                            label="Target"
                            error={form.errors.target}
                        >
                            <Input
                                id="target"
                                placeholder="Mis. 100% tugas selesai tepat waktu"
                                value={form.data.target}
                                onChange={(e) =>
                                    form.setData('target', e.target.value)
                                }
                            />
                        </FormField>
                        {(form.errors as Record<string, string | undefined>)
                            .period && (
                            <p className="text-sm text-danger">
                                {
                                    (
                                        form.errors as Record<
                                            string,
                                            string | undefined
                                        >
                                    ).period
                                }
                            </p>
                        )}
                        <DialogFooter>
                            <Button
                                type="submit"
                                className="rounded-control"
                                disabled={form.processing}
                            >
                                {form.processing && <Spinner />}
                                Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={confirm !== null}
                onOpenChange={(o) => !o && setConfirm(null)}
                title={
                    confirm === 'activate'
                        ? 'Aktifkan periode?'
                        : confirm === 'publish'
                          ? 'Publikasikan hasil?'
                          : 'Tutup periode?'
                }
                description={
                    confirm === 'activate'
                        ? 'Lembar penilaian dibuat untuk semua karyawan aktif. Indikator tidak bisa diubah lagi setelah ini.'
                        : confirm === 'publish'
                          ? 'Semua penilaian berstatus Final akan terlihat oleh karyawan yang bersangkutan.'
                          : 'Periode hanya bisa ditutup bila semua penilaian sudah dipublikasikan.'
                }
                confirmLabel="Lanjutkan"
                onConfirm={runConfirm}
            />
        </AppLayout>
    );
}
