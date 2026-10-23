import { Head, Link, router, useForm } from '@inertiajs/react';
import { Clock, Hourglass, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import type { Column, Filter } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { FormField } from '@/components/form-field';
import { KpiCard } from '@/components/kpi-card';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
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
import { todayWib } from '@/lib/attendance';
import type { RequestStatusValue } from '@/lib/requests';
import { formatDuration, formatRange, requestTone } from '@/lib/requests';
import { cn } from '@/lib/utils';
import { show, store } from '@/routes/overtime';
import type { Paginated } from '@/types';

type Tab = 'mine' | 'approvals' | 'all';

type Row = {
    id: number;
    employee_name: string | null;
    employee_nik: string | null;
    department: string | null;
    work_date: string;
    start_time: string;
    end_time: string;
    duration_minutes: number;
    status: RequestStatusValue;
    status_label: string;
};

type Props = {
    tabs: Tab[];
    filters: { tab: Tab; search: string; status: string; department: string };
    requests: Paginated<Row>;
    awaitingCount: number;
    summary: { approved_minutes: number; pending_minutes: number } | null;
    maxMinutesPerDay: number;
    statusOptions: { value: string; label: string }[];
    departmentOptions: { id: number; name: string }[];
    canSubmit: boolean;
};

const tabLabels: Record<Tab, string> = {
    mine: 'Pengajuan Saya',
    approvals: 'Perlu Persetujuan',
    all: 'Semua Pengajuan',
};

export default function OvertimeIndex({
    tabs,
    filters,
    requests,
    awaitingCount,
    summary,
    maxMinutesPerDay,
    statusOptions,
    departmentOptions,
    canSubmit,
}: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const form = useForm({
        work_date: todayWib(),
        start_time: '17:00',
        end_time: '19:00',
        reason: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url(), {
            onSuccess: () => {
                setFormOpen(false);
                form.reset();
            },
        });
    };

    const columns: Column<Row>[] = [
        ...(filters.tab === 'mine'
            ? []
            : [
                  {
                      key: 'employee',
                      header: 'Karyawan',
                      cell: (r: Row) => (
                          <span>
                              <span className="block font-semibold text-navy">
                                  {r.employee_name}
                              </span>
                              <span className="block text-xs text-text-muted">
                                  {r.employee_nik} · {r.department ?? '—'}
                              </span>
                          </span>
                      ),
                  },
              ]),
        {
            key: 'date',
            header: 'Tanggal',
            cell: (r) => formatRange(r.work_date, r.work_date),
        },
        {
            key: 'time',
            header: 'Jam',
            cell: (r) => (
                <span>
                    <span className="block font-mono">
                        {r.start_time}–{r.end_time}
                    </span>
                    <span className="block text-xs text-text-muted">
                        {formatDuration(r.duration_minutes)}
                    </span>
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (r) => (
                <StatusBadge tone={requestTone[r.status]}>
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
                    <Link href={show.url(r.id)}>
                        {filters.tab === 'approvals' ? 'Proses' : 'Lihat'}
                    </Link>
                </Button>
            ),
        },
    ];

    const filterDefs: Filter[] = [
        ...(filters.tab === 'approvals'
            ? []
            : [{ name: 'status', label: 'Status', options: statusOptions }]),
        ...(filters.tab === 'all'
            ? [
                  {
                      name: 'department',
                      label: 'Departemen',
                      options: departmentOptions.map((d) => ({
                          value: String(d.id),
                          label: d.name,
                      })),
                  },
              ]
            : []),
    ];

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Lembur', href: '/lembur' },
            ]}
        >
            <Head title="Lembur" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Lembur"
                    description={`Lembur di luar jam kerja, maksimal ${formatDuration(maxMinutesPerDay)} per hari. Lembur yang disetujui dihitung di payroll.`}
                    actions={
                        canSubmit && (
                            <Button
                                className="rounded-control"
                                onClick={() => setFormOpen(true)}
                            >
                                <Plus className="size-4" />
                                Ajukan Lembur
                            </Button>
                        )
                    }
                />

                {tabs.length > 1 && (
                    <div
                        role="tablist"
                        className="inline-flex w-fit flex-wrap rounded-control border border-border bg-surface p-1"
                    >
                        {tabs.map((tab) => (
                            <button
                                key={tab}
                                role="tab"
                                aria-selected={filters.tab === tab}
                                onClick={() =>
                                    router.get(
                                        '/lembur',
                                        { tab },
                                        { preserveState: false },
                                    )
                                }
                                className={cn(
                                    'flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium',
                                    filters.tab === tab
                                        ? 'bg-primary text-white'
                                        : 'text-text-muted hover:text-navy',
                                )}
                            >
                                {tabLabels[tab]}
                                {tab === 'approvals' && awaitingCount > 0 && (
                                    <span
                                        className={cn(
                                            'rounded-full px-2 text-xs',
                                            filters.tab === tab
                                                ? 'bg-white text-primary'
                                                : 'bg-danger text-white',
                                        )}
                                    >
                                        {awaitingCount}
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>
                )}

                {filters.tab === 'mine' && summary && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <KpiCard
                            label="Lembur disetujui bulan ini"
                            value={formatDuration(summary.approved_minutes)}
                            icon={Clock}
                            tone="success"
                        />
                        <KpiCard
                            label="Menunggu persetujuan"
                            value={formatDuration(summary.pending_minutes)}
                            icon={Hourglass}
                            tone="warning"
                        />
                    </div>
                )}

                <DataTable
                    rows={requests}
                    columns={columns}
                    filters={{
                        tab: filters.tab,
                        search: filters.search,
                        status: filters.status,
                        department: filters.department,
                    }}
                    filterDefs={filterDefs}
                    rowKey={(r) => r.id}
                    searchPlaceholder={
                        filters.tab === 'mine'
                            ? 'Cari…'
                            : 'Cari nama atau NIK karyawan…'
                    }
                    emptyTitle={
                        filters.tab === 'approvals'
                            ? 'Tidak ada lembur yang menunggu Anda'
                            : 'Belum ada pengajuan lembur'
                    }
                />
            </div>

            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="rounded-card sm:max-w-md">
                    <form onSubmit={submit} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle className="text-navy">
                                Ajukan Lembur
                            </DialogTitle>
                            <DialogDescription>
                                Jam dalam WIB. Lembur lintas tengah malam belum
                                didukung.
                            </DialogDescription>
                        </DialogHeader>
                        <FormField
                            id="work_date"
                            label="Tanggal"
                            required
                            error={form.errors.work_date}
                        >
                            <Input
                                id="work_date"
                                type="date"
                                value={form.data.work_date}
                                onChange={(e) =>
                                    form.setData('work_date', e.target.value)
                                }
                            />
                        </FormField>
                        <div className="grid grid-cols-2 gap-4">
                            <FormField
                                id="start_time"
                                label="Mulai"
                                required
                                error={form.errors.start_time}
                            >
                                <Input
                                    id="start_time"
                                    type="time"
                                    value={form.data.start_time}
                                    onChange={(e) =>
                                        form.setData(
                                            'start_time',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="end_time"
                                label="Selesai"
                                required
                                error={form.errors.end_time}
                            >
                                <Input
                                    id="end_time"
                                    type="time"
                                    value={form.data.end_time}
                                    onChange={(e) =>
                                        form.setData('end_time', e.target.value)
                                    }
                                />
                            </FormField>
                        </div>
                        <FormField
                            id="reason"
                            label="Alasan / pekerjaan"
                            required
                            error={form.errors.reason}
                        >
                            <textarea
                                id="reason"
                                rows={3}
                                value={form.data.reason}
                                maxLength={1000}
                                onChange={(e) =>
                                    form.setData('reason', e.target.value)
                                }
                                className="w-full rounded-control border border-input bg-surface px-3 py-2 text-sm focus:border-primary-bright focus:ring-2 focus:ring-primary-bright/20 focus:outline-none"
                            />
                        </FormField>
                        <DialogFooter className="gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="rounded-control"
                                onClick={() => setFormOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                className="rounded-control"
                                disabled={form.processing}
                            >
                                {form.processing && <Spinner />}
                                Kirim pengajuan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
