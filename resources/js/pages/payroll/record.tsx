import { Head, router, useForm } from '@inertiajs/react';
import { FileCheck, FileText, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { FilePreviewButton } from '@/components/file-preview';
import { FormField, selectClassName } from '@/components/form-field';
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
import { formatJam, formatTanggal, formatUang } from '@/lib/format';
import type { PaymentStatusValue } from '@/lib/payroll';
import { paymentTone } from '@/lib/payroll';
import { formatDuration, formatRange } from '@/lib/requests';
import paymentRoutes from '@/routes/payroll/payments';
import periods from '@/routes/payroll/periods';
import records from '@/routes/payroll/records';

type Item = {
    id: number;
    name: string;
    amount: string;
    source: string;
    note: string | null;
};

type Payment = {
    id: number;
    amount: string;
    status: PaymentStatusValue;
    status_label: string;
    reference: string | null;
    has_proof: boolean;
    proof_kind: 'pdf' | 'image' | null;
    failure_reason: string | null;
    paid_at: string | null;
    allowed_next: { value: PaymentStatusValue; label: string }[];
};

type Props = {
    record: {
        id: number;
        period_id: number;
        period: string;
        pay_date: string;
        employee_name: string;
        nik: string;
        department: string | null;
        position: string | null;
        bank_name: string | null;
        bank_account: string | null;
        bank_account_holder: string | null;
        earnings: Item[];
        deductions: Item[];
        total_earnings: string;
        total_deductions: string;
        net_pay: string;
        overtime_minutes: number;
        present_days: number;
        absent_days: number;
        leave_days: number;
    };
    payments: Payment[];
    can: { adjust: boolean; pay: boolean; backToPeriod: boolean };
};

function Lines({
    title,
    items,
    total,
    canAdjust,
    onRemove,
}: {
    title: string;
    items: Item[];
    total: string;
    canAdjust: boolean;
    onRemove: (item: Item) => void;
}) {
    return (
        <section className="rounded-card border border-border bg-surface p-5">
            <h2 className="mb-3 font-semibold text-navy">{title}</h2>
            {items.length === 0 ? (
                <p className="text-sm text-text-muted">Tidak ada.</p>
            ) : (
                <ul className="divide-y divide-border">
                    {items.map((item) => (
                        <li
                            key={item.id}
                            className="flex items-start justify-between gap-3 py-2 text-sm"
                        >
                            <span>
                                <span className="block">{item.name}</span>
                                {(item.note || item.source === 'manual') && (
                                    <span className="block text-xs text-text-muted">
                                        {item.source === 'manual'
                                            ? 'Penyesuaian manual'
                                            : ''}
                                        {item.source === 'manual' && item.note
                                            ? ' · '
                                            : ''}
                                        {item.note}
                                    </span>
                                )}
                            </span>
                            <span className="flex items-center gap-2 font-mono whitespace-nowrap">
                                {formatUang(item.amount)}
                                {canAdjust && item.source === 'manual' && (
                                    <button
                                        type="button"
                                        aria-label={`Hapus ${item.name}`}
                                        onClick={() => onRemove(item)}
                                        className="text-danger"
                                    >
                                        <Trash2 className="size-4" />
                                    </button>
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            <p className="mt-3 flex justify-between border-t border-border pt-3 text-sm font-semibold">
                <span>Total</span>
                <span className="font-mono">{formatUang(total)}</span>
            </p>
        </section>
    );
}

export default function PayrollRecord({ record, payments, can }: Props) {
    const [adjustOpen, setAdjustOpen] = useState(false);
    const [editingPayment, setEditingPayment] = useState<Payment | null>(null);
    const adjust = useForm({ name: '', type: 'earning', amount: '', note: '' });
    const pay = useForm<{
        status: string;
        reference: string;
        failure_reason: string;
        proof: File | null;
    }>({ status: '', reference: '', failure_reason: '', proof: null });

    const submitAdjust = (event: FormEvent) => {
        event.preventDefault();
        adjust.post(records.items.store.url(record.id), {
            preserveScroll: true,
            onSuccess: () => {
                setAdjustOpen(false);
                adjust.reset();
            },
        });
    };

    const submitPayment = (event: FormEvent) => {
        event.preventDefault();

        if (!editingPayment) {
            return;
        }

        pay.post(paymentRoutes.update.url(editingPayment.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setEditingPayment(null),
        });
    };

    return (
        <AppLayout
            breadcrumbs={
                can.backToPeriod
                    ? [
                          { title: 'Gaji', href: '/gaji' },
                          {
                              title: record.period,
                              href: periods.show.url(record.period_id),
                          },
                          { title: record.employee_name, href: '#' },
                      ]
                    : [
                          { title: 'Slip Gaji Saya', href: '/gaji/slip-saya' },
                          { title: record.period, href: '#' },
                      ]
            }
        >
            <Head title={`Slip ${record.period} — ${record.employee_name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-navy capitalize">
                            Slip Gaji {record.period}
                        </h1>
                        <p className="mt-1 text-sm text-text-muted">
                            {record.employee_name} ({record.nik}) ·{' '}
                            {record.position ?? '—'} ·{' '}
                            {record.department ?? '—'}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {can.adjust && (
                            <Button
                                variant="outline"
                                className="rounded-control"
                                onClick={() => setAdjustOpen(true)}
                            >
                                <Plus className="size-4" />
                                Penyesuaian
                            </Button>
                        )}
                        <FilePreviewButton
                            url={records.pdf.url(record.id)}
                            kind="pdf"
                            title="Slip Gaji"
                            icon={FileText}
                            className="rounded-control"
                        >
                            Lihat PDF
                        </FilePreviewButton>
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-3">
                    <div className="grid gap-6 md:grid-cols-2 xl:col-span-2">
                        <Lines
                            title="Pendapatan"
                            items={record.earnings}
                            total={record.total_earnings}
                            canAdjust={can.adjust}
                            onRemove={(item) =>
                                router.delete(
                                    records.items.destroy.url([
                                        record.id,
                                        item.id,
                                    ]),
                                    { preserveScroll: true },
                                )
                            }
                        />
                        <Lines
                            title="Potongan"
                            items={record.deductions}
                            total={record.total_deductions}
                            canAdjust={can.adjust}
                            onRemove={(item) =>
                                router.delete(
                                    records.items.destroy.url([
                                        record.id,
                                        item.id,
                                    ]),
                                    { preserveScroll: true },
                                )
                            }
                        />
                    </div>

                    <div className="flex flex-col gap-6">
                        <section className="rounded-card bg-navy p-5 text-white">
                            <p className="text-sm text-white/70">
                                Gaji bersih diterima
                            </p>
                            <p className="mt-1 text-3xl font-bold">
                                {formatUang(record.net_pay)}
                            </p>
                            <p className="mt-3 text-xs text-white/70">
                                Dibayar{' '}
                                {formatRange(record.pay_date, record.pay_date)}
                                {record.bank_name &&
                                    ` ke ${record.bank_name} ${record.bank_account} a.n. ${record.bank_account_holder}`}
                            </p>
                        </section>
                        <section className="rounded-card border border-border bg-surface p-5 text-sm">
                            <h2 className="mb-2 font-semibold text-navy">
                                Kehadiran periode ini
                            </h2>
                            <p>
                                Hadir {record.present_days} hari · Tidak hadir{' '}
                                {record.absent_days} hari · Cuti/izin{' '}
                                {record.leave_days} hari
                            </p>
                            {record.overtime_minutes > 0 && (
                                <p className="mt-1 text-text-muted">
                                    Lembur{' '}
                                    {formatDuration(record.overtime_minutes)}
                                </p>
                            )}
                        </section>

                        {payments.length > 0 && (
                            <section className="rounded-card border border-border bg-surface p-5">
                                <h2 className="mb-3 font-semibold text-navy">
                                    Pembayaran
                                </h2>
                                <ul className="space-y-3">
                                    {payments.map((p) => (
                                        <li
                                            key={p.id}
                                            className="rounded-control border border-border p-3 text-sm"
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="font-mono">
                                                    {formatUang(p.amount)}
                                                </span>
                                                <StatusBadge
                                                    tone={paymentTone[p.status]}
                                                >
                                                    {p.status_label}
                                                </StatusBadge>
                                            </div>
                                            {p.reference && (
                                                <p className="mt-1 text-xs text-text-muted">
                                                    Ref: {p.reference}
                                                </p>
                                            )}
                                            {p.paid_at && (
                                                <p className="text-xs text-text-muted">
                                                    Dibayar{' '}
                                                    {formatTanggal(p.paid_at)},{' '}
                                                    {formatJam(p.paid_at)}
                                                </p>
                                            )}
                                            {p.failure_reason && (
                                                <p className="text-xs text-danger">
                                                    {p.failure_reason}
                                                </p>
                                            )}
                                            <div className="mt-2 flex gap-2">
                                                {p.has_proof && (
                                                    <FilePreviewButton
                                                        url={paymentRoutes.proof.url(
                                                            p.id,
                                                        )}
                                                        kind={
                                                            p.proof_kind ??
                                                            'pdf'
                                                        }
                                                        title="Bukti Pembayaran"
                                                        icon={FileCheck}
                                                        variant="outline"
                                                        size="sm"
                                                        className="rounded-control"
                                                    >
                                                        Bukti
                                                    </FilePreviewButton>
                                                )}
                                                {can.pay &&
                                                    p.allowed_next.length >
                                                        0 && (
                                                        <Button
                                                            size="sm"
                                                            className="rounded-control"
                                                            onClick={() => {
                                                                pay.clearErrors();
                                                                pay.setData({
                                                                    status: p
                                                                        .allowed_next[0]
                                                                        .value,
                                                                    reference:
                                                                        p.reference ??
                                                                        '',
                                                                    failure_reason:
                                                                        '',
                                                                    proof: null,
                                                                });
                                                                setEditingPayment(
                                                                    p,
                                                                );
                                                            }}
                                                        >
                                                            Ubah status
                                                        </Button>
                                                    )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}
                    </div>
                </div>
            </div>

            <Dialog open={adjustOpen} onOpenChange={setAdjustOpen}>
                <DialogContent className="rounded-card sm:max-w-md">
                    <form onSubmit={submitAdjust} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle className="text-navy">
                                Penyesuaian Manual
                            </DialogTitle>
                            <DialogDescription>
                                Mis. bonus, kasbon, potongan ketidakhadiran.
                                Tetap ada saat dihitung ulang.
                            </DialogDescription>
                        </DialogHeader>
                        <FormField
                            id="adj-name"
                            label="Nama"
                            required
                            error={adjust.errors.name}
                        >
                            <Input
                                id="adj-name"
                                value={adjust.data.name}
                                onChange={(e) =>
                                    adjust.setData('name', e.target.value)
                                }
                            />
                        </FormField>
                        <div className="grid grid-cols-2 gap-4">
                            <FormField
                                id="adj-type"
                                label="Jenis"
                                required
                                error={adjust.errors.type}
                            >
                                <select
                                    id="adj-type"
                                    value={adjust.data.type}
                                    onChange={(e) =>
                                        adjust.setData('type', e.target.value)
                                    }
                                    className={selectClassName}
                                >
                                    <option value="earning">Pendapatan</option>
                                    <option value="deduction">Potongan</option>
                                </select>
                            </FormField>
                            <FormField
                                id="adj-amount"
                                label="Nominal (Rp)"
                                required
                                error={adjust.errors.amount}
                            >
                                <Input
                                    id="adj-amount"
                                    inputMode="decimal"
                                    placeholder="250000"
                                    value={adjust.data.amount}
                                    onChange={(e) =>
                                        adjust.setData('amount', e.target.value)
                                    }
                                />
                            </FormField>
                        </div>
                        <FormField
                            id="adj-note"
                            label="Keterangan"
                            error={adjust.errors.note}
                        >
                            <Input
                                id="adj-note"
                                value={adjust.data.note}
                                onChange={(e) =>
                                    adjust.setData('note', e.target.value)
                                }
                            />
                        </FormField>
                        {(adjust.errors as Record<string, string>).status && (
                            <p className="text-sm text-danger">
                                {
                                    (adjust.errors as Record<string, string>)
                                        .status
                                }
                            </p>
                        )}
                        <DialogFooter>
                            <Button
                                type="submit"
                                className="rounded-control"
                                disabled={adjust.processing}
                            >
                                {adjust.processing && <Spinner />}
                                Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editingPayment !== null}
                onOpenChange={(o) => !o && setEditingPayment(null)}
            >
                <DialogContent className="rounded-card sm:max-w-md">
                    <form onSubmit={submitPayment} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle className="text-navy">
                                Ubah Status Pembayaran
                            </DialogTitle>
                            <DialogDescription>
                                Sudah Dibayar wajib disertai nomor referensi
                                transfer atau bukti.
                            </DialogDescription>
                        </DialogHeader>
                        <FormField
                            id="pay-status"
                            label="Status baru"
                            required
                            error={pay.errors.status}
                        >
                            <select
                                id="pay-status"
                                value={pay.data.status}
                                onChange={(e) =>
                                    pay.setData('status', e.target.value)
                                }
                                className={selectClassName}
                            >
                                {editingPayment?.allowed_next.map((s) => (
                                    <option key={s.value} value={s.value}>
                                        {s.label}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        {pay.data.status === 'paid' && (
                            <>
                                <FormField
                                    id="pay-ref"
                                    label="Nomor referensi transfer"
                                    error={pay.errors.reference}
                                >
                                    <Input
                                        id="pay-ref"
                                        value={pay.data.reference}
                                        onChange={(e) =>
                                            pay.setData(
                                                'reference',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="pay-proof"
                                    label="Bukti pembayaran"
                                    hint="PDF/JPG/PNG, maks. 2 MB"
                                    error={pay.errors.proof}
                                >
                                    <Input
                                        id="pay-proof"
                                        type="file"
                                        accept="application/pdf,image/jpeg,image/png"
                                        onChange={(e) =>
                                            pay.setData(
                                                'proof',
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                </FormField>
                            </>
                        )}
                        {pay.data.status === 'failed' && (
                            <FormField
                                id="pay-reason"
                                label="Alasan gagal"
                                required
                                error={pay.errors.failure_reason}
                            >
                                <Input
                                    id="pay-reason"
                                    value={pay.data.failure_reason}
                                    onChange={(e) =>
                                        pay.setData(
                                            'failure_reason',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        )}
                        <DialogFooter>
                            <Button
                                type="submit"
                                className="rounded-control"
                                disabled={pay.processing}
                            >
                                {pay.processing && <Spinner />}
                                Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
