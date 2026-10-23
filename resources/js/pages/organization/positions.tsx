import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { Column } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
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
import { destroy, store, update } from '@/routes/positions';
import type { Paginated } from '@/types';

type Position = {
    id: number;
    code: string;
    name: string;
    department_id: number | null;
    department_name: string | null;
    level: number;
    description: string | null;
    is_active: boolean;
};

type Option = { id: number; name: string; is_active: boolean };

type Props = {
    positions: Paginated<Position>;
    filters: { search: string; department: string; status: string };
    departmentOptions: Option[];
};

type FormData = {
    code: string;
    name: string;
    department_id: string;
    level: string;
    description: string;
    is_active: boolean;
};

const emptyForm: FormData = {
    code: '',
    name: '',
    department_id: '',
    level: '1',
    description: '',
    is_active: true,
};

export default function Positions({
    positions,
    filters,
    departmentOptions,
}: Props) {
    const [editing, setEditing] = useState<Position | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Position | null>(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);
    const form = useForm<FormData>(emptyForm);

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData(emptyForm);
        setFormOpen(true);
    };

    const openEdit = (position: Position) => {
        setEditing(position);
        form.clearErrors();
        form.setData({
            code: position.code,
            name: position.name,
            department_id: position.department_id
                ? String(position.department_id)
                : '',
            level: String(position.level),
            description: position.description ?? '',
            is_active: position.is_active,
        });
        setFormOpen(true);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setFormOpen(false),
        };

        if (editing) {
            form.put(update.url(editing.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    const confirmDelete = () => {
        if (!deleting) {
            return;
        }

        router.delete(destroy.url(deleting.id), {
            preserveScroll: true,
            onStart: () => setDeleteProcessing(true),
            onFinish: () => {
                setDeleteProcessing(false);
                setDeleting(null);
            },
        });
    };

    const columns: Column<Position>[] = [
        {
            key: 'code',
            header: 'Kode',
            cell: (row) => (
                <span className="font-mono text-xs font-semibold text-navy">
                    {row.code}
                </span>
            ),
        },
        {
            key: 'name',
            header: 'Jabatan',
            cell: (row) => (
                <span className="font-medium text-navy">{row.name}</span>
            ),
        },
        {
            key: 'department',
            header: 'Departemen',
            cell: (row) => row.department_name ?? 'Lintas departemen',
        },
        {
            key: 'level',
            header: 'Level',
            cell: (row) => row.level,
            className: 'md:text-center',
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) =>
                row.is_active ? (
                    <StatusBadge tone="success">Aktif</StatusBadge>
                ) : (
                    <StatusBadge>Nonaktif</StatusBadge>
                ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            className: 'md:text-right',
            cell: (row) => (
                <div className="flex justify-end gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        className="rounded-control"
                        onClick={() => openEdit(row)}
                        aria-label={`Ubah ${row.name}`}
                    >
                        <Pencil className="size-4" />
                        <span className="hidden lg:inline">Ubah</span>
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        className="rounded-control text-danger hover:text-danger"
                        onClick={() => setDeleting(row)}
                        aria-label={`Hapus ${row.name}`}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pengaturan', href: '/pengaturan' },
                { title: 'Jabatan', href: '/pengaturan/jabatan' },
            ]}
        >
            <Head title="Jabatan" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Jabatan"
                    description="Kelola jabatan, level, dan departemen tempat jabatan berada."
                    actions={
                        <Button
                            className="rounded-control"
                            onClick={openCreate}
                        >
                            <Plus className="size-4" />
                            Tambah Jabatan
                        </Button>
                    }
                />

                <DataTable
                    rows={positions}
                    columns={columns}
                    filters={filters}
                    rowKey={(row) => row.id}
                    searchPlaceholder="Cari kode atau nama jabatan…"
                    emptyTitle="Belum ada jabatan"
                    emptyDescription="Tambahkan jabatan, misalnya Staff HR atau Manager Keuangan."
                    filterDefs={[
                        {
                            name: 'department',
                            label: 'Departemen',
                            options: departmentOptions.map((option) => ({
                                value: String(option.id),
                                label: option.name,
                            })),
                        },
                        {
                            name: 'status',
                            label: 'Status',
                            options: [
                                { value: 'active', label: 'Aktif' },
                                { value: 'inactive', label: 'Nonaktif' },
                            ],
                        },
                    ]}
                />
            </div>

            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="rounded-card sm:max-w-lg">
                    <form onSubmit={submit} className="grid gap-5">
                        <DialogHeader>
                            <DialogTitle className="text-navy">
                                {editing ? 'Ubah Jabatan' : 'Tambah Jabatan'}
                            </DialogTitle>
                            <DialogDescription>
                                Kolom bertanda * wajib diisi.
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
                                    value={form.data.code}
                                    onChange={(e) =>
                                        form.setData('code', e.target.value)
                                    }
                                    placeholder="HR-STF"
                                    maxLength={20}
                                    className="uppercase"
                                />
                            </FormField>
                            <div className="sm:col-span-2">
                                <FormField
                                    id="name"
                                    label="Nama jabatan"
                                    required
                                    error={form.errors.name}
                                >
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                        placeholder="Staff HR"
                                        maxLength={100}
                                    />
                                </FormField>
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="sm:col-span-2">
                                <FormField
                                    id="department_id"
                                    label="Departemen"
                                    hint="Kosongkan untuk jabatan lintas departemen (mis. Direktur)."
                                    error={form.errors.department_id}
                                >
                                    <select
                                        id="department_id"
                                        value={form.data.department_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'department_id',
                                                e.target.value,
                                            )
                                        }
                                        className={selectClassName}
                                    >
                                        <option value="">
                                            — Lintas departemen —
                                        </option>
                                        {departmentOptions.map((option) => (
                                            <option
                                                key={option.id}
                                                value={option.id}
                                            >
                                                {option.name}
                                                {option.is_active
                                                    ? ''
                                                    : ' (nonaktif)'}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>
                            </div>
                            <FormField
                                id="level"
                                label="Level"
                                required
                                hint="1 terendah, 10 tertinggi"
                                error={form.errors.level}
                            >
                                <Input
                                    id="level"
                                    type="number"
                                    min={1}
                                    max={10}
                                    value={form.data.level}
                                    onChange={(e) =>
                                        form.setData('level', e.target.value)
                                    }
                                />
                            </FormField>
                        </div>

                        <FormField
                            id="description"
                            label="Deskripsi"
                            error={form.errors.description}
                        >
                            <textarea
                                id="description"
                                rows={3}
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                                className="w-full rounded-control border border-input bg-surface px-3 py-2 text-sm focus:border-primary-bright focus:ring-2 focus:ring-primary-bright/20 focus:outline-none"
                                maxLength={1000}
                            />
                        </FormField>

                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="is_active"
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked === true)
                                }
                            />
                            <Label htmlFor="is_active" className="font-normal">
                                Jabatan aktif
                            </Label>
                        </div>

                        <DialogFooter className="gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="rounded-control"
                                onClick={() => setFormOpen(false)}
                            >
                                Batal
                            </Button>
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

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title="Hapus jabatan?"
                description={`Jabatan ${deleting?.name ?? ''} akan dihapus permanen. Tindakan ini tercatat di audit log.`}
                confirmLabel="Hapus"
                destructive
                processing={deleteProcessing}
                onConfirm={confirmDelete}
            />
        </AppLayout>
    );
}
