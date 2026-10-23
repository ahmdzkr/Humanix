import { Head, Link, router } from '@inertiajs/react';
import {
    BadgeCheck,
    BriefcaseBusiness,
    CalendarCheck,
    CalendarDays,
    Clock,
    PieChart,
    Plane,
    UserRoundSearch,
    Users,
    Wallet,
} from 'lucide-react';
import type { AttendanceDay } from '@/components/charts/attendance-chart';
import { AttendanceChart } from '@/components/charts/attendance-chart';
import { BarList, SERIES, shortMonth } from '@/components/charts/chart-kit';
import type {
    ActivityItem,
    Awaiting,
    PerformanceSummary,
} from '@/components/charts/dashboard-widgets';
import {
    ActivityFeed,
    AwaitingTable,
    DashboardBackdrop,
    EmptyState,
    formatAngka,
    Greeting,
    Panel,
    PerformancePanel,
} from '@/components/charts/dashboard-widgets';
import { DonutChart } from '@/components/charts/donut-chart';
import { SingleSeriesChart } from '@/components/charts/single-series-chart';
import { selectClassName } from '@/components/form-field';
import { KpiCard } from '@/components/kpi-card';
import { StatusBadge } from '@/components/status-badge';
import AppLayout from '@/layouts/app-layout';
import { formatRupiah } from '@/lib/format';
import type { PayrollStatusValue } from '@/lib/payroll';
import { payrollTone } from '@/lib/payroll';
import { dashboard } from '@/routes/hr';
import periods from '@/routes/payroll/periods';
import { index as recruitmentIndex } from '@/routes/recruitment';

type Distribution = { name: string; total: number }[];

type Props = {
    filters: { department: string; days: string };
    departmentOptions: { id: number; name: string }[];
    kpi: {
        headcount: number;
        today: AttendanceDay;
        pending_leave: number;
        pending_overtime: number;
    };
    attendanceTrend: AttendanceDay[];
    departments: Distribution;
    employmentStatuses: Distribution;
    leaveOvertime: {
        month: string;
        leave_days: number;
        overtime_hours: number;
    }[];
    awaiting: Awaiting[];
    payroll: {
        id: number;
        period: string;
        code: string;
        status: PayrollStatusValue;
        status_label: string;
        net_total: string;
        records: number;
        paid: number;
    } | null;
    recruitment: {
        open_vacancies: number;
        applicants_this_month: number;
        stages: Distribution;
    };
    performance: PerformanceSummary;
    activity: ActivityItem[];
};

export default function HrDashboard(props: Props) {
    const { filters, departmentOptions, kpi, payroll, recruitment } = props;
    const today = kpi.today;
    const pending = kpi.pending_leave + kpi.pending_overtime;

    const visit = (next: Partial<Props['filters']>) =>
        router.get(
            dashboard.url(),
            Object.fromEntries(
                Object.entries({ ...filters, ...next }).filter(
                    ([, v]) => v !== '' && v !== '7',
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <AppLayout>
            <Head title="Dashboard HRD" />
            <div className="relative isolate flex flex-1 flex-col gap-6 p-4 md:p-6">
                <DashboardBackdrop />
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <Greeting
                        subtitle={
                            pending > 0
                                ? `Ada ${pending} pengajuan cuti/izin & lembur yang menunggu persetujuan.`
                                : 'Tidak ada pengajuan yang menunggu persetujuan.'
                        }
                    />
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <label className="sr-only" htmlFor="filter-department">
                            Departemen
                        </label>
                        <select
                            id="filter-department"
                            value={filters.department}
                            onChange={(e) =>
                                visit({ department: e.target.value })
                            }
                            className={selectClassName + ' sm:w-56'}
                        >
                            <option value="">Semua departemen</option>
                            {departmentOptions.map((d) => (
                                <option key={d.id} value={String(d.id)}>
                                    {d.name}
                                </option>
                            ))}
                        </select>
                        <div
                            className="inline-flex rounded-control border border-border bg-surface p-1"
                            role="group"
                            aria-label="Rentang tren"
                        >
                            {['7', '30'].map((d) => (
                                <button
                                    key={d}
                                    type="button"
                                    aria-pressed={filters.days === d}
                                    onClick={() => visit({ days: d })}
                                    className={
                                        'rounded-md px-3 py-1.5 text-sm font-medium ' +
                                        (filters.days === d
                                            ? 'bg-primary text-white'
                                            : 'text-text-muted hover:text-navy')
                                    }
                                >
                                    {d} hari
                                </button>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <KpiCard
                        label="Total Karyawan"
                        image="/images/total-karyawan.png"
                        value={formatAngka(kpi.headcount)}
                        icon={Users}
                        hint="Karyawan aktif"
                    />
                    <KpiCard
                        label="Hadir Hari Ini"
                        image="/images/hadir.png"
                        value={formatAngka(today.present)}
                        icon={CalendarCheck}
                        tone="success"
                        hint={
                            today.scheduled - today.leave > 0
                                ? `${today.rate.toLocaleString('id-ID')}% dari ${today.scheduled - today.leave} terjadwal · ${today.late} terlambat`
                                : 'Tidak ada jadwal kerja hari ini'
                        }
                    />
                    <KpiCard
                        label="Cuti/Izin"
                        image="/images/cuti.png"
                        value={formatAngka(today.leave)}
                        icon={Plane}
                        tone="warning"
                        hint={`Hari ini · ${kpi.pending_leave} pengajuan menunggu`}
                    />
                    <KpiCard
                        label="Pengajuan Lembur"
                        image="/images/lembur.png"
                        value={formatAngka(kpi.pending_overtime)}
                        icon={Clock}
                        tone="violet"
                        hint="Menunggu persetujuan"
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-3">
                    <AttendanceChart
                        data={props.attendanceTrend}
                        title={`Tren Kehadiran ${filters.days} Hari`}
                        className="xl:col-span-2"
                    />
                    <DonutChart
                        title="Distribusi per Departemen"
                        icon={PieChart}
                        data={props.departments}
                        totalLabel="Karyawan"
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-3">
                    <div className="xl:col-span-2">
                        <AwaitingTable items={props.awaiting} />
                    </div>
                    <Panel title="Ringkasan Payroll" icon={Wallet}>
                        {payroll === null ? (
                            <EmptyState>Belum ada periode payroll.</EmptyState>
                        ) : (
                            <div className="flex flex-1 flex-col">
                                <p className="text-sm text-text-muted">
                                    Total gaji bersih · {payroll.period}
                                </p>
                                <p className="mt-1 text-[28px] leading-tight font-bold text-navy">
                                    {formatRupiah(payroll.net_total)}
                                </p>
                                <div className="mt-3">
                                    <StatusBadge
                                        tone={
                                            ['approved', 'closed'].includes(
                                                payroll.status,
                                            )
                                                ? 'success'
                                                : payrollTone[payroll.status]
                                        }
                                    >
                                        {['approved', 'closed'].includes(
                                            payroll.status,
                                        )
                                            ? 'Sudah Diproses'
                                            : payroll.status_label}
                                    </StatusBadge>
                                </div>
                                <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
                                    <div className="rounded-lg bg-muted/60 p-3">
                                        <dt className="text-xs text-text-muted">
                                            Slip
                                        </dt>
                                        <dd className="text-lg font-bold text-navy">
                                            {payroll.records}
                                        </dd>
                                    </div>
                                    <div className="rounded-lg bg-muted/60 p-3">
                                        <dt className="text-xs text-text-muted">
                                            Sudah dibayar
                                        </dt>
                                        <dd className="text-lg font-bold text-navy">
                                            {payroll.paid}
                                        </dd>
                                    </div>
                                </dl>
                                <Link
                                    href={periods.show.url(payroll.id)}
                                    className="mt-4 text-sm font-medium text-primary hover:underline"
                                >
                                    Buka periode →
                                </Link>
                            </div>
                        )}
                    </Panel>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <SingleSeriesChart
                        title="Hari Cuti Disetujui"
                        icon={CalendarDays}
                        data={props.leaveOvertime}
                        dataKey="leave_days"
                        xKey="month"
                        name="Cuti"
                        unit="hari"
                        color={SERIES[2]}
                        formatX={shortMonth}
                    />
                    <SingleSeriesChart
                        title="Jam Lembur Disetujui"
                        icon={Clock}
                        data={props.leaveOvertime}
                        dataKey="overtime_hours"
                        xKey="month"
                        name="Lembur"
                        unit="jam"
                        color={SERIES[3]}
                        formatX={shortMonth}
                    />
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Panel title="Status Kepegawaian" icon={BadgeCheck}>
                        <BarList items={props.employmentStatuses} />
                    </Panel>
                    <Panel
                        title="Rekrutmen"
                        icon={UserRoundSearch}
                        action={
                            <Link
                                href={recruitmentIndex.url()}
                                className="text-sm font-medium text-primary hover:underline"
                            >
                                Lihat
                            </Link>
                        }
                    >
                        <div className="mb-4 grid grid-cols-2 gap-3 text-sm">
                            <div className="rounded-lg bg-muted/60 p-3">
                                <p className="flex items-center gap-1 text-xs text-text-muted">
                                    <BriefcaseBusiness className="size-3.5" />{' '}
                                    Lowongan dibuka
                                </p>
                                <p className="text-lg font-bold text-navy">
                                    {recruitment.open_vacancies}
                                </p>
                            </div>
                            <div className="rounded-lg bg-muted/60 p-3">
                                <p className="text-xs text-text-muted">
                                    Pelamar bulan ini
                                </p>
                                <p className="text-lg font-bold text-navy">
                                    {recruitment.applicants_this_month}
                                </p>
                            </div>
                        </div>
                        <BarList
                            items={recruitment.stages}
                            color={SERIES[6]}
                            empty="Belum ada pelamar di lowongan yang masih dibuka."
                        />
                    </Panel>
                    <PerformancePanel summary={props.performance} />
                </div>

                <ActivityFeed items={props.activity} />
            </div>
        </AppLayout>
    );
}
