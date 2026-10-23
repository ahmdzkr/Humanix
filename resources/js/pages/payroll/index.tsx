import { Head, Link, useForm } from '@inertiajs/react';
import { Layers, Plus, Users } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import type { Column } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { formatUang } from '@/lib/format';
import { payrollTone } from '@/lib/payroll';
import type { PayrollStatusValue } from '@/lib/payroll';
import { formatRange } from '@/lib/requests';
import components from '@/routes/payroll/components';
import periods from '@/routes/payroll/periods';
import structures from '@/routes/payroll/structures';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    code: string;
    label: string;
    pay_date: string;
    status: PayrollStatusValue;
    status_label: string;
    records_count: number;
    net_total: string;
};

type Props = {
    periods: Paginated<Row>;
    can: { manage: boolean };
    suggestedMonth: string;
};

export default function PayrollIndex({
    periods: rows,
    can,
    suggestedMonth,
}: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm({ month: suggestedMonth, pay_date: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(periods.store.url(), { onSuccess: () => setOpen(false) });
    };

    const columns: Column<Row>[] = [
        {
            key: 'period',
            header: 'Periode',
            cell: (r) => (
                <Link
                    href={periods.show.url(r.id)}
                    className="font-semibold text-navy capitalize hover:text-primary"
                >
                    {r.label}
                </Link>
            ),
        },
        {
            key: 'pay_date',
            header: 'Tanggal bayar',
            cell: (r) => formatRange(r.pay_date, r.pay_date),
        },
        { key: 'count', header: 'Slip', cell: (r) => r.records_count },
        {
            key: 'net',
            header: 'Total gaji bersih',
            cell: (r) => (
                <span className="font-semibold">{formatUang(r.net_total)}</span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (r) => (
                <StatusBadge tone={payrollTone[r.status]}>
                    {r.status_label}
                </StatusBadge>
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
                    <Link href={periods.show.url(r.id)}>Buka</Link>
                </Button>
            ),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Gaji', href: '/gaji' },
            ]}
        >
            <Head title="Gaji" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Payroll"
                    description="Alur: Draft → Dihitung → Direview → Disetujui → Ditutup. Data terkunci setelah direview."
                    actions={
                        can.manage && (
                            <>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="rounded-control"
                                >
                                    <Link href={components.index.url()}>
                                        <Layers className="size-4" />
                                        Komponen
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="rounded-control"
                                >
                                    <Link href={structures.index.url()}>
                                        <Users className="size-4" />
                                        Struktur Gaji
                                    </Link>
                                </Button>
                                <Button
                                    className="rounded-control"
                                    onClick={() => setOpen(true)}
                                >
                                    <Plus className="size-4" />
                                    Periode Baru
                                </Button>
                            </>
                        )
                    }
                />
                <DataTable
                    rows={rows}
                    columns={columns}
                    filters={{}}
                    rowKey={(r) => r.id}
                    searchPlaceholder="Cari periode…"
                    emptyTitle="Belum ada periode payroll"
                    emptyDescription="Buat periode baru, pastikan struktur gaji karyawan sudah diisi, lalu klik Hitung."
                />
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="rounded-card sm:max-w-md">
                    <form onSubmit={submit} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle className="text-navy">
                                Periode Payroll Baru
                            </DialogTitle>
                            <DialogDescription>
                                Satu periode = satu bulan kalender.
                            </DialogDescription>
                        </DialogHeader>
                        <FormField
                            id="month"
                            label="Bulan"
                            required
                            error={form.errors.month}
                        >
                            <Input
                                id="month"
                                type="month"
                                value={form.data.month}
                                onChange={(e) =>
                                    form.setData('month', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="pay_date"
                            label="Tanggal pembayaran"
                            required
                            error={form.errors.pay_date}
                        >
                            <Input
                                id="pay_date"
                                type="date"
                                value={form.data.pay_date}
                                onChange={(e) =>
                                    form.setData('pay_date', e.target.value)
                                }
                            />
                        </FormField>
                        <DialogFooter>
                            <Button
                                type="submit"
                                className="rounded-control"
                                disabled={form.processing}
                            >
                                {form.processing && <Spinner />}
                                Buat periode
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
