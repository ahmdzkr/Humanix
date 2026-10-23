import { Head } from '@inertiajs/react';
import {
    DashboardBackdrop,
    EmptyState,
    Greeting,
} from '@/components/charts/dashboard-widgets';
import type { Personal } from '@/components/charts/personal-panels';
import {
    PersonalKpis,
    PersonalPanels,
} from '@/components/charts/personal-panels';
import AppLayout from '@/layouts/app-layout';

export default function EmployeeDashboard({
    personal,
}: {
    personal: Personal | null;
}) {
    const pending = personal
        ? personal.pending.leave + personal.pending.overtime
        : 0;

    return (
        <AppLayout>
            <Head title="Dashboard" />
            <div className="relative isolate flex flex-1 flex-col gap-6 p-4 md:p-6">
                <DashboardBackdrop />
                <Greeting
                    subtitle={
                        pending > 0
                            ? `Anda punya ${pending} pengajuan yang masih menunggu persetujuan.`
                            : 'Ringkasan absensi, cuti, lembur, dan gaji Anda.'
                    }
                />
                {personal ? (
                    <>
                        <PersonalKpis personal={personal} />
                        <PersonalPanels personal={personal} />
                    </>
                ) : (
                    <EmptyState>
                        Akun Anda belum terhubung dengan data karyawan. Hubungi
                        HRD.
                    </EmptyState>
                )}
            </div>
        </AppLayout>
    );
}
