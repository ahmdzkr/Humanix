import { Head, Link } from '@inertiajs/react';
import { FileSpreadsheet, Plus } from 'lucide-react';
import type { Column } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { FilePreviewButton } from '@/components/file-preview';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { create, show } from '@/routes/employees';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    nik: string;
    full_name: string;
    email: string | null;
    department: string | null;
    position: string | null;
    employment_status: string;
    is_active: boolean;
    photo_url: string | null;
};

type Props = {
    employees: Paginated<Row>;
    filters: {
        search: string;
        department: string;
        employment_status: string;
        status: string;
    };
    departmentOptions: { id: number; name: string }[];
    employmentStatuses: { value: string; label: string }[];
    can: { create: boolean; export: boolean };
};

export default function EmployeesIndex({
    employees,
    filters,
    departmentOptions,
    employmentStatuses,
    can,
}: Props) {
    const exportQuery = new URLSearchParams(
        Object.entries(filters).filter(([, v]) => v !== ''),
    ).toString();

    const columns: Column<Row>[] = [
        {
            key: 'name',
            header: 'Karyawan',
            cell: (row) => (
                <Link
                    href={show.url(row.id)}
                    className="flex items-center gap-3 text-left"
                >
                    <EmployeeAvatar
                        name={row.full_name}
                        photoUrl={row.photo_url}
                        className="size-9"
                    />
                    <span className="min-w-0">
                        <span className="block font-semibold text-navy hover:text-primary">
                            {row.full_name}
                        </span>
                        <span className="block font-mono text-xs text-text-muted">
                            {row.nik}
                        </span>
                    </span>
                </Link>
            ),
        },
        {
            key: 'position',
            header: 'Jabatan',
            cell: (row) => (
                <span>
                    <span className="block">{row.position ?? '—'}</span>
                    <span className="block text-xs text-text-muted">
                        {row.department ?? '—'}
                    </span>
                </span>
            ),
        },
        {
            key: 'employment',
            header: 'Kepegawaian',
            cell: (row) => (
                <StatusBadge tone="info">{row.employment_status}</StatusBadge>
            ),
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
                <Button
                    asChild
                    variant="outline"
                    size="sm"
                    className="rounded-control"
                >
                    <Link href={show.url(row.id)}>Lihat</Link>
                </Button>
            ),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Data Karyawan', href: '/karyawan' },
            ]}
        >
            <Head title="Data Karyawan" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Data Karyawan"
                    description={`${employees.total} karyawan sesuai filter dan hak akses Anda.`}
                    actions={
                        <>
                            {can.export && (
                                <FilePreviewButton
                                    url={`/karyawan/ekspor${exportQuery ? `?${exportQuery}` : ''}`}
                                    kind="excel"
                                    title="Data Karyawan"
                                    icon={FileSpreadsheet}
                                    variant="outline"
                                    className="rounded-control"
                                >
                                    Ekspor Excel
                                </FilePreviewButton>
                            )}
                            {can.create && (
                                <Button asChild className="rounded-control">
                                    <Link href={create.url()}>
                                        <Plus className="size-4" />
                                        Tambah Karyawan
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <DataTable
                    rows={employees}
                    columns={columns}
                    filters={filters}
                    rowKey={(row) => row.id}
                    searchPlaceholder="Cari nama, NIK, atau email…"
                    emptyTitle="Belum ada karyawan"
                    emptyDescription={
                        can.create
                            ? 'Tambahkan karyawan pertama dengan tombol Tambah Karyawan.'
                            : 'Belum ada karyawan yang dapat Anda lihat.'
                    }
                    filterDefs={[
                        {
                            name: 'department',
                            label: 'Departemen',
                            options: departmentOptions.map((d) => ({
                                value: String(d.id),
                                label: d.name,
                            })),
                        },
                        {
                            name: 'employment_status',
                            label: 'Kepegawaian',
                            options: employmentStatuses,
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
        </AppLayout>
    );
}
