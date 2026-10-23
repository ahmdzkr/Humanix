import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarDays, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import type { Column, Filter } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { FormField, selectClassName } from '@/components/form-field';
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
import type { RequestStatusValue } from '@/lib/requests';
import { formatRange, requestTone } from '@/lib/requests';
import { cn } from '@/lib/utils';
import { show, store } from '@/routes/leave';
import type { Paginated } from '@/types';

type Tab = 'mine' | 'approvals' | 'all';

type Row = {
    id: number;
    employee_name: string | null;
    employee_nik: string | null;
    department: string | null;
    type: string | null;
    start_date: string;
    end_date: string;
    days: number;
    status: RequestStatusValue;
    status_label: string;
};

type Props = {
    tabs: Tab[];
    filters: {
        tab: Tab;
        search: string;
        status: string;
        type: string;
        department: string;
    };
    requests: Paginated<Row>;
    awaitingCount: number;
    balances: {
        type: string;
        year: number;
        quota: number;
        used: number;
        pending: number;
        remaining: number;
    }[];
    leaveTypes: {
        id: number;
        name: string;
        uses_balance: boolean;
        requires_attachment: boolean;
    }[];
    statusOptions: { value: string; label: string }[];
    departmentOptions: { id: number; name: string }[];
    canSubmit: boolean;
};

const tabLabels: Record<Tab, string> = {
    mine: 'Pengajuan Saya',
    approvals: 'Perlu Persetujuan',
    all: 'Semua Pengajuan',
};

export default function LeaveIndex({
    tabs,
    filters,
    requests,
    awaitingCount,
    balances,
    leaveTypes,
    statusOptions,
    departmentOptions,
    canSubmit,
}: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const form = useForm<{
        leave_type_id: string;
        start_date: string;
        end_date: string;
        reason: string;
        attachment: File | null;
    }>({
        leave_type_id: '',
        start_date: '',
        end_date: '',
        reason: '',
        attachment: null,
    });

    const selectedType = leaveTypes.find(
        (t) => String(t.id) === form.data.leave_type_id,
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url(), {
            forceFormData: true,
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
        { key: 'type', header: 'Jenis', cell: (r) => r.type },
        {
            key: 'date',
            header: 'Tanggal',
            cell: (r) => (
                <span>
                    <span className="block">
                        {formatRange(r.start_date, r.end_date)}
                    </span>
                    <span className="block text-xs text-text-muted">
                        {r.days} hari kerja
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
        {
            name: 'type',
            label: 'Jenis',
            options: leaveTypes.map((t) => ({
                value: String(t.id),
                label: t.name,
            })),
        },
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
                { title: 'Cuti/Izin', href: '/cuti-izin' },
            ]}
        >
            <Head title="Cuti/Izin" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Cuti/Izin"
                    description="Pengajuan diproses berurutan: atasan langsung, lalu HRD."
                    actions={
                        canSubmit && (
                            <Button
                                className="rounded-control"
                                onClick={() => setFormOpen(true)}
                            >
                                <Plus className="size-4" />
                                Ajukan Cuti/Izin
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
                                        '/cuti-izin',
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

                {filters.tab === 'mine' && balances.length > 0 && (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {balances.map((b) => (
                            <section
                                key={b.type}
                                className="rounded-card border border-border bg-surface p-5"
                            >
                                <div className="flex items-center gap-3">
                                    <span className="flex size-10 items-center justify-center rounded-control bg-primary/10 text-primary">
                                        <CalendarDays className="size-5" />
                                    </span>
                                    <div>
                                        <p className="text-sm text-text-muted">
                                            Sisa {b.type} {b.year}
                                        </p>
                                        <p className="text-2xl font-bold text-navy">
                                            {b.remaining}{' '}
                                            <span className="text-sm font-normal text-text-muted">
                                                / {b.quota} hari
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <div className="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full bg-primary"
                                        style={{
                                            width: `${b.quota ? (b.remaining / b.quota) * 100 : 0}%`,
                                        }}
                                    />
                                </div>
                                <p className="mt-2 text-xs text-text-muted">
                                    Terpakai {b.used} hari
                                    {b.pending > 0 &&
                                        ` · ${b.pending} hari menunggu persetujuan`}
                                </p>
                            </section>
                        ))}
                    </div>
                )}

                <DataTable
                    rows={requests}
                    columns={columns}
                    filters={{
                        tab: filters.tab,
                        search: filters.search,
                        status: filters.status,
                        type: filters.type,
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
                            ? 'Tidak ada pengajuan yang menunggu Anda'
                            : 'Belum ada pengajuan'
                    }
                    emptyDescription={
                        filters.tab === 'mine'
                            ? 'Ajukan cuti atau izin dengan tombol di kanan atas.'
                            : 'Pengajuan akan muncul di sini.'
                    }
                />
            </div>

            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="rounded-card sm:max-w-lg">
                    <form onSubmit={submit} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle className="text-navy">
                                Ajukan Cuti/Izin
                            </DialogTitle>
                            <DialogDescription>
                                Jumlah hari dihitung otomatis dari hari kerja
                                (tanpa akhir pekan dan hari libur).
                            </DialogDescription>
                        </DialogHeader>
                        <FormField
                            id="leave_type_id"
                            label="Jenis"
                            required
                            error={form.errors.leave_type_id}
                        >
                            <select
                                id="leave_type_id"
                                value={form.data.leave_type_id}
                                onChange={(e) =>
                                    form.setData(
                                        'leave_type_id',
                                        e.target.value,
                                    )
                                }
                                className={selectClassName}
                            >
                                <option value="">— Pilih —</option>
                                {leaveTypes.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.name}
                                    </option>
                                ))}
                            </select>
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
                                    onChange={(e) => {
                                        form.setData((d) => ({
                                            ...d,
                                            start_date: e.target.value,
                                            end_date:
                                                d.end_date &&
                                                d.end_date >= e.target.value
                                                    ? d.end_date
                                                    : e.target.value,
                                        }));
                                    }}
                                />
                            </FormField>
                            <FormField
                                id="end_date"
                                label="Sampai"
                                required
                                error={form.errors.end_date}
                            >
                                <Input
                                    id="end_date"
                                    type="date"
                                    min={form.data.start_date}
                                    value={form.data.end_date}
                                    onChange={(e) =>
                                        form.setData('end_date', e.target.value)
                                    }
                                />
                            </FormField>
                        </div>
                        <FormField
                            id="reason"
                            label="Alasan"
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
                        <FormField
                            id="attachment"
                            label="Lampiran"
                            required={selectedType?.requires_attachment}
                            hint="PDF/JPG/PNG, maks. 2 MB (mis. surat dokter)."
                            error={form.errors.attachment}
                        >
                            <Input
                                id="attachment"
                                type="file"
                                accept="application/pdf,image/jpeg,image/png"
                                onChange={(e) =>
                                    form.setData(
                                        'attachment',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
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
