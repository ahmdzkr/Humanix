import { Head, router } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    CalendarCheck,
    CalendarDays,
    Clock,
    Eye,
    FileSpreadsheet,
    Users,
    Wallet,
} from 'lucide-react';
import { EmptyState, formatAngka } from '@/components/charts/dashboard-widgets';
import { FilePreviewButton } from '@/components/file-preview';
import { KpiCard } from '@/components/kpi-card';
import { PageHeader } from '@/components/page-header';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/reports';

type Row = {
    nik: string;
    name: string;
    department: string | null;
    leave_days: number;
    leave_requests: number;
    overtime_hours: number;
    overtime_requests: number;
};

type Props = {
    filters: { month: string };
    scopeLabel: string;
    summary: {
        headcount: number;
        attendance: {
            days: number;
            scheduled: number;
            present: number;
            late: number;
            absent: number;
            leave: number;
            rate: number;
        };
        leave_days: number;
        overtime_hours: number;
    };
    rows: Row[];
    downloads: {
        leave_overtime: string;
        employees: string | null;
        attendance: string | null;
    };
    payrollPeriods: {
        id: number;
        label: string;
        status: string;
        url: string;
    }[];
};

function DownloadCard({
    icon: Icon,
    title,
    description,
    href,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
    href: string;
}) {
    return (
        <div className="flex items-start gap-3 rounded-card border border-border bg-surface p-4">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-control bg-success/10 text-success">
                <Icon className="size-5" />
            </span>
            <div className="min-w-0 flex-1">
                <p className="font-semibold text-navy">{title}</p>
                <p className="text-sm text-text-muted">{description}</p>
            </div>
            <FilePreviewButton
                url={href}
                kind="excel"
                title={title}
                icon={Eye}
                variant="outline"
                size="sm"
                className="shrink-0"
            >
                Lihat
            </FilePreviewButton>
        </div>
    );
}

export default function Reports({
    filters,
    scopeLabel,
    summary,
    rows,
    downloads,
    payrollPeriods,
}: Props) {
    const monthLabel = new Date(
        `${filters.month}-01T00:00:00+07:00`,
    ).toLocaleDateString('id-ID', {
        month: 'long',
        year: 'numeric',
        timeZone: 'Asia/Jakarta',
    });
    const a = summary.attendance;

    return (
        <AppLayout breadcrumbs={[{ title: 'Laporan', href: index.url() }]}>
            <Head title="Laporan" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Laporan"
                    description={`${scopeLabel} · ${monthLabel}`}
                    actions={
                        <Input
                            type="month"
                            aria-label="Bulan laporan"
                            value={filters.month}
                            onChange={(e) =>
                                e.target.value &&
                                router.get(
                                    index.url(),
                                    { month: e.target.value },
                                    { preserveState: true, replace: true },
                                )
                            }
                            className="w-44 rounded-control"
                        />
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <KpiCard
                        label="Karyawan Aktif"
                        value={formatAngka(summary.headcount)}
                        icon={Users}
                        image="/images/total-karyawan.png"
                    />
                    <KpiCard
                        label="Tingkat Kehadiran"
                        value={
                            a.days > 0
                                ? `${a.rate.toLocaleString('id-ID')}%`
                                : '—'
                        }
                        icon={CalendarCheck}
                        image="/images/hadir.png"
                        tone="success"
                        hint={
                            a.days > 0
                                ? `${a.present} hadir (${a.late} terlambat) · ${a.absent} tidak hadir`
                                : 'Bulan ini belum berjalan'
                        }
                    />
                    <KpiCard
                        label="Hari Cuti/Izin Disetujui"
                        value={formatAngka(summary.leave_days)}
                        icon={CalendarDays}
                        image="/images/cuti.png"
                        tone="warning"
                    />
                    <KpiCard
                        label="Jam Lembur Disetujui"
                        value={`${summary.overtime_hours.toLocaleString('id-ID')} jam`}
                        icon={Clock}
                        image="/images/lembur.png"
                        tone="violet"
                    />
                </div>

                <section className="grid gap-3">
                    <h2 className="text-lg font-semibold text-navy">
                        Unduh Laporan
                    </h2>
                    <div className="grid gap-3 lg:grid-cols-2">
                        <DownloadCard
                            icon={FileSpreadsheet}
                            title="Rekap Cuti & Lembur"
                            description={`Per karyawan, ${monthLabel}.`}
                            href={downloads.leave_overtime}
                        />
                        {downloads.attendance && (
                            <DownloadCard
                                icon={CalendarCheck}
                                title="Rekap Absensi Bulanan"
                                description={`Hadir, terlambat, tidak hadir, cuti per karyawan, ${monthLabel}.`}
                                href={downloads.attendance}
                            />
                        )}
                        {downloads.employees && (
                            <DownloadCard
                                icon={Users}
                                title="Data Karyawan"
                                description="Seluruh karyawan (tanpa nomor rekening)."
                                href={downloads.employees}
                            />
                        )}
                        {payrollPeriods.map((p) => (
                            <DownloadCard
                                key={p.id}
                                icon={Wallet}
                                title={`Payroll ${p.label}`}
                                description={`Status: ${p.status}`}
                                href={p.url}
                            />
                        ))}
                    </div>
                </section>

                <section className="rounded-card border border-border bg-surface">
                    <h2 className="border-b border-border px-5 py-4 font-semibold text-navy">
                        Rekap Cuti & Lembur per Karyawan
                    </h2>
                    {rows.length === 0 ? (
                        <div className="p-5">
                            <EmptyState>
                                Tidak ada karyawan dalam cakupan laporan ini.
                            </EmptyState>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[640px] text-sm">
                                <thead>
                                    <tr className="bg-muted/60 text-left text-xs font-semibold tracking-wide text-text-muted uppercase">
                                        <th className="px-5 py-3">Karyawan</th>
                                        <th className="px-3 py-3">
                                            Departemen
                                        </th>
                                        <th className="px-3 py-3 text-right">
                                            Hari Cuti
                                        </th>
                                        <th className="px-3 py-3 text-right">
                                            Pengajuan Cuti
                                        </th>
                                        <th className="px-3 py-3 text-right">
                                            Jam Lembur
                                        </th>
                                        <th className="px-5 py-3 text-right">
                                            Pengajuan Lembur
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((r) => (
                                        <tr
                                            key={r.nik}
                                            className="border-t border-border"
                                        >
                                            <td className="px-5 py-3">
                                                <span className="font-medium text-navy">
                                                    {r.name}
                                                </span>
                                                <span className="block text-xs text-text-muted">
                                                    {r.nik}
                                                </span>
                                            </td>
                                            <td className="px-3 py-3">
                                                {r.department ?? '—'}
                                            </td>
                                            <td className="px-3 py-3 text-right">
                                                {r.leave_days}
                                            </td>
                                            <td className="px-3 py-3 text-right">
                                                {r.leave_requests}
                                            </td>
                                            <td className="px-3 py-3 text-right">
                                                {r.overtime_hours.toLocaleString(
                                                    'id-ID',
                                                )}
                                            </td>
                                            <td className="px-5 py-3 text-right">
                                                {r.overtime_requests}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
