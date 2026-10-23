import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ApprovalTimeline } from '@/components/approval-timeline';
import { DecisionPanel } from '@/components/decision-panel';
import { StatusBadge } from '@/components/status-badge';
import AppLayout from '@/layouts/app-layout';
import { formatJam, formatTanggal } from '@/lib/format';
import type { ApprovalStepView, RequestStatusValue } from '@/lib/requests';
import { formatDuration, formatRange, requestTone } from '@/lib/requests';
import { cancel, decide, index } from '@/routes/overtime';

type Props = {
    overtime: {
        id: number;
        employee_name: string | null;
        employee_nik: string | null;
        department: string | null;
        position: string | null;
        work_date: string;
        start_time: string;
        end_time: string;
        duration_minutes: number;
        status: RequestStatusValue;
        status_label: string;
        created_at: string | null;
        reason: string;
        version: number;
        payable: boolean;
    };
    steps: ApprovalStepView[];
    can: { decide: boolean; cancel: boolean };
};

function Info({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-xs text-text-muted">{label}</dt>
            <dd className="mt-0.5 text-sm font-medium break-words">
                {children}
            </dd>
        </div>
    );
}

export default function OvertimeShow({ overtime, steps, can }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Lembur', href: index.url() },
                { title: `Pengajuan #${overtime.id}`, href: '#' },
            ]}
        >
            <Head title={`Lembur #${overtime.id}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-2xl font-bold text-navy">
                        Lembur — {overtime.employee_name}
                    </h1>
                    <StatusBadge tone={requestTone[overtime.status]}>
                        {overtime.status_label}
                    </StatusBadge>
                    {overtime.payable && (
                        <StatusBadge tone="info">Masuk payroll</StatusBadge>
                    )}
                </div>

                <div className="grid gap-6 xl:grid-cols-3">
                    <section className="rounded-card border border-border bg-surface p-5 xl:col-span-2">
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <Info label="Karyawan">
                                {overtime.employee_name} (
                                {overtime.employee_nik})
                            </Info>
                            <Info label="Jabatan">
                                {overtime.position ?? '—'} ·{' '}
                                {overtime.department ?? '—'}
                            </Info>
                            <Info label="Tanggal">
                                {formatRange(
                                    overtime.work_date,
                                    overtime.work_date,
                                )}
                            </Info>
                            <Info label="Jam">
                                {overtime.start_time}–{overtime.end_time} WIB (
                                {formatDuration(overtime.duration_minutes)})
                            </Info>
                            <Info label="Diajukan">
                                {overtime.created_at
                                    ? `${formatTanggal(overtime.created_at)}, ${formatJam(overtime.created_at)}`
                                    : '—'}
                            </Info>
                            <div className="sm:col-span-2">
                                <Info label="Alasan / pekerjaan">
                                    <span className="whitespace-pre-line">
                                        {overtime.reason}
                                    </span>
                                </Info>
                            </div>
                        </dl>
                    </section>

                    <div className="flex flex-col gap-6">
                        <section className="rounded-card border border-border bg-surface p-5">
                            <h2 className="mb-4 font-semibold text-navy">
                                Alur Persetujuan
                            </h2>
                            <ApprovalTimeline steps={steps} />
                        </section>
                        <DecisionPanel
                            decideUrl={decide.url(overtime.id)}
                            cancelUrl={cancel.url(overtime.id)}
                            version={overtime.version}
                            canDecide={can.decide}
                            canCancel={can.cancel}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
