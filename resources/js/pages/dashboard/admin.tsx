import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    Briefcase,
    Building2,
    CheckCircle2,
    CircleAlert,
    ListChecks,
    ShieldCheck,
    UserCog,
} from 'lucide-react';
import { BarList } from '@/components/charts/chart-kit';
import {
    DashboardBackdrop,
    EmptyState,
    formatAngka,
    Greeting,
    Panel,
} from '@/components/charts/dashboard-widgets';
import { KpiCard } from '@/components/kpi-card';
import AppLayout from '@/layouts/app-layout';
import { formatJam, formatTanggal } from '@/lib/format';
import { index as auditIndex } from '@/routes/audit';
import { index as settingsIndex } from '@/routes/pengaturan';

type Props = {
    metrics: {
        users: number;
        users_without_role: number;
        departments: number;
        positions: number;
        audit_today: number;
        roles: { name: string; total: number }[];
        config: { label: string; ok: boolean }[];
        audit: {
            actor: string;
            event: string;
            model: string;
            at: string | null;
        }[];
    };
};

export default function AdminDashboard({ metrics }: Props) {
    const missing = metrics.config.filter((c) => !c.ok).length;

    return (
        <AppLayout>
            <Head title="Dashboard Administrator" />
            <div className="relative isolate flex flex-1 flex-col gap-6 p-4 md:p-6">
                <DashboardBackdrop />
                <Greeting
                    subtitle={
                        missing > 0
                            ? `${missing} pengaturan sistem masih perlu dilengkapi.`
                            : 'Semua pengaturan dasar sistem sudah lengkap.'
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <KpiCard
                        label="Jumlah Akun"
                        value={formatAngka(metrics.users)}
                        icon={UserCog}
                        hint={
                            metrics.users_without_role > 0
                                ? `${metrics.users_without_role} akun belum punya peran`
                                : 'Semua akun punya peran'
                        }
                    />
                    <KpiCard
                        label="Departemen Aktif"
                        value={formatAngka(metrics.departments)}
                        icon={Building2}
                        tone="cyan"
                    />
                    <KpiCard
                        label="Jabatan Aktif"
                        value={formatAngka(metrics.positions)}
                        icon={Briefcase}
                        tone="success"
                    />
                    <KpiCard
                        label="Aktivitas Audit Hari Ini"
                        value={formatAngka(metrics.audit_today)}
                        icon={Activity}
                        tone="warning"
                    />
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Panel title="Distribusi Peran" icon={ShieldCheck}>
                        <BarList
                            items={metrics.roles}
                            empty="Belum ada akun dengan peran."
                        />
                    </Panel>
                    <Panel
                        title="Status Konfigurasi"
                        icon={ListChecks}
                        action={
                            <Link
                                href={settingsIndex.url()}
                                className="text-sm font-medium text-primary hover:underline"
                            >
                                Pengaturan
                            </Link>
                        }
                    >
                        <ul className="space-y-3 text-sm">
                            {metrics.config.map((c) => (
                                <li
                                    key={c.label}
                                    className="flex items-start gap-2"
                                >
                                    {c.ok ? (
                                        <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-success" />
                                    ) : (
                                        <CircleAlert className="mt-0.5 size-4 shrink-0 text-[#b45309]" />
                                    )}
                                    <span>
                                        {c.label}
                                        <span className="sr-only">
                                            {c.ok ? ': sudah' : ': belum'}
                                        </span>
                                        {!c.ok && (
                                            <span className="block text-xs text-text-muted">
                                                Belum
                                            </span>
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Panel>
                    <Panel
                        title="Log Audit Terbaru"
                        icon={Activity}
                        action={
                            <Link
                                href={auditIndex.url()}
                                className="text-sm font-medium text-primary hover:underline"
                            >
                                Semua
                            </Link>
                        }
                    >
                        {metrics.audit.length === 0 ? (
                            <EmptyState>Belum ada catatan audit.</EmptyState>
                        ) : (
                            <ul className="space-y-3 text-sm">
                                {metrics.audit.map((l, i) => (
                                    <li key={i}>
                                        <p>
                                            <span className="font-semibold text-navy">
                                                {l.actor}
                                            </span>{' '}
                                            {l.event} {l.model}
                                        </p>
                                        {l.at && (
                                            <p className="text-xs text-text-muted">
                                                {formatTanggal(l.at)} ·{' '}
                                                {formatJam(l.at)}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>
            </div>
        </AppLayout>
    );
}
