import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { AuditTabs } from '@/components/audit-tabs';
import type { Column, FilterOption } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { selectClassName } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import type { StatusTone } from '@/components/status-badge';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { formatJam, formatTanggal } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index } from '@/routes/audit';
import type { Paginated } from '@/types';

type Log = {
    id: number;
    actor: string;
    event: 'created' | 'updated' | 'deleted';
    event_label: string;
    module: string;
    record_id: number | null;
    changes: { field: string; old: string | null; new: string | null }[];
    ip: string | null;
    at: string | null;
};

type Filters = {
    user: string;
    module: string;
    event: string;
    from: string;
    to: string;
};

type Props = {
    logs: Paginated<Log>;
    filters: Filters;
    userOptions: { id: number; name: string }[];
    moduleOptions: FilterOption[];
    eventOptions: FilterOption[];
};

const eventTone: Record<Log['event'], StatusTone> = {
    created: 'success',
    updated: 'info',
    deleted: 'danger',
};

export default function AuditLogs({
    logs,
    filters,
    userOptions,
    moduleOptions,
    eventOptions,
}: Props) {
    const [detail, setDetail] = useState<Log | null>(null);

    const visit = (next: Partial<Filters>) =>
        router.get(
            index.url(),
            Object.fromEntries(
                Object.entries({ ...filters, ...next }).filter(
                    ([, v]) => v !== '',
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const columns: Column<Log>[] = [
        {
            key: 'at',
            header: 'Waktu',
            cell: (l) =>
                l.at ? (
                    <span className="whitespace-nowrap">
                        {formatTanggal(l.at)}
                        <span className="block text-xs text-text-muted">
                            {formatJam(l.at)}
                        </span>
                    </span>
                ) : (
                    '—'
                ),
        },
        {
            key: 'actor',
            header: 'Pelaku',
            cell: (l) => (
                <span className="font-medium text-navy">{l.actor}</span>
            ),
        },
        {
            key: 'event',
            header: 'Aksi',
            cell: (l) => (
                <StatusBadge tone={eventTone[l.event] ?? 'neutral'}>
                    {l.event_label}
                </StatusBadge>
            ),
        },
        {
            key: 'module',
            header: 'Modul',
            cell: (l) => (
                <span>
                    {l.module}
                    {l.record_id && (
                        <span className="text-text-muted"> #{l.record_id}</span>
                    )}
                </span>
            ),
        },
        {
            key: 'changes',
            header: 'Perubahan',
            cell: (l) =>
                l.changes.length === 0 ? (
                    <span className="text-text-muted">—</span>
                ) : (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setDetail(l)}
                    >
                        {l.changes.length} field
                    </Button>
                ),
        },
    ];

    return (
        <AppLayout breadcrumbs={[{ title: 'Audit Log', href: index.url() }]}>
            <Head title="Audit Log" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Audit Log"
                    description="Jejak perubahan data: siapa, kapan, dan nilai sebelum/sesudah. Kata sandi, token, dan nomor rekening tidak pernah ditampilkan."
                />
                <AuditTabs active="audit" />
                <DataTable
                    rows={logs}
                    columns={columns}
                    filters={filters}
                    searchable={false}
                    rowKey={(l) => l.id}
                    filterDefs={[
                        {
                            name: 'user',
                            label: 'Pelaku',
                            options: [
                                { value: 'system', label: 'Sistem' },
                                ...userOptions.map((u) => ({
                                    value: String(u.id),
                                    label: u.name,
                                })),
                            ],
                        },
                        {
                            name: 'module',
                            label: 'Modul',
                            options: moduleOptions,
                        },
                        { name: 'event', label: 'Aksi', options: eventOptions },
                    ]}
                    toolbar={
                        <div className="flex items-center gap-2">
                            <input
                                type="date"
                                aria-label="Dari tanggal"
                                value={filters.from}
                                max={filters.to || undefined}
                                onChange={(e) =>
                                    visit({ from: e.target.value })
                                }
                                className={cn(selectClassName, 'md:w-40')}
                            />
                            <span className="text-text-muted">–</span>
                            <input
                                type="date"
                                aria-label="Sampai tanggal"
                                value={filters.to}
                                min={filters.from || undefined}
                                onChange={(e) => visit({ to: e.target.value })}
                                className={cn(selectClassName, 'md:w-40')}
                            />
                        </div>
                    }
                    emptyTitle="Belum ada catatan audit"
                    emptyDescription="Perubahan data akan tercatat di sini."
                />
            </div>

            <Dialog
                open={detail !== null}
                onOpenChange={(open) => !open && setDetail(null)}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {detail?.event_label} {detail?.module}
                            {detail?.record_id ? ` #${detail.record_id}` : ''}
                        </DialogTitle>
                        <DialogDescription>
                            {detail?.actor}
                            {detail?.at &&
                                ` · ${formatTanggal(detail.at)} ${formatJam(detail.at)}`}
                            {detail?.ip && ` · IP ${detail.ip}`}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-[60vh] overflow-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs text-text-muted uppercase">
                                    <th className="py-2 pr-3">Field</th>
                                    <th className="py-2 pr-3">Sebelum</th>
                                    <th className="py-2">Sesudah</th>
                                </tr>
                            </thead>
                            <tbody>
                                {detail?.changes.map((c) => (
                                    <tr
                                        key={c.field}
                                        className="border-t border-border align-top"
                                    >
                                        <td className="py-2 pr-3 font-mono text-xs text-navy">
                                            {c.field}
                                        </td>
                                        <td className="py-2 pr-3 break-all text-danger">
                                            {c.old ?? (
                                                <span className="text-text-muted">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-2 break-all text-success">
                                            {c.new ?? (
                                                <span className="text-text-muted">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
