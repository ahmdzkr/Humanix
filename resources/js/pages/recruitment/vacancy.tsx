import { Head, Link } from '@inertiajs/react';
import type { Column } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatTanggal } from '@/lib/format';
import type { ApplicationStatusValue } from '@/lib/recruitment';
import { applicationTone } from '@/lib/recruitment';
import { formatRange } from '@/lib/requests';
import applications from '@/routes/recruitment/applications';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    full_name: string;
    email: string;
    phone: string;
    last_education: string | null;
    status: ApplicationStatusValue;
    status_label: string;
    applied_at: string | null;
};

type Props = {
    vacancy: {
        id: number;
        title: string;
        status_label: string;
        quota: number;
        filled: number;
        closed_reason: 'quota' | 'manual' | null;
        deadline: string;
        is_open: boolean;
    };
    applications: Paginated<Row>;
    filters: { search: string; status: string };
    counts: Record<string, number>;
    statusOptions: { value: ApplicationStatusValue; label: string }[];
};

export default function VacancyShow({
    vacancy,
    applications: rows,
    filters,
    counts,
    statusOptions,
}: Props) {
    const columns: Column<Row>[] = [
        {
            key: 'name',
            header: 'Pelamar',
            cell: (r) => (
                <Link
                    href={applications.show.url(r.id)}
                    className="block text-left"
                >
                    <span className="block font-semibold text-navy hover:text-primary">
                        {r.full_name}
                    </span>
                    <span className="block text-xs text-text-muted">
                        {r.email} · {r.phone}
                    </span>
                </Link>
            ),
        },
        {
            key: 'education',
            header: 'Pendidikan',
            cell: (r) => r.last_education ?? '—',
        },
        {
            key: 'applied',
            header: 'Melamar',
            cell: (r) => (r.applied_at ? formatTanggal(r.applied_at) : '—'),
        },
        {
            key: 'status',
            header: 'Tahap',
            cell: (r) => (
                <StatusBadge tone={applicationTone[r.status]}>
                    {r.status_label}
                </StatusBadge>
            ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            className: 'md:text-right',
            cell: (r) => (
                <Button
                    asChild
                    variant="outline"
                    size="sm"
                    className="rounded-control"
                >
                    <Link href={applications.show.url(r.id)}>Proses</Link>
                </Button>
            ),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Rekruitmen', href: '/rekruitmen' },
                { title: vacancy.title, href: '#' },
            ]}
        >
            <Head title={`Pelamar ${vacancy.title}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={vacancy.title}
                    description={`${vacancy.status_label} · kuota ${vacancy.filled}/${vacancy.quota} diterima · tenggat ${formatRange(vacancy.deadline, vacancy.deadline)}`}
                />
                {vacancy.closed_reason === 'quota' && (
                    <p
                        className="rounded-control border border-border bg-surface px-4 py-3 text-sm text-text-muted"
                        data-testid="quota-closed"
                    >
                        <StatusBadge tone="neutral">
                            Ditutup otomatis: kuota terpenuhi
                        </StatusBadge>{' '}
                        Lowongan tidak tampil lagi di halaman karier dan tidak
                        menerima lamaran baru. Pelamar yang masih diproses tidak
                        diubah.
                    </p>
                )}
                <div className="flex flex-wrap gap-2">
                    {statusOptions.map((s) => (
                        <span
                            key={s.value}
                            className="rounded-full border border-border bg-surface px-3 py-1 text-sm"
                        >
                            {s.label}:{' '}
                            <strong className="text-navy">
                                {counts[s.value] ?? 0}
                            </strong>
                        </span>
                    ))}
                </div>
                <DataTable
                    rows={rows}
                    columns={columns}
                    filters={filters}
                    filterDefs={[
                        {
                            name: 'status',
                            label: 'Tahap',
                            options: statusOptions,
                        },
                    ]}
                    rowKey={(r) => r.id}
                    searchPlaceholder="Cari nama atau email pelamar…"
                    emptyTitle="Belum ada pelamar"
                    emptyDescription="Lamaran dari halaman karier akan muncul di sini."
                />
            </div>
        </AppLayout>
    );
}
