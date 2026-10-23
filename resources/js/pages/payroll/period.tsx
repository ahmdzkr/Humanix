import { Head, Link, router } from '@inertiajs/react';
import {
    Banknote,
    Calculator,
    Check,
    CheckCheck,
    FileSpreadsheet,
    Lock,
    Receipt,
    Send,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { Column } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { FilePreviewButton } from '@/components/file-preview';
import { KpiCard } from '@/components/kpi-card';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatUang } from '@/lib/format';
import type { PaymentStatusValue, PayrollStatusValue } from '@/lib/payroll';
import { paymentTone, payrollSteps } from '@/lib/payroll';
import { formatDuration, formatRange } from '@/lib/requests';
import { cn } from '@/lib/utils';
import periods from '@/routes/payroll/periods';
import recordRoutes from '@/routes/payroll/records';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    employee_name: string;
    nik: string;
    department: string | null;
    total_earnings: string;
    total_deductions: string;
    net_pay: string;
    overtime_minutes: number;
    payment_status: PaymentStatusValue | null;
    payment_status_label: string | null;
};

type Props = {
    period: {
        id: number;
        code: string;
        label: string;
        start_date: string;
        end_date: string;
        pay_date: string;
        status: PayrollStatusValue;
        status_label: string;
        version: number;
    };
    records: Paginated<Row>;
    filters: { search: string };
    totals: {
        count: number;
        earnings: string;
        deductions: string;
        net: string;
        paid: number;
    };
    can: {
        calculate: boolean;
        review: boolean;
        approve: boolean;
        pay: boolean;
        close: boolean;
        export: boolean;
    };
};

type Action = {
    key: string;
    title: string;
    description: string;
    run: () => void;
};

export default function PayrollPeriod({
    period,
    records,
    filters,
    totals,
    can,
}: Props) {
    const [pending, setPending] = useState<Action | null>(null);
    const [processing, setProcessing] = useState(false);
    const currentIndex = payrollSteps.findIndex(
        (s) => s.value === period.status,
    );

    const post = (url: string, data: Record<string, string | number> = {}) => {
        setProcessing(true);
        router.post(url, data, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setPending(null);
            },
        });
    };

    const advance = (target: 'reviewed' | 'approved' | 'closed') =>
        post(periods.advance.url(period.id), {
            target,
            version: period.version,
        });

    const actions: Record<string, Action> = {
        calculate: {
            key: 'calculate',
            title:
                period.status === 'draft'
                    ? 'Hitung gaji?'
                    : 'Hitung ulang gaji?',
            description:
                'Gaji dihitung dari struktur gaji, komponen, dan lembur yang disetujui. Penyesuaian manual tetap dipertahankan.',
            run: () =>
                post(periods.calculate.url(period.id), {
                    version: period.version,
                }),
        },
        review: {
            key: 'review',
            title: 'Tandai sudah direview?',
            description:
                'Setelah direview, angka gaji terkunci dan tidak bisa dihitung ulang atau disesuaikan.',
            run: () => advance('reviewed'),
        },
        approve: {
            key: 'approve',
            title: 'Setujui payroll?',
            description:
                'Slip gaji akan terlihat oleh karyawan dan pembayaran bisa diproses.',
            run: () => advance('approved'),
        },
        pay: {
            key: 'pay',
            title: 'Buat instruksi pembayaran?',
            description:
                'Membuat pembayaran berstatus Menunggu untuk slip yang belum punya. Aman diulang — tidak akan menggandakan.',
            run: () => post(periods.payments.url(period.id)),
        },
        close: {
            key: 'close',
            title: 'Tutup periode?',
            description:
                'Hanya bisa jika semua slip sudah dibayar. Setelah ditutup, data periode tidak bisa diubah lagi.',
            run: () => advance('closed'),
        },
    };

    const columns: Column<Row>[] = [
        {
            key: 'employee',
            header: 'Karyawan',
            cell: (r) => (
                <Link
                    href={recordRoutes.show.url(r.id)}
                    className="block text-left"
                >
                    <span className="block font-semibold text-navy hover:text-primary">
                        {r.employee_name}
                    </span>
                    <span className="block text-xs text-text-muted">
                        {r.nik} · {r.department ?? '—'}
                    </span>
                </Link>
            ),
        },
        {
            key: 'earnings',
            header: 'Pendapatan',
            cell: (r) => (
                <span>
                    <span className="block">
                        {formatUang(r.total_earnings)}
                    </span>
                    {r.overtime_minutes > 0 && (
                        <span className="block text-xs text-text-muted">
                            lembur {formatDuration(r.overtime_minutes)}
                        </span>
                    )}
                </span>
            ),
        },
        {
            key: 'deductions',
            header: 'Potongan',
            cell: (r) => formatUang(r.total_deductions),
        },
        {
            key: 'net',
            header: 'Gaji bersih',
            cell: (r) => (
                <span
                    className={cn(
                        'font-semibold',
                        r.net_pay.startsWith('-') ? 'text-danger' : 'text-navy',
                    )}
                >
                    {formatUang(r.net_pay)}
                </span>
            ),
        },
        {
            key: 'payment',
            header: 'Pembayaran',
            cell: (r) =>
                r.payment_status ? (
                    <StatusBadge tone={paymentTone[r.payment_status]}>
                        {r.payment_status_label}
                    </StatusBadge>
                ) : (
                    <span className="text-xs text-text-muted">—</span>
                ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            className: 'md:text-right',
            cell: (r) => (
                <Button
                    asChild
                    variant="outline"
                    size="sm"
                    className="rounded-control"
                >
                    <Link href={recordRoutes.show.url(r.id)}>Slip</Link>
                </Button>
            ),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Gaji', href: '/gaji' },
                { title: period.label, href: '#' },
            ]}
        >
            <Head title={`Payroll ${period.label}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`Payroll ${period.label}`}
                    description={`${formatRange(period.start_date, period.end_date)} · dibayar ${formatRange(period.pay_date, period.pay_date)}`}
                    actions={
                        can.export && (
                            <FilePreviewButton
                                url={periods.export.url(period.id)}
                                kind="excel"
                                title={`Payroll ${period.code}`}
                                icon={FileSpreadsheet}
                                variant="outline"
                                className="rounded-control"
                            >
                                Ekspor Excel
                            </FilePreviewButton>
                        )
                    }
                />

                <section className="rounded-card border border-border bg-surface p-5">
                    <ol className="flex flex-wrap items-center gap-2">
                        {payrollSteps.map((step, i) => (
                            <li
                                key={step.value}
                                className="flex items-center gap-2"
                            >
                                <span
                                    className={cn(
                                        'flex items-center gap-2 rounded-full px-3 py-1 text-sm font-medium',
                                        i < currentIndex &&
                                            'bg-success/10 text-success',
                                        i === currentIndex &&
                                            'bg-primary text-white',
                                        i > currentIndex &&
                                            'bg-muted text-text-muted',
                                    )}
                                >
                                    {i < currentIndex && (
                                        <Check className="size-3.5" />
                                    )}
                                    {step.label}
                                </span>
                                {i < payrollSteps.length - 1 && (
                                    <span className="h-px w-4 bg-border" />
                                )}
                            </li>
                        ))}
                    </ol>
                    <div className="mt-4 flex flex-wrap gap-2">
                        {can.calculate && (
                            <Button
                                className="rounded-control"
                                onClick={() => setPending(actions.calculate)}
                            >
                                <Calculator className="size-4" />
                                {period.status === 'draft'
                                    ? 'Hitung gaji'
                                    : 'Hitung ulang'}
                            </Button>
                        )}
                        {can.review && (
                            <Button
                                variant="outline"
                                className="rounded-control"
                                onClick={() => setPending(actions.review)}
                            >
                                <CheckCheck className="size-4" />
                                Tandai sudah direview
                            </Button>
                        )}
                        {can.approve && (
                            <Button
                                className="rounded-control bg-success hover:bg-success/90"
                                onClick={() => setPending(actions.approve)}
                            >
                                <Check className="size-4" />
                                Setujui payroll
                            </Button>
                        )}
                        {can.pay && (
                            <Button
                                className="rounded-control"
                                onClick={() => setPending(actions.pay)}
                            >
                                <Send className="size-4" />
                                Buat instruksi pembayaran
                            </Button>
                        )}
                        {can.close && (
                            <Button
                                variant="outline"
                                className="rounded-control"
                                onClick={() => setPending(actions.close)}
                            >
                                <Lock className="size-4" />
                                Tutup periode
                            </Button>
                        )}
                        {period.status === 'closed' && (
                            <p className="flex items-center gap-2 text-sm text-text-muted">
                                <Lock className="size-4" /> Periode ditutup —
                                data terkunci.
                            </p>
                        )}
                    </div>
                </section>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <KpiCard
                        label="Jumlah slip"
                        value={String(totals.count)}
                        icon={Receipt}
                    />
                    <KpiCard
                        label="Total pendapatan"
                        value={formatUang(totals.earnings)}
                        icon={Banknote}
                        tone="cyan"
                    />
                    <KpiCard
                        label="Total potongan"
                        value={formatUang(totals.deductions)}
                        icon={Wallet}
                        tone="warning"
                    />
                    <KpiCard
                        label="Total gaji bersih"
                        value={formatUang(totals.net)}
                        icon={Banknote}
                        tone="success"
                        hint={`${totals.paid} dari ${totals.count} sudah dibayar`}
                    />
                </div>

                <DataTable
                    rows={records}
                    columns={columns}
                    filters={filters}
                    rowKey={(r) => r.id}
                    searchPlaceholder="Cari nama atau NIK…"
                    emptyTitle="Belum ada slip gaji"
                    emptyDescription="Klik Hitung gaji untuk membuat slip dari struktur gaji karyawan."
                />
            </div>

            <ConfirmDialog
                open={pending !== null}
                onOpenChange={(o) => !o && setPending(null)}
                title={pending?.title ?? ''}
                description={pending?.description ?? ''}
                confirmLabel="Lanjutkan"
                processing={processing}
                onConfirm={() => pending?.run()}
            />
        </AppLayout>
    );
}
