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
import { destroy, store, update } from '@/routes/departments';
import type { Paginated } from '@/types';

type Department = {
    id: number;
    code: string;
    name: string;
    parent_id: number | null;
    parent_name: string | null;
    head_employee_id: number | null;
    head_name: string | null;
    employees_count: number;
    description: string | null;
    is_active: boolean;
    positions_count: number;
    children_count: number;
};

type Option = { id: number; name: string; is_active: boolean };

type Props = {
    departments: Paginated<Department>;
    filters: { search: string; status: string };
    parentOptions: Option[];
    headOptions: { id: number; full_name: string; department_id: number }[];
};

type FormData = {
    code: string;
    name: string;
    parent_id: string;
    head_employee_id: string;
    description: string;
    is_active: boolean;
};

const emptyForm: FormData = {
    code: '',
    name: '',
    parent_id: '',
    head_employee_id: '',
    description: '',
    is_active: true,
};

export default function Departments({
    departments,
    filters,
    parentOptions,
    headOptions,
}: Props) {
    const [editing, setEditing] = useState<Department | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Department | null>(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);
    const form = useForm<FormData>(emptyForm);

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData(emptyForm);
        setFormOpen(true);
    };

    const openEdit = (department: Department) => {
        setEditing(department);
        form.clearErrors();
        form.setData({
            code: department.code,
            name: department.name,
            parent_id: department.parent_id ? String(department.parent_id) : '',
            head_employee_id: department.head_employee_id
                ? String(department.head_employee_id)
                : '',
            description: department.description ?? '',
            is_active: department.is_active,
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

    const columns: Column<Department>[] = [
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
            header: 'Departemen',
            cell: (row) => (
                <span className="font-medium text-navy">{row.name}</span>
            ),
        },
        {
            key: 'head',
            header: 'Kepala',
            cell: (row) => row.head_name ?? '—',
        },
        {
            key: 'employees',
            header: 'Karyawan',
            cell: (row) => row.employees_count,
            className: 'md:text-center',
        },
        {
            key: 'parent',
            header: 'Induk',
            cell: (row) => row.parent_name ?? '—',
        },
        {
            key: 'positions',
            header: 'Jabatan',
            cell: (row) => row.positions_count,
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
                { title: 'Departemen', href: '/pengaturan/departemen' },
            ]}
        >
            <Head title="Departemen" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Departemen"
                    description="Kelola departemen dan hierarki organisasi perusahaan."
                    actions={
                        <Button
                            className="rounded-control"
                            onClick={openCreate}
                        >
                            <Plus className="size-4" />
                            Tambah Departemen
                        </Button>
                    }
                />

                <DataTable
                    rows={departments}
                    columns={columns}
                    filters={filters}
                    rowKey={(row) => row.id}
                    searchPlaceholder="Cari kode atau nama departemen…"
                    emptyTitle="Belum ada departemen"
                    emptyDescription="Tambahkan departemen pertama untuk mulai menyusun struktur organisasi."
                    filterDefs={[
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
                                {editing
                                    ? 'Ubah Departemen'
                                    : 'Tambah Departemen'}
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
                                    placeholder="HR"
                                    maxLength={20}
                                    className="uppercase"
                                />
                            </FormField>
                            <div className="sm:col-span-2">
                                <FormField
                                    id="name"
                                    label="Nama departemen"
                                    required
                                    error={form.errors.name}
                                >
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                        placeholder="Human Resources"
                                        maxLength={100}
                                    />
                                </FormField>
                            </div>
                        </div>

                        <FormField
                            id="parent_id"
                            label="Departemen induk"
                            hint="Kosongkan jika departemen ini berada di tingkat teratas."
                            error={form.errors.parent_id}
                        >
                            <select
                                id="parent_id"
                                value={form.data.parent_id}
                                onChange={(e) =>
                                    form.setData('parent_id', e.target.value)
                                }
                                className={selectClassName}
                            >
                                <option value="">— Tidak ada —</option>
                                {parentOptions
                                    .filter(
                                        (option) => option.id !== editing?.id,
                                    )
                                    .map((option) => (
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

                        {editing && (
                            <FormField
                                id="head_employee_id"
                                label="Kepala departemen"
                                hint="Hanya karyawan aktif di departemen ini."
                                error={form.errors.head_employee_id}
                            >
                                <select
                                    id="head_employee_id"
                                    value={form.data.head_employee_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'head_employee_id',
                                            e.target.value,
                                        )
                                    }
                                    className={selectClassName}
                                >
                                    <option value="">
                                        — Belum ditentukan —
                                    </option>
                                    {headOptions
                                        .filter(
                                            (o) =>
                                                o.department_id === editing.id,
                                        )
                                        .map((o) => (
                                            <option key={o.id} value={o.id}>
                                                {o.full_name}
                                            </option>
                                        ))}
                                </select>
                            </FormField>
                        )}

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
                                Departemen aktif
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
                title="Hapus departemen?"
                description={
                    deleting &&
                    (deleting.children_count > 0 ||
                        deleting.employees_count > 0 ||
                        deleting.positions_count > 0)
                        ? `${deleting.name} masih memiliki sub-departemen, jabatan, atau karyawan, jadi tidak bisa dihapus. Ubah statusnya menjadi nonaktif sebagai gantinya.`
                        : `Departemen ${deleting?.name ?? ''} akan dihapus permanen. Tindakan ini tercatat di audit log.`
                }
                confirmLabel="Hapus"
                destructive
                processing={deleteProcessing}
                onConfirm={confirmDelete}
            />
        </AppLayout>
    );
}
