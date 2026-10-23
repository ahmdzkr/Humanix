import { Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Activity, ClipboardCheck, Hourglass, Star } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { formatJam, formatTanggal, sapaanWaktu } from '@/lib/format';
import { formatScore } from '@/lib/performance';
import { cn } from '@/lib/utils';
import { show as leaveShow } from '@/routes/leave';
import { show as overtimeShow } from '@/routes/overtime';

export type Awaiting = {
    kind: 'leave' | 'overtime';
    id: number;
    employee: string | null;
    type: string | null;
    detail: string;
    date: string;
    created_at: string | null;
};

export type ActivityItem = { actor: string; text: string; at: string | null };

export type PerformanceSummary = {
    period: string;
    total: number;
    completed: number;
    published: number;
    average: number | null;
} | null;

/** Kartu polos dengan judul berikon, dipakai semua panel non-grafik. */
export function Panel({
    title,
    icon: Icon,
    action,
    children,
    className,
}: {
    title: string;
    icon: LucideIcon;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'flex flex-col rounded-card border border-border bg-surface p-5',
                className,
            )}
        >
            <div className="mb-4 flex items-center justify-between gap-3">
                <h2 className="flex items-center gap-2 text-base font-semibold text-navy">
                    <span className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Icon className="size-4" />
                    </span>
                    {title}
                </h2>
                {action}
            </div>
            {children}
        </section>
    );
}

/** Sapaan sesuai waktu WIB + tanggal/jam yang diperbarui tiap 30 detik. */
export function Greeting({ subtitle }: { subtitle: ReactNode }) {
    const { auth } = usePage().props;
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const timer = setInterval(() => setNow(new Date()), 30_000);

        return () => clearInterval(timer);
    }, []);

    return (
        <div>
            <p className="text-sm text-text-muted">
                {formatTanggal(now)} · {formatJam(now)}
            </p>
            <h1 className="mt-1 text-2xl font-bold text-navy">
                {sapaanWaktu(now)}, {auth.user.name.split(' ')[0]}
            </h1>
            <p className="mt-1 text-sm text-text-muted">{subtitle}</p>
        </div>
    );
}

export function EmptyState({ children }: { children: ReactNode }) {
    return (
        <p className="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-text-muted">
            {children}
        </p>
    );
}

export function AwaitingTable({ items }: { items: Awaiting[] }) {
    return (
        <Panel title="Pengajuan Menunggu Persetujuan" icon={Hourglass}>
            {items.length === 0 ? (
                <EmptyState>
                    Tidak ada pengajuan yang menunggu keputusan Anda.
                </EmptyState>
            ) : (
                <div className="-mx-5 overflow-x-auto">
                    <table className="w-full min-w-[560px] text-sm">
                        <thead>
                            <tr className="border-b border-border text-left text-xs text-text-muted uppercase">
                                <th className="px-5 py-2 font-medium">
                                    Karyawan
                                </th>
                                <th className="px-3 py-2 font-medium">Jenis</th>
                                <th className="px-3 py-2 font-medium">
                                    Tanggal
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-5 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item) => (
                                <tr
                                    key={`${item.kind}-${item.id}`}
                                    className="border-b border-border last:border-0"
                                >
                                    <td className="px-5 py-3 font-medium text-navy">
                                        {item.employee ?? '—'}
                                    </td>
                                    <td className="px-3 py-3">
                                        {item.type}
                                        <span className="block text-xs text-text-muted">
                                            {item.detail}
                                        </span>
                                    </td>
                                    <td className="px-3 py-3 whitespace-nowrap">
                                        {formatTanggal(
                                            `${item.date}T00:00:00+07:00`,
                                        )}
                                    </td>
                                    <td className="px-3 py-3">
                                        <StatusBadge tone="warning">
                                            Menunggu
                                        </StatusBadge>
                                    </td>
                                    <td className="px-5 py-3 text-right">
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                href={
                                                    item.kind === 'leave'
                                                        ? leaveShow.url(item.id)
                                                        : overtimeShow.url(
                                                              item.id,
                                                          )
                                                }
                                            >
                                                Lihat
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Panel>
    );
}

/** "5 menit lalu", "2 jam lalu", atau tanggal bila lebih dari sehari. */
function relative(at: string | null): string {
    if (!at) {
        return '';
    }

    const minutes = Math.round((Date.now() - new Date(at).getTime()) / 60_000);

    if (minutes < 1) {
        return 'baru saja';
    }

    if (minutes < 60) {
        return `${minutes} menit lalu`;
    }

    if (minutes < 24 * 60) {
        return `${Math.floor(minutes / 60)} jam lalu`;
    }

    return formatTanggal(at);
}

export function ActivityFeed({ items }: { items: ActivityItem[] }) {
    return (
        <Panel title="Aktivitas Terbaru" icon={Activity}>
            {items.length === 0 ? (
                <EmptyState>Belum ada aktivitas.</EmptyState>
            ) : (
                <ul className="space-y-4">
                    {items.map((item, i) => (
                        <li key={i} className="flex gap-3">
                            <span
                                aria-hidden
                                className="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                            />
                            <div className="min-w-0 text-sm">
                                <p>
                                    <span className="font-semibold text-navy">
                                        {item.actor}
                                    </span>{' '}
                                    {item.text}
                                </p>
                                <p className="text-xs text-text-muted">
                                    {relative(item.at)}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}

/** Progres periode penilaian: lembar selesai/total + rata-rata nilai terpublikasi. */
export function PerformancePanel({ summary }: { summary: PerformanceSummary }) {
    const pct =
        summary && summary.total > 0
            ? Math.round((summary.completed / summary.total) * 100)
            : 0;

    return (
        <Panel title="Penilaian Kinerja" icon={Star}>
            {summary === null ? (
                <EmptyState>Belum ada periode penilaian.</EmptyState>
            ) : (
                <div className="space-y-4">
                    <p className="text-sm text-text-muted">{summary.period}</p>
                    <div>
                        <div className="mb-1 flex justify-between text-sm">
                            <span className="flex items-center gap-1.5">
                                <ClipboardCheck className="size-4 text-text-muted" />
                                Lembar selesai
                            </span>
                            <span className="font-semibold text-navy">
                                {summary.completed} / {summary.total}
                            </span>
                        </div>
                        <div
                            className="h-2 rounded-full bg-muted"
                            role="progressbar"
                            aria-valuenow={pct}
                            aria-valuemin={0}
                            aria-valuemax={100}
                        >
                            <div
                                className="h-2 rounded-full bg-primary"
                                style={{ width: `${pct}%` }}
                            />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3 text-sm">
                        <div className="rounded-lg bg-muted/60 p-3">
                            <p className="text-xs text-text-muted">
                                Dipublikasikan
                            </p>
                            <p className="text-lg font-bold text-navy">
                                {summary.published}
                            </p>
                        </div>
                        <div className="rounded-lg bg-muted/60 p-3">
                            <p className="text-xs text-text-muted">
                                Rata-rata nilai
                            </p>
                            <p className="text-lg font-bold text-navy">
                                {summary.average === null
                                    ? '—'
                                    : formatScore(summary.average)}
                            </p>
                        </div>
                    </div>
                </div>
            )}
        </Panel>
    );
}

/**
 * Latar samar "X" HUMANIX, menempel di pojok kanan bawah layar (terlihat di sela kartu saat menggulir).
 * Wadahnya harus `relative isolate` agar gambar berada di belakang kartu, bukan di atasnya.
 */
export function DashboardBackdrop() {
    return (
        <img
            src="/images/x-transparantdash.webp"
            alt=""
            aria-hidden
            width={1400}
            height={967}
            className="pointer-events-none fixed right-0 bottom-0 -z-10 w-[min(70vw,760px)] opacity-70 select-none"
        />
    );
}

export const formatAngka = (value: number) =>
    new Intl.NumberFormat('id-ID').format(value);
