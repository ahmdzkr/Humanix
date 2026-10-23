import { Head, router } from '@inertiajs/react';
import { CalendarCheck, CalendarDays, Clock, UserX } from 'lucide-react';
import { KpiCard } from '@/components/kpi-card';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { AttendanceRow } from '@/lib/attendance';
import { sourceLabel, statusTone } from '@/lib/attendance';
import { formatTanggal } from '@/lib/format';
import { mine } from '@/routes/attendance';

type Props = {
    month: string;
    records: AttendanceRow[];
    summary: { present: number; late: number; absent: number; leave: number };
    schedule: string | null;
};

export default function MyAttendance({
    month,
    records,
    summary,
    schedule,
}: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Absensi Saya', href: mine.url() },
            ]}
        >
            <Head title="Absensi Saya" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Absensi Saya"
                    description={
                        schedule
                            ? `Jadwal kerja: ${schedule}`
                            : 'Anda belum memiliki jadwal kerja. Hubungi HRD.'
                    }
                    actions={
                        <Input
                            type="month"
                            aria-label="Bulan"
                            value={month}
                            onChange={(e) =>
                                e.target.value &&
                                router.get(
                                    mine.url(),
                                    { month: e.target.value },
                                    { preserveState: true, replace: true },
                                )
                            }
                            className="w-auto rounded-control"
                        />
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <KpiCard
                        label="Hadir"
                        value={String(summary.present + summary.late)}
                        icon={CalendarCheck}
                        tone="success"
                    />
                    <KpiCard
                        label="Terlambat"
                        value={String(summary.late)}
                        icon={Clock}
                        tone="warning"
                    />
                    <KpiCard
                        label="Tidak Hadir"
                        value={String(summary.absent)}
                        icon={UserX}
                    />
                    <KpiCard
                        label="Cuti/Izin"
                        value={String(summary.leave)}
                        icon={CalendarDays}
                        tone="cyan"
                    />
                </div>

                <section className="rounded-card border border-border bg-surface">
                    {records.length === 0 ? (
                        <p className="p-10 text-center text-sm text-text-muted">
                            Belum ada catatan absensi di bulan ini.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {records.map((r) => (
                                <li
                                    key={r.id}
                                    className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p className="font-semibold text-navy">
                                            {formatTanggal(
                                                `${r.work_date}T00:00:00+07:00`,
                                            )}
                                        </p>
                                        <p className="text-xs text-text-muted">
                                            {sourceLabel[r.source] ?? r.source}
                                            {r.note && ` · ${r.note}`}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-4">
                                        <span className="font-mono text-sm">
                                            {r.check_in ?? '—'} –{' '}
                                            {r.check_out ?? '—'}
                                        </span>
                                        <StatusBadge
                                            tone={statusTone[r.status]}
                                        >
                                            {r.status_label}
                                            {r.late_minutes > 0 &&
                                                ` ${r.late_minutes} mnt`}
                                        </StatusBadge>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
