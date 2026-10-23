import { Link } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarDays,
    Clock,
    IdCard,
    Star,
    Wallet,
} from 'lucide-react';
import { BarList, SERIES } from '@/components/charts/chart-kit';
import { EmptyState, Panel } from '@/components/charts/dashboard-widgets';
import { FilePreviewButton } from '@/components/file-preview';
import { KpiCard } from '@/components/kpi-card';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import type { AttendanceStatusValue } from '@/lib/attendance';
import { statusTone } from '@/lib/attendance';
import { formatUang } from '@/lib/format';
import { formatScore } from '@/lib/performance';
import { mine as attendanceMine } from '@/routes/attendance';
import { idCard } from '@/routes/my-profile';
import { mySlips } from '@/routes/payroll';
import records from '@/routes/payroll/records';
import reviews from '@/routes/performance/reviews';

export type Personal = {
    attendance: {
        scheduled: boolean;
        schedule: string | null;
        status: AttendanceStatusValue | null;
        status_label: string | null;
        check_in: string | null;
        check_out: string | null;
        on_leave: boolean;
    };
    leave_balance: { type: string; quota: number; remaining: number } | null;
    overtime_minutes_this_month: number;
    pending: { leave: number; overtime: number };
    latest_slip: { id: number; period: string; net_pay: string } | null;
    latest_review: {
        id: number;
        period: string;
        score: string | null;
        rating: string | null;
    } | null;
    month_attendance: Partial<Record<AttendanceStatusValue, number>>;
};

const monthLabels: Record<AttendanceStatusValue, string> = {
    present: 'Hadir tepat waktu',
    late: 'Terlambat',
    absent: 'Tidak hadir',
    leave: 'Cuti/Izin',
};

function todayText(a: Personal['attendance']): string {
    if (a.on_leave) {
        return 'Anda sedang cuti/izin hari ini';
    }

    if (!a.scheduled) {
        return 'Tidak ada jadwal kerja hari ini';
    }

    if (a.check_in === null) {
        return 'Belum absen masuk';
    }

    return a.check_out
        ? `Masuk ${a.check_in} · Pulang ${a.check_out}`
        : `Masuk ${a.check_in} · belum absen pulang`;
}

function formatDuration(minutes: number): string {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;

    return h > 0 ? `${h} jam${m ? ` ${m} mnt` : ''}` : `${m} mnt`;
}

/** Ringkasan pribadi untuk Karyawan (dan Manager di bagian "Data Saya"). */
export function PersonalKpis({ personal }: { personal: Personal }) {
    const a = personal.attendance;

    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <KpiCard
                label="Absensi Hari Ini"
                image="/images/hadir.png"
                tone="success"
                value={
                    a.status_label ??
                    (a.on_leave
                        ? 'Cuti'
                        : a.scheduled
                          ? 'Belum absen'
                          : 'Libur')
                }
                icon={CalendarCheck}
                hint={todayText(a)}
            />
            <KpiCard
                label={
                    personal.leave_balance
                        ? `Sisa ${personal.leave_balance.type}`
                        : 'Sisa Cuti'
                }
                value={
                    personal.leave_balance
                        ? `${personal.leave_balance.remaining} hari`
                        : '—'
                }
                icon={CalendarDays}
                image="/images/cuti.png"
                tone="warning"
                hint={
                    personal.leave_balance
                        ? `Kuota ${personal.leave_balance.quota} hari · ${personal.pending.leave} pengajuan menunggu`
                        : 'Belum ada jenis cuti berkuota'
                }
            />
            <KpiCard
                label="Lembur Bulan Ini"
                image="/images/lembur.png"
                value={formatDuration(personal.overtime_minutes_this_month)}
                icon={Clock}
                tone="violet"
                hint={`Disetujui · ${personal.pending.overtime} pengajuan menunggu`}
            />
            <KpiCard
                label="Gaji Terakhir"
                value={
                    personal.latest_slip
                        ? formatUang(personal.latest_slip.net_pay)
                        : '—'
                }
                icon={Wallet}
                tone="success"
                hint={
                    personal.latest_slip
                        ? `Gaji bersih · ${personal.latest_slip.period}`
                        : 'Belum ada slip yang diterbitkan'
                }
            />
        </div>
    );
}

export function PersonalPanels({ personal }: { personal: Personal }) {
    const a = personal.attendance;
    const month = (Object.keys(monthLabels) as AttendanceStatusValue[]).map(
        (s) => ({
            name: monthLabels[s],
            total: personal.month_attendance[s] ?? 0,
        }),
    );

    return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Panel title="Absensi Hari Ini" icon={IdCard}>
                <div className="flex flex-1 flex-col gap-3 text-sm">
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-text-muted">Jadwal</span>
                        <span className="text-right font-medium">
                            {a.schedule ?? 'Belum ada jadwal'}
                        </span>
                    </div>
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-text-muted">Status</span>
                        {a.status ? (
                            <StatusBadge tone={statusTone[a.status]}>
                                {a.status_label}
                            </StatusBadge>
                        ) : (
                            <StatusBadge tone="neutral">
                                {a.on_leave
                                    ? 'Cuti/Izin'
                                    : a.scheduled
                                      ? 'Belum absen'
                                      : 'Libur'}
                            </StatusBadge>
                        )}
                    </div>
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-text-muted">Masuk / Pulang</span>
                        <span className="font-medium">
                            {a.check_in ?? '—'} / {a.check_out ?? '—'}
                        </span>
                    </div>
                    <p className="rounded-lg bg-primary/5 p-3 text-xs text-text-muted">
                        Untuk absen, tunjukkan QR di kartu ID Anda ke perangkat
                        kiosk di kantor.
                    </p>
                    <div className="mt-auto flex flex-wrap gap-2">
                        <FilePreviewButton
                            url={idCard.url()}
                            kind="pdf"
                            title="ID Card Saya"
                            icon={IdCard}
                            size="sm"
                        >
                            Tampilkan QR Absen
                        </FilePreviewButton>
                        <Button asChild size="sm" variant="outline">
                            <Link href={attendanceMine.url()}>Riwayat</Link>
                        </Button>
                    </div>
                </div>
            </Panel>

            <Panel title="Kehadiran Bulan Ini" icon={CalendarCheck}>
                <BarList
                    items={month}
                    color={SERIES[0]}
                    empty="Belum ada catatan kehadiran bulan ini."
                />
            </Panel>

            <div className="flex flex-col gap-4">
                <Panel
                    title="Slip Gaji Terakhir"
                    icon={Wallet}
                    action={
                        <Link
                            href={mySlips.url()}
                            className="text-sm font-medium text-primary hover:underline"
                        >
                            Semua
                        </Link>
                    }
                >
                    {personal.latest_slip ? (
                        <Link
                            href={records.show.url(personal.latest_slip.id)}
                            className="block rounded-lg bg-muted/60 p-3 hover:bg-muted"
                        >
                            <p className="text-xs text-text-muted">
                                {personal.latest_slip.period}
                            </p>
                            <p className="text-lg font-bold text-navy">
                                {formatUang(personal.latest_slip.net_pay)}
                            </p>
                        </Link>
                    ) : (
                        <EmptyState>
                            Belum ada slip gaji yang diterbitkan.
                        </EmptyState>
                    )}
                </Panel>
                <Panel title="Hasil Penilaian Terakhir" icon={Star}>
                    {personal.latest_review ? (
                        <Link
                            href={reviews.show.url(personal.latest_review.id)}
                            className="block rounded-lg bg-muted/60 p-3 hover:bg-muted"
                        >
                            <p className="text-xs text-text-muted">
                                {personal.latest_review.period}
                            </p>
                            <p className="text-lg font-bold text-navy">
                                {formatScore(personal.latest_review.score)}
                                {personal.latest_review.rating && (
                                    <span className="ml-2 text-sm font-medium text-text-muted">
                                        {personal.latest_review.rating}
                                    </span>
                                )}
                            </p>
                        </Link>
                    ) : (
                        <EmptyState>
                            Belum ada hasil penilaian yang dipublikasikan.
                        </EmptyState>
                    )}
                </Panel>
            </div>
        </div>
    );
}
