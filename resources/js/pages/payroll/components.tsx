import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { FormField, selectClassName } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { formatUang } from '@/lib/format';
import componentRoutes from '@/routes/payroll/components';

type Component = {
    id: number;
    code: string;
    name: string;
    type: 'earning' | 'deduction';
    calculation: 'fixed' | 'percentage';
    default_amount: string | null;
    default_rate: string | null;
    sort_order: number;
    is_active: boolean;
};

type Props = {
    components: Component[];
    types: Record<string, string>;
    calculations: Record<string, string>;
};

const empty = {
    code: '',
    name: '',
    type: 'earning',
    calculation: 'fixed',
    default_amount: '',
    default_rate: '',
    sort_order: '0',
    is_active: true,
};

const trimRate = (rate: string) => rate.replace(/\.?0+$/, '');

export default function SalaryComponents({
    components,
    types,
    calculations,
}: Props) {
    const [editing, setEditing] = useState<Component | 'new' | null>(null);
    const form = useForm(empty);

    const open = (c: Component | 'new') => {
        form.clearErrors();
        form.setData(
            c === 'new'
                ? empty
                : {
                      code: c.code,
                      name: c.name,
                      type: c.type,
                      calculation: c.calculation,
                      default_amount: c.default_amount ?? '',
                      default_rate: c.default_rate ?? '',
                      sort_order: String(c.sort_order),
                      is_active: c.is_active,
                  },
        );
        setEditing(c);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        };

        if (editing && editing !== 'new') {
            form.put(componentRoutes.update.url(editing.id), options);
        } else {
            form.post(componentRoutes.store.url(), options);
        }
    };

    const group = (type: 'earning' | 'deduction') =>
        components.filter((c) => c.type === type);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Gaji', href: '/gaji' },
                { title: 'Komponen Gaji', href: '#' },
            ]}
        >
            <Head title="Komponen Gaji" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Komponen Gaji"
                    description="Tunjangan dan potongan. Tarif PPh 21 dan BPJS tidak diisi otomatis — tetapkan sesuai aturan yang berlaku."
                    actions={
                        <Button
                            className="rounded-control"
                            onClick={() => open('new')}
                        >
                            <Plus className="size-4" />
                            Tambah Komponen
                        </Button>
                    }
                />
                {(['earning', 'deduction'] as const).map((type) => (
                    <section
                        key={type}
                        className="rounded-card border border-border bg-surface"
                    >
                        <h2 className="border-b border-border p-4 font-semibold text-navy">
                            {types[type]}
                        </h2>
                        <ul className="divide-y divide-border">
                            {group(type).map((c) => (
                                <li
                                    key={c.id}
                                    className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p className="font-medium text-navy">
                                            {c.name}{' '}
                                            <span className="font-mono text-xs text-text-muted">
                                                {c.code}
                                            </span>
                                        </p>
                                        <p className="text-xs text-text-muted">
                                            {calculations[c.calculation]} ·
                                            default{' '}
                                            {c.calculation === 'percentage'
                                                ? c.default_rate
                                                    ? `${trimRate(c.default_rate)}%`
                                                    : 'belum diisi'
                                                : c.default_amount
                                                  ? formatUang(c.default_amount)
                                                  : 'belum diisi'}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {!c.is_active && (
                                            <StatusBadge>Nonaktif</StatusBadge>
                                        )}
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="rounded-control"
                                            onClick={() => open(c)}
                                        >
                                            <Pencil className="size-4" />
                                            Ubah
                                        </Button>
                                    </div>
                                </li>
                            ))}
                            {group(type).length === 0 && (
                                <li className="p-4 text-sm text-text-muted">
                                    Belum ada.
                                </li>
                            )}
                        </ul>
                    </section>
                ))}
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(o) => !o && setEditing(null)}
            >
                <DialogContent className="rounded-card sm:max-w-lg">
                    <form onSubmit={submit} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle className="text-navy">
                                {editing === 'new'
                                    ? 'Tambah Komponen'
                                    : 'Ubah Komponen'}
                            </DialogTitle>
                            <DialogDescription>
                                Nilai default bisa diganti per karyawan di
                                Struktur Gaji.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <FormField
                                id="code"
                                label="Kode"
                                required
                                error={form.errors.code}
                            >
                                <Input
                                    id="code"
                                    className="uppercase"
                                    value={form.data.code}
                                    onChange={(e) =>
                                        form.setData('code', e.target.value)
                                    }
                                />
                            </FormField>
                            <div className="sm:col-span-2">
                                <FormField
                                    id="name"
                                    label="Nama"
                                    required
                                    error={form.errors.name}
                                >
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                    />
                                </FormField>
                            </div>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                id="type"
                                label="Jenis"
                                required
                                error={form.errors.type}
                            >
                                <select
                                    id="type"
                                    value={form.data.type}
                                    onChange={(e) =>
                                        form.setData('type', e.target.value)
                                    }
                                    className={selectClassName}
                                >
                                    {Object.entries(types).map(([v, l]) => (
                                        <option key={v} value={v}>
                                            {l}
                                        </option>
                                    ))}
                                </select>
                            </FormField>
                            <FormField
                                id="calculation"
                                label="Cara hitung"
                                required
                                error={form.errors.calculation}
                            >
                                <select
                                    id="calculation"
                                    value={form.data.calculation}
                                    onChange={(e) =>
                                        form.setData(
                                            'calculation',
                                            e.target.value,
                                        )
                                    }
                                    className={selectClassName}
                                >
                                    {Object.entries(calculations).map(
                                        ([v, l]) => (
                                            <option key={v} value={v}>
                                                {l}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </FormField>
                        </div>
                        {form.data.calculation === 'fixed' ? (
                            <FormField
                                id="default_amount"
                                label="Nominal default (Rp)"
                                hint="Boleh kosong"
                                error={form.errors.default_amount}
                            >
                                <Input
                                    id="default_amount"
                                    inputMode="decimal"
                                    value={form.data.default_amount}
                                    onChange={(e) =>
                                        form.setData(
                                            'default_amount',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        ) : (
                            <FormField
                                id="default_rate"
                                label="Persen default (%)"
                                hint="Dari gaji pokok. Boleh kosong"
                                error={form.errors.default_rate}
                            >
                                <Input
                                    id="default_rate"
                                    inputMode="decimal"
                                    value={form.data.default_rate}
                                    onChange={(e) =>
                                        form.setData(
                                            'default_rate',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        )}
                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="is_active"
                                checked={form.data.is_active}
                                onCheckedChange={(c) =>
                                    form.setData('is_active', c === true)
                                }
                            />
                            <Label htmlFor="is_active" className="font-normal">
                                Komponen aktif
                            </Label>
                        </div>
                        <DialogFooter>
                            <Button
                                type="submit"
                                className="rounded-control"
                                disabled={form.processing}
                            >
                                {form.processing && <Spinner />}
                                Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
