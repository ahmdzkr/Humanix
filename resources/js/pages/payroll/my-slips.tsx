import { Head, Link } from '@inertiajs/react';
import { FileText, Receipt } from 'lucide-react';
import { FilePreviewButton } from '@/components/file-preview';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatUang } from '@/lib/format';
import { formatRange } from '@/lib/requests';
import records from '@/routes/payroll/records';

type Slip = {
    id: number;
    period: string;
    pay_date: string;
    net_pay: string;
    paid: boolean;
};

export default function MySlips({ slips }: { slips: Slip[] }) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Slip Gaji Saya', href: '/gaji/slip-saya' },
            ]}
        >
            <Head title="Slip Gaji Saya" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Slip Gaji Saya"
                    description="Slip muncul setelah payroll periode tersebut disetujui. Data ini rahasia — hanya Anda dan HRD yang dapat melihatnya."
                />
                {slips.length === 0 ? (
                    <div className="flex flex-col items-center rounded-card border border-border bg-surface p-12 text-center text-sm text-text-muted">
                        <Receipt className="mb-2 size-8 text-primary" />
                        Belum ada slip gaji yang diterbitkan.
                    </div>
                ) : (
                    <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {slips.map((s) => (
                            <li
                                key={s.id}
                                className="flex flex-col gap-3 rounded-card border border-border bg-surface p-5"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <p className="font-semibold text-navy capitalize">
                                            {s.period}
                                        </p>
                                        <p className="text-xs text-text-muted">
                                            Dibayar{' '}
                                            {formatRange(
                                                s.pay_date,
                                                s.pay_date,
                                            )}
                                        </p>
                                    </div>
                                    {s.paid ? (
                                        <StatusBadge tone="success">
                                            Sudah Diproses
                                        </StatusBadge>
                                    ) : (
                                        <StatusBadge tone="warning">
                                            Menunggu Pembayaran
                                        </StatusBadge>
                                    )}
                                </div>
                                <p className="text-2xl font-bold text-navy">
                                    {formatUang(s.net_pay)}
                                </p>
                                <div className="mt-auto flex gap-2">
                                    <Button
                                        asChild
                                        variant="outline"
                                        size="sm"
                                        className="rounded-control"
                                    >
                                        <Link href={records.show.url(s.id)}>
                                            Lihat rincian
                                        </Link>
                                    </Button>
                                    <FilePreviewButton
                                        url={records.pdf.url(s.id)}
                                        kind="pdf"
                                        title={`Slip Gaji ${s.period}`}
                                        icon={FileText}
                                        size="sm"
                                        className="rounded-control"
                                    >
                                        PDF
                                    </FilePreviewButton>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
