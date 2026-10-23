import { Head } from '@inertiajs/react';
import { Paperclip } from 'lucide-react';
import type { ReactNode } from 'react';
import { ApprovalTimeline } from '@/components/approval-timeline';
import { DecisionPanel } from '@/components/decision-panel';
import { FilePreviewButton } from '@/components/file-preview';
import { StatusBadge } from '@/components/status-badge';
import AppLayout from '@/layouts/app-layout';
import { formatJam, formatTanggal } from '@/lib/format';
import type { ApprovalStepView, RequestStatusValue } from '@/lib/requests';
import { formatRange, requestTone } from '@/lib/requests';
import { attachment, cancel, decide, index } from '@/routes/leave';

type Props = {
    leave: {
        id: number;
        employee_name: string | null;
        employee_nik: string | null;
        department: string | null;
        position: string | null;
        type: string | null;
        start_date: string;
        end_date: string;
        days: number;
        status: RequestStatusValue;
        status_label: string;
        created_at: string | null;
        reason: string;
        has_attachment: boolean;
        attachment_kind: 'pdf' | 'image' | null;
        version: number;
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

export default function LeaveShow({ leave, steps, can }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Cuti/Izin', href: index.url() },
                { title: `Pengajuan #${leave.id}`, href: '#' },
            ]}
        >
            <Head title={`Cuti/Izin #${leave.id}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-2xl font-bold text-navy">
                        {leave.type} — {leave.employee_name}
                    </h1>
                    <StatusBadge tone={requestTone[leave.status]}>
                        {leave.status_label}
                    </StatusBadge>
                </div>

                <div className="grid gap-6 xl:grid-cols-3">
                    <section className="rounded-card border border-border bg-surface p-5 xl:col-span-2">
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <Info label="Karyawan">
                                {leave.employee_name} ({leave.employee_nik})
                            </Info>
                            <Info label="Jabatan">
                                {leave.position ?? '—'} ·{' '}
                                {leave.department ?? '—'}
                            </Info>
                            <Info label="Tanggal">
                                {formatRange(leave.start_date, leave.end_date)}
                            </Info>
                            <Info label="Jumlah">{leave.days} hari kerja</Info>
                            <Info label="Diajukan">
                                {leave.created_at
                                    ? `${formatTanggal(leave.created_at)}, ${formatJam(leave.created_at)}`
                                    : '—'}
                            </Info>
                            <div className="sm:col-span-2">
                                <Info label="Alasan">
                                    <span className="whitespace-pre-line">
                                        {leave.reason}
                                    </span>
                                </Info>
                            </div>
                        </dl>
                        {leave.has_attachment && (
                            <FilePreviewButton
                                url={attachment.url(leave.id)}
                                kind={leave.attachment_kind ?? 'pdf'}
                                title={`Lampiran cuti ${leave.employee_name ?? ''}`.trim()}
                                icon={Paperclip}
                                variant="outline"
                                className="mt-4 rounded-control"
                            >
                                Lihat lampiran
                            </FilePreviewButton>
                        )}
                    </section>

                    <div className="flex flex-col gap-6">
                        <section className="rounded-card border border-border bg-surface p-5">
                            <h2 className="mb-4 font-semibold text-navy">
                                Alur Persetujuan
                            </h2>
                            <ApprovalTimeline steps={steps} />
                        </section>
                        <DecisionPanel
                            decideUrl={decide.url(leave.id)}
                            cancelUrl={cancel.url(leave.id)}
                            version={leave.version}
                            canDecide={can.decide}
                            canCancel={can.cancel}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
