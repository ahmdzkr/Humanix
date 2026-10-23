import { Head } from '@inertiajs/react';
import { CalendarCheck, Clock, Hourglass, Users } from 'lucide-react';
import type { AttendanceDay } from '@/components/charts/attendance-chart';
import { AttendanceChart } from '@/components/charts/attendance-chart';
import type {
    ActivityItem,
    Awaiting,
    PerformanceSummary,
} from '@/components/charts/dashboard-widgets';
import {
    ActivityFeed,
    AwaitingTable,
    DashboardBackdrop,
    formatAngka,
    Greeting,
    PerformancePanel,
} from '@/components/charts/dashboard-widgets';
import type { Personal } from '@/components/charts/personal-panels';
import {
    PersonalKpis,
    PersonalPanels,
} from '@/components/charts/personal-panels';
import { KpiCard } from '@/components/kpi-card';
import AppLayout from '@/layouts/app-layout';

type Props = {
    kpi: {
        team: number;
        today: AttendanceDay;
        awaiting: number;
        overtime_hours: number;
    };
    attendanceTrend: AttendanceDay[];
    awaiting: Awaiting[];
    performance: PerformanceSummary;
    activity: ActivityItem[];
    personal: Personal | null;
};

export default function ManagerDashboard({
    kpi,
    attendanceTrend,
    awaiting,
    performance,
    activity,
    personal,
}: Props) {
    const today = kpi.today;

    return (
        <AppLayout>
            <Head title="Dashboard Manager" />
            <div className="relative isolate flex flex-1 flex-col gap-6 p-4 md:p-6">
                <DashboardBackdrop />
                <Greeting
                    subtitle={
                        kpi.awaiting > 0
                            ? `Ada ${kpi.awaiting} pengajuan tim yang menunggu persetujuan Anda.`
                            : 'Tidak ada pengajuan tim yang menunggu persetujuan.'
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <KpiCard
                        label="Anggota Tim"
                        image="/images/total-karyawan.png"
                        value={formatAngka(kpi.team)}
                        icon={Users}
                        hint="Bawahan aktif"
                    />
                    <KpiCard
                        label="Tim Hadir Hari Ini"
                        image="/images/hadir.png"
                        value={formatAngka(today.present)}
                        icon={CalendarCheck}
                        tone="success"
                        hint={
                            today.scheduled - today.leave > 0
                                ? `${today.rate.toLocaleString('id-ID')}% · ${today.late} terlambat · ${today.leave} cuti`
                                : 'Tidak ada jadwal kerja tim hari ini'
                        }
                    />
                    <KpiCard
                        label="Pengajuan Menunggu"
                        value={formatAngka(kpi.awaiting)}
                        icon={Hourglass}
                        tone="warning"
                        hint="Perlu keputusan Anda"
                    />
                    <KpiCard
                        label="Lembur Tim Bulan Ini"
                        image="/images/lembur.png"
                        value={`${kpi.overtime_hours.toLocaleString('id-ID')} jam`}
                        icon={Clock}
                        tone="violet"
                        hint="Lembur yang sudah disetujui"
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-3">
                    <AttendanceChart
                        data={attendanceTrend}
                        title="Kehadiran Tim 7 Hari"
                        className="xl:col-span-2"
                    />
                    <PerformancePanel summary={performance} />
                </div>

                <div className="grid gap-4 xl:grid-cols-3">
                    <div className="xl:col-span-2">
                        <AwaitingTable items={awaiting} />
                    </div>
                    <ActivityFeed items={activity} />
                </div>

                {personal && (
                    <>
                        <h2 className="mt-2 text-lg font-semibold text-navy">
                            Data Saya
                        </h2>
                        <PersonalKpis personal={personal} />
                        <PersonalPanels personal={personal} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
