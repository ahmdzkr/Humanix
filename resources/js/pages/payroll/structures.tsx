import { Head, useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import type { Column } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
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
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { todayWib } from '@/lib/attendance';
import { formatUang } from '@/lib/format';
import { formatRange } from '@/lib/requests';
import structures from '@/routes/payroll/structures';
import type { Paginated } from '@/types';

type Component = {
    id: number;
    code: string;
    name: string;
    type: 'earning' | 'deduction';
    calculation: 'fixed' | 'percentage';
    default_amount: string | null;
    default_rate: string | null;
};

type StructureItem = {
    salary_component_id: number;
    amount: string | null;
    rate: string | null;
};

type Row = {
    id: number;
    full_name: string;
    nik: string;
    department: string | null;
    position: string | null;
    structure: {
        base_salary: string;
        effective_from: string;
        items: StructureItem[];
    } | null;
};

type Props = {
    employees: Paginated<Row>;
    filters: { search: string };
    components: Component[];
};

type ItemState = Record<number, { enabled: boolean; value: string }>;

export default function SalaryStructures({
    employees,
    filters,
    components,
}: Props) {
    const [editing, setEditing] = useState<Row | null>(null);
    const [items, setItems] = useState<ItemState>({});
    const form = useForm<{
        base_salary: string;
        effective_from: string;
        notes: string;
        items: {
            salary_component_id: number;
            amount: string | null;
            rate: string | null;
        }[];
    }>({ base_salary: '', effective_from: todayWib(), notes: '', items: [] });

    const open = (row: Row) => {
        const current = new Map(
            row.structure?.items.map((i) => [i.salary_component_id, i]) ?? [],
        );
        setItems(
            Object.fromEntries(
                components.map((c) => {
                    const existing = current.get(c.id);
                    const value =
                        c.calculation === 'percentage'
                            ? (existing?.rate ?? c.default_rate ?? '')
                            : (existing?.amount ?? c.default_amount ?? '');

                    return [c.id, { enabled: !!existing, value: value ?? '' }];
                }),
            ),
        );
        form.clearErrors();
        form.setData({
            base_salary: row.structure?.base_salary ?? '',
            effective_from: todayWib(),
            notes: '',
            items: [],
        });
        setEditing(row);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!editing) {
            return;
        }

        form.transform((data) => ({
            ...data,
            items: components
                .filter((c) => items[c.id]?.enabled)
                .map((c) => ({
                    salary_component_id: c.id,
                    amount:
                        c.calculation === 'fixed'
                            ? items[c.id].value || null
                            : null,
                    rate:
                        c.calculation === 'percentage'
                            ? items[c.id].value || null
                            : null,
                })),
        }));
        form.post(structures.store.url(editing.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };

    const columns: Column<Row>[] = [
        {
            key: 'employee',
            header: 'Karyawan',
            cell: (r) => (
                <span>
                    <span className="block font-semibold text-navy">
                        {r.full_name}
                    </span>
                    <span className="block text-xs text-text-muted">
                        {r.nik} · {r.position ?? '—'}
                    </span>
                </span>
            ),
        },
        {
            key: 'base',
            header: 'Gaji pokok',
            cell: (r) =>
                r.structure ? (
                    <span>
                        <span className="block font-semibold">
                            {formatUang(r.structure.base_salary)}
                        </span>
                        <span className="block text-xs text-text-muted">
                            sejak{' '}
                            {formatRange(
                                r.structure.effective_from,
                                r.structure.effective_from,
                            )}
                        </span>
                    </span>
                ) : (
                    <span className="text-sm text-danger">Belum diatur</span>
                ),
        },
        {
            key: 'items',
            header: 'Komponen',
            cell: (r) => r.structure?.items.length ?? 0,
        },
        {
            key: 'actions',
            header: 'Aksi',
            className: 'md:text-right',
            cell: (r) => (
                <Button
                    variant="outline"
                    size="sm"
                    className="rounded-control"
                    onClick={() => open(r)}
                >
                    <Pencil className="size-4" />
                    {r.structure ? 'Ubah' : 'Atur'}
                </Button>
            ),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Gaji', href: '/gaji' },
                { title: 'Struktur Gaji', href: '#' },
            ]}
        >
            <Head title="Struktur Gaji" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Struktur Gaji"
                    description="Gaji pokok dan komponen per karyawan. Perubahan membuat struktur baru yang berlaku mulai tanggal tertentu; riwayat lama tetap tersimpan."
                />
                <DataTable
                    rows={employees}
                    columns={columns}
                    filters={filters}
                    rowKey={(r) => r.id}
                    searchPlaceholder="Cari nama atau NIK…"
                    emptyTitle="Belum ada karyawan aktif"
                />
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(o) => !o && setEditing(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto rounded-card sm:max-w-lg">
                    <form onSubmit={submit} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle className="text-navy">
                                Struktur Gaji — {editing?.full_name}
                            </DialogTitle>
                            <DialogDescription>
                                Tercatat di audit log.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                id="base_salary"
                                label="Gaji pokok (Rp)"
                                required
                                error={form.errors.base_salary}
                            >
                                <Input
                                    id="base_salary"
                                    inputMode="decimal"
                                    value={form.data.base_salary}
                                    onChange={(e) =>
                                        form.setData(
                                            'base_salary',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="effective_from"
                                label="Berlaku mulai"
                                required
                                error={form.errors.effective_from}
                            >
                                <Input
                                    id="effective_from"
                                    type="date"
                                    value={form.data.effective_from}
                                    onChange={(e) =>
                                        form.setData(
                                            'effective_from',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </div>
                        <fieldset className="grid gap-2">
                            <legend className="mb-1 text-sm font-medium">
                                Komponen
                            </legend>
                            {components.map((c) => (
                                <div
                                    key={c.id}
                                    className="flex items-center gap-3 rounded-control border border-border p-2"
                                >
                                    <Checkbox
                                        id={`c-${c.id}`}
                                        checked={items[c.id]?.enabled ?? false}
                                        onCheckedChange={(checked) =>
                                            setItems((s) => ({
                                                ...s,
                                                [c.id]: {
                                                    ...s[c.id],
                                                    enabled: checked === true,
                                                },
                                            }))
                                        }
                                    />
                                    <label
                                        htmlFor={`c-${c.id}`}
                                        className="flex-1 text-sm"
                                    >
                                        {c.name}
                                        <span className="block text-xs text-text-muted">
                                            {c.type === 'earning'
                                                ? 'Pendapatan'
                                                : 'Potongan'}{' '}
                                            ·{' '}
                                            {c.calculation === 'percentage'
                                                ? '% gaji pokok'
                                                : 'nominal'}
                                        </span>
                                    </label>
                                    <Input
                                        aria-label={`Nilai ${c.name}`}
                                        inputMode="decimal"
                                        className="w-32"
                                        placeholder={
                                            c.calculation === 'percentage'
                                                ? '%'
                                                : 'Rp'
                                        }
                                        disabled={!items[c.id]?.enabled}
                                        value={items[c.id]?.value ?? ''}
                                        onChange={(e) =>
                                            setItems((s) => ({
                                                ...s,
                                                [c.id]: {
                                                    ...s[c.id],
                                                    value: e.target.value,
                                                },
                                            }))
                                        }
                                    />
                                </div>
                            ))}
                        </fieldset>
                        {Object.entries(form.errors)
                            .filter(([k]) => k.startsWith('items'))
                            .map(([k, v]) => (
                                <p key={k} className="text-sm text-danger">
                                    {v}
                                </p>
                            ))}
                        <DialogFooter>
                            <Button
                                type="submit"
                                className="rounded-control"
                                disabled={form.processing}
                            >
                                {form.processing && <Spinner />}
                                Simpan struktur
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
