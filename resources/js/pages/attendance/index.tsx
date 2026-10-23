import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarDays,
    Clock,
    FileSpreadsheet,
    History,
    Pencil,
    Plus,
    ScanLine,
    UserX,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import type { Column } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { FilePreviewButton } from '@/components/file-preview';
import { FormField, selectClassName } from '@/components/form-field';
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
import type { AttendanceRow } from '@/lib/attendance';
import { sourceLabel, statusTone, todayWib } from '@/lib/attendance';
import { formatTanggal } from '@/lib/format';
import { cn } from '@/lib/utils';
import { correct, kiosk } from '@/routes/attendance';
import type { Paginated } from '@/types';

type Summary = {
    present: number;
    late: number;
    absent: number;
    leave: number;
    not_yet?: number;
};

type MonthlyRow = Summary & {
    employee_id: number;
    full_name: string;
    nik: string;
    department: string | null;
    late_minutes: number;
};

type Props = {
    filters: {
        mode: 'daily' | 'monthly';
        date: string;
        month: string;
        search: string;
        department: string;
        status: string;
    };
    daily: { records: Paginated<AttendanceRow>; summary: Summary } | null;
    monthly: { rows: Paginated<MonthlyRow> } | null;
    departmentOptions: { id: number; name: string }[];
    statusOptions: { value: string; label: string }[];
    employeeOptions: { id: number; full_name: string; nik: string }[];
    can: { correct: boolean; export: boolean; kiosk: boolean };
};

type CorrectionForm = {
    employee_id: string;
    work_date: string;
    status: string;
    check_in_time: string;
    check_out_time: string;
    note: string;
    reason: string;
};

export default function AttendanceIndex({
    filters,
    daily,
    monthly,
    departmentOptions,
    statusOptions,
    employeeOptions,
    can,
}: Props) {
    const [correcting, setCorrecting] = useState<AttendanceRow | 'new' | null>(
        null,
    );
    const [history, setHistory] = useState<AttendanceRow | null>(null);
    const form = useForm<CorrectionForm>({
        employee_id: '',
        work_date: filters.date,
        status: 'present',
        check_in_time: '',
        check_out_time: '',
        note: '',
        reason: '',
    });

    const visit = (next: Partial<Props['filters']>) =>
        router.get(
            '/absensi',
            Object.fromEntries(
                Object.entries({ ...filters, ...next }).filter(
                    ([, v]) => v !== '',
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const openCorrection = (row: AttendanceRow | 'new') => {
        form.clearErrors();
        form.setData(
            row === 'new'
                ? {
                      employee_id: '',
                      work_date: filters.date,
                      status: 'present',
                      check_in_time: '',
                      check_out_time: '',
                      note: '',
                      reason: '',
                  }
                : {
                      employee_id: String(row.employee_id),
                      work_date: row.work_date,
                      status: row.status,
                      check_in_time: row.check_in ?? '',
                      check_out_time: row.check_out ?? '',
                      note: row.note ?? '',
                      reason: '',
                  },
        );
        setCorrecting(row);
    };

    const submitCorrection = (event: FormEvent) => {
        event.preventDefault();
        form.post(correct.url(), {
            preserveScroll: true,
            onSuccess: () => setCorrecting(null),
        });
    };

    const exportQuery = new URLSearchParams(
        Object.entries(filters).filter(([, v]) => v !== ''),
    ).toString();

    const dailyColumns: Column<AttendanceRow>[] = [
        {
            key: 'employee',
            header: 'Karyawan',
            cell: (row) => (
                <span>
                    <span className="block font-semibold text-navy">
                        {row.full_name}
                    </span>
                    <span className="block text-xs text-text-muted">
                        {row.nik} · {row.department ?? '—'}
                    </span>
                </span>
            ),
        },
        {
            key: 'in',
            header: 'Masuk',
            cell: (row) => (
                <span className="font-mono">{row.check_in ?? '—'}</span>
            ),
        },
        {
            key: 'out',
            header: 'Pulang',
            cell: (row) => (
                <span className="font-mono">{row.check_out ?? '—'}</span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) => (
                <span className="flex flex-col items-end gap-1 md:items-start">
                    <StatusBadge tone={statusTone[row.status]}>
                        {row.status_label}
                        {row.late_minutes > 0 && ` ${row.late_minutes} mnt`}
                    </StatusBadge>
                    <span className="text-xs text-text-muted">
                        {sourceLabel[row.source] ?? row.source}
                    </span>
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            className: 'md:text-right',
            cell: (row) => (
                <div className="flex justify-end gap-2">
                    {row.corrections.length > 0 && (
                        <Button
                            variant="outline"
                            size="sm"
                            className="rounded-control"
                            aria-label="Riwayat koreksi"
                            onClick={() => setHistory(row)}
                        >
                            <History className="size-4" />
                        </Button>
                    )}
                    {can.correct && (
                        <Button
                            variant="outline"
                            size="sm"
                            className="rounded-control"
                            onClick={() => openCorrection(row)}
                        >
                            <Pencil className="size-4" />
                            <span className="hidden lg:inline">Koreksi</span>
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    const monthlyColumns: Column<MonthlyRow>[] = [
        {
            key: 'employee',
            header: 'Karyawan',
            cell: (row) => (
                <span>
                    <span className="block font-semibold text-navy">
                        {row.full_name}
                    </span>
                    <span className="block text-xs text-text-muted">
                        {row.nik} · {row.department ?? '—'}
                    </span>
                </span>
            ),
        },
        { key: 'present', header: 'Hadir', cell: (r) => r.present + r.late },
        { key: 'late', header: 'Terlambat', cell: (r) => r.late },
        { key: 'absent', header: 'Tidak Hadir', cell: (r) => r.absent },
        { key: 'leave', header: 'Cuti/Izin', cell: (r) => r.leave },
        {
            key: 'late_minutes',
            header: 'Total Terlambat',
            cell: (r) => `${r.late_minutes} menit`,
        },
    ];

    const tableFilters = {
        search: filters.search,
        department: filters.department,
        ...(filters.mode === 'daily' ? { status: filters.status } : {}),
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Absensi', href: '/absensi' },
            ]}
        >
            <Head title="Absensi" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Absensi"
                    description="Rekap kehadiran karyawan sesuai hak akses Anda."
                    actions={
                        <>
                            {can.export && (
                                <FilePreviewButton
                                    url={`/absensi/ekspor?${exportQuery}`}
                                    kind="excel"
                                    title="Rekap Absensi"
                                    icon={FileSpreadsheet}
                                    variant="outline"
                                    className="rounded-control"
                                >
                                    Ekspor
                                </FilePreviewButton>
                            )}
                            {can.kiosk && (
                                <Button asChild className="rounded-control">
                                    <Link href={kiosk.url()}>
                                        <ScanLine className="size-4" />
                                        Buka Kios
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div
                        role="tablist"
                        className="inline-flex rounded-control border border-border bg-surface p-1"
                    >
                        {(['daily', 'monthly'] as const).map((mode) => (
                            <button
                                key={mode}
                                role="tab"
                                aria-selected={filters.mode === mode}
                                onClick={() => visit({ mode })}
                                className={cn(
                                    'rounded-lg px-4 py-2 text-sm font-medium',
                                    filters.mode === mode
                                        ? 'bg-primary text-white'
                                        : 'text-text-muted hover:text-navy',
                                )}
                            >
                                {mode === 'daily' ? 'Harian' : 'Bulanan'}
                            </button>
                        ))}
                    </div>
                    {filters.mode === 'daily' ? (
                        <Input
                            type="date"
                            aria-label="Tanggal"
                            value={filters.date}
                            max={todayWib()}
                            onChange={(e) =>
                                e.target.value &&
                                visit({ date: e.target.value })
                            }
                            className="w-auto rounded-control"
                        />
                    ) : (
                        <Input
                            type="month"
                            aria-label="Bulan"
                            value={filters.month}
                            onChange={(e) =>
                                e.target.value &&
                                visit({ month: e.target.value })
                            }
                            className="w-auto rounded-control"
                        />
                    )}
                    {filters.mode === 'daily' && can.correct && (
                        <Button
                            variant="outline"
                            className="rounded-control sm:ml-auto"
                            onClick={() => openCorrection('new')}
                        >
                            <Plus className="size-4" />
                            Catat absensi manual
                        </Button>
                    )}
                </div>

                {daily && (
                    <>
                        <p className="text-sm text-text-muted">
                            {formatTanggal(`${filters.date}T00:00:00+07:00`)}
                        </p>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <KpiCard
                                label="Hadir"
                                value={String(
                                    daily.summary.present + daily.summary.late,
                                )}
                                icon={CalendarCheck}
                                tone="success"
                                hint={`${daily.summary.late} di antaranya terlambat`}
                            />
                            <KpiCard
                                label="Terlambat"
                                value={String(daily.summary.late)}
                                icon={Clock}
                                tone="warning"
                            />
                            <KpiCard
                                label="Tidak Hadir"
                                value={String(daily.summary.absent)}
                                icon={UserX}
                                hint={
                                    daily.summary.not_yet
                                        ? `${daily.summary.not_yet} terjadwal belum absen`
                                        : undefined
                                }
                            />
                            <KpiCard
                                label="Cuti/Izin"
                                value={String(daily.summary.leave)}
                                icon={CalendarDays}
                                tone="cyan"
                            />
                        </div>
                        <DataTable
                            rows={daily.records}
                            columns={dailyColumns}
                            filters={tableFilters}
                            rowKey={(row) => row.id}
                            searchPlaceholder="Cari nama atau NIK…"
                            emptyTitle="Belum ada catatan absensi"
                            emptyDescription="Catatan muncul setelah karyawan scan di kios, atau setelah penandaan otomatis malam hari."
                            filterDefs={[
                                {
                                    name: 'department',
                                    label: 'Departemen',
                                    options: departmentOptions.map((d) => ({
                                        value: String(d.id),
                                        label: d.name,
                                    })),
                                },
                                {
                                    name: 'status',
                                    label: 'Status',
                                    options: statusOptions,
                                },
                            ]}
                        />
                    </>
                )}

                {monthly && (
                    <DataTable
                        rows={monthly.rows}
                        columns={monthlyColumns}
                        filters={tableFilters}
                        rowKey={(row) => row.employee_id}
                        searchPlaceholder="Cari nama atau NIK…"
                        emptyTitle="Belum ada karyawan"
                        filterDefs={[
                            {
                                name: 'department',
                                label: 'Departemen',
                                options: departmentOptions.map((d) => ({
                                    value: String(d.id),
                                    label: d.name,
                                })),
                            },
                        ]}
                    />
                )}
            </div>

            <Dialog
                open={correcting !== null}
                onOpenChange={(open) => !open && setCorrecting(null)}
            >
                <DialogContent className="rounded-card sm:max-w-lg">
                    <form onSubmit={submitCorrection} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle className="text-navy">
                                {correcting === 'new'
                                    ? 'Catat Absensi Manual'
                                    : `Koreksi Absensi — ${correcting?.full_name ?? ''}`}
                            </DialogTitle>
                            <DialogDescription>
                                Nilai lama dan baru beserta alasan tersimpan di
                                riwayat koreksi.
                            </DialogDescription>
                        </DialogHeader>

                        {correcting === 'new' && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    id="employee_id"
                                    label="Karyawan"
                                    required
                                    error={form.errors.employee_id}
                                >
                                    <select
                                        id="employee_id"
                                        value={form.data.employee_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'employee_id',
                                                e.target.value,
                                            )
                                        }
                                        className={selectClassName}
                                    >
                                        <option value="">— Pilih —</option>
                                        {employeeOptions.map((e) => (
                                            <option key={e.id} value={e.id}>
                                                {e.full_name} ({e.nik})
                                            </option>
                                        ))}
                                    </select>
                                </FormField>
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
                                            form.setData(
                                                'work_date',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </div>
                        )}

                        <div className="grid gap-4 sm:grid-cols-3">
                            <FormField
                                id="status"
                                label="Status"
                                required
                                error={form.errors.status}
                            >
                                <select
                                    id="status"
                                    value={form.data.status}
                                    onChange={(e) =>
                                        form.setData('status', e.target.value)
                                    }
                                    className={selectClassName}
                                >
                                    {statusOptions.map((s) => (
                                        <option key={s.value} value={s.value}>
                                            {s.label}
                                        </option>
                                    ))}
                                </select>
                            </FormField>
                            <FormField
                                id="check_in_time"
                                label="Jam masuk"
                                error={form.errors.check_in_time}
                            >
                                <Input
                                    id="check_in_time"
                                    type="time"
                                    value={form.data.check_in_time}
                                    onChange={(e) =>
                                        form.setData(
                                            'check_in_time',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="check_out_time"
                                label="Jam pulang"
                                error={form.errors.check_out_time}
                            >
                                <Input
                                    id="check_out_time"
                                    type="time"
                                    value={form.data.check_out_time}
                                    onChange={(e) =>
                                        form.setData(
                                            'check_out_time',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </div>

                        <FormField
                            id="reason"
                            label="Alasan koreksi"
                            required
                            hint="Contoh: lupa scan, kamera kios rusak, dinas luar."
                            error={form.errors.reason}
                        >
                            <Input
                                id="reason"
                                value={form.data.reason}
                                maxLength={255}
                                onChange={(e) =>
                                    form.setData('reason', e.target.value)
                                }
                            />
                        </FormField>

                        <DialogFooter className="gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="rounded-control"
                                onClick={() => setCorrecting(null)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                className="rounded-control"
                                disabled={form.processing}
                            >
                                {form.processing && <Spinner />}
                                Simpan koreksi
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={history !== null}
                onOpenChange={(open) => !open && setHistory(null)}
            >
                <DialogContent className="rounded-card sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="text-navy">
                            Riwayat koreksi — {history?.full_name}
                        </DialogTitle>
                    </DialogHeader>
                    <ol className="max-h-[60vh] space-y-4 overflow-y-auto">
                        {history?.corrections.map((c, i) => (
                            <li
                                key={i}
                                className="border-l-2 border-primary/20 pl-4 text-sm"
                            >
                                <p className="font-semibold text-navy">
                                    {c.actor}
                                </p>
                                <p className="text-text-muted italic">
                                    “{c.reason}”
                                </p>
                                <ul className="mt-1">
                                    {Object.entries(c.new).map(([k, v]) => (
                                        <li key={k}>
                                            <span className="text-text-muted">
                                                {k}:
                                            </span>{' '}
                                            {c.old && c.old[k] !== v && (
                                                <>
                                                    <span className="line-through">
                                                        {c.old[k] ?? '—'}
                                                    </span>{' '}
                                                    →{' '}
                                                </>
                                            )}
                                            {v ?? '—'}
                                        </li>
                                    ))}
                                </ul>
                            </li>
                        ))}
                    </ol>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
