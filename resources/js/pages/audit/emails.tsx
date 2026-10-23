import { Head } from '@inertiajs/react';
import { AuditTabs } from '@/components/audit-tabs';
import type { Column } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import type { StatusTone } from '@/components/status-badge';
import { StatusBadge } from '@/components/status-badge';
import AppLayout from '@/layouts/app-layout';
import { formatJam, formatTanggal } from '@/lib/format';
import { emails, index } from '@/routes/audit';
import type { Paginated } from '@/types';

type Delivery = {
    id: number;
    recipient: string;
    category: string;
    subject: string;
    status: 'queued' | 'sent' | 'failed' | 'skipped';
    status_label: string;
    attempts: number;
    error: string | null;
    at: string | null;
    sent_at: string | null;
};

type Props = {
    deliveries: Paginated<Delivery>;
    filters: { status: string };
    counts: Partial<Record<Delivery['status'], number>>;
};

const tone: Record<Delivery['status'], StatusTone> = {
    queued: 'warning',
    sent: 'success',
    failed: 'danger',
    skipped: 'neutral',
};

const labels: Record<Delivery['status'], string> = {
    queued: 'Dalam antrean',
    sent: 'Terkirim',
    failed: 'Gagal',
    skipped: 'Tidak dikirim',
};

export default function EmailLog({ deliveries, filters, counts }: Props) {
    const columns: Column<Delivery>[] = [
        {
            key: 'at',
            header: 'Waktu',
            cell: (d) =>
                d.at ? (
                    <span className="whitespace-nowrap">
                        {formatTanggal(d.at)}
                        <span className="block text-xs text-text-muted">
                            {formatJam(d.at)}
                        </span>
                    </span>
                ) : (
                    '—'
                ),
        },
        {
            key: 'recipient',
            header: 'Penerima',
            cell: (d) => <span className="break-all">{d.recipient}</span>,
        },
        {
            key: 'subject',
            header: 'Subjek',
            cell: (d) => (
                <span>
                    {d.subject}
                    <span className="block text-xs text-text-muted">
                        {d.category}
                    </span>
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (d) => (
                <span className="flex flex-col items-end gap-1 md:items-start">
                    <StatusBadge tone={tone[d.status]}>
                        {d.status_label}
                    </StatusBadge>
                    {d.attempts > 0 && (
                        <span className="text-xs text-text-muted">
                            {d.attempts}× percobaan
                        </span>
                    )}
                    {d.error && (
                        <span className="max-w-xs text-xs break-words text-danger">
                            {d.error}
                        </span>
                    )}
                </span>
            ),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Audit Log', href: index.url() },
                { title: 'Pengiriman Email', href: emails.url() },
            ]}
        >
            <Head title="Log Pengiriman Email" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Audit Log"
                    description="Status email notifikasi apa adanya. Email yang gagal tetap tercatat gagal beserta pesan galatnya."
                />
                <AuditTabs active="emails" />
                <DataTable
                    rows={deliveries}
                    columns={columns}
                    filters={filters}
                    searchable={false}
                    rowKey={(d) => d.id}
                    filterDefs={[
                        {
                            name: 'status',
                            label: 'Status',
                            options: (
                                Object.keys(labels) as Delivery['status'][]
                            ).map((s) => ({
                                value: s,
                                label: `${labels[s]} (${counts[s] ?? 0})`,
                            })),
                        },
                    ]}
                    emptyTitle="Belum ada email notifikasi"
                    emptyDescription="Email tercatat di sini setelah SMTP dikonfigurasi dan notifikasi terkirim."
                />
            </div>
        </AppLayout>
    );
}
