import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    History,
    IdCard,
    KeyRound,
    Mail,
    Pencil,
    Power,
    QrCode,
    ShieldCheck,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { FilePreviewButton } from '@/components/file-preview';
import { selectClassName } from '@/components/form-field';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatJam, formatTanggal } from '@/lib/format';
import {
    edit,
    idCard,
    index,
    passwordLink,
    role as roleRoute,
    rotateQr,
    status as statusRoute,
} from '@/routes/employees';

type Employee = {
    id: number;
    nik: string;
    full_name: string;
    email: string | null;
    gender_label: string | null;
    birth_place: string | null;
    birth_date: string | null;
    phone: string | null;
    address: string | null;
    department: string | null;
    position: string | null;
    manager: { id: number; full_name: string } | null;
    employment_status: string;
    join_date: string | null;
    contract_end_date: string | null;
    is_active: boolean;
    deactivated_at: string | null;
    photo_url: string | null;
    role: string | null;
    bank_account: {
        bank_name: string;
        account_number: string;
        account_holder: string;
    } | null;
    subordinates_count: number;
    schedule: string | null;
};

type HistoryItem = {
    id: number;
    type: string;
    label: string;
    changes: Record<string, [string | null, string | null]> | null;
    note: string | null;
    actor: string;
    created_at: string | null;
};

type Props = {
    employee: Employee;
    histories: HistoryItem[];
    roles: string[];
    can: {
        update: boolean;
        changeStatus: boolean;
        rotateQr: boolean;
        printIdCard: boolean;
        changeRole: boolean;
        viewBankAccount: boolean;
    };
};

const tanggal = (value: string | null) =>
    value ? formatTanggal(`${value}T00:00:00+07:00`) : '—';

function Info({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-xs text-text-muted">{label}</dt>
            <dd className="mt-0.5 text-sm font-medium break-words text-text">
                {children || '—'}
            </dd>
        </div>
    );
}

function Card({
    title,
    icon,
    children,
}: {
    title: string;
    icon?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="rounded-card border border-border bg-surface p-5">
            <h2 className="mb-4 flex items-center gap-2 text-base font-semibold text-navy">
                {icon}
                {title}
            </h2>
            {children}
        </section>
    );
}

export default function EmployeeShow({
    employee,
    histories,
    roles,
    can,
}: Props) {
    const [confirm, setConfirm] = useState<'status' | 'qr' | null>(null);
    const [processing, setProcessing] = useState(false);
    const roleForm = useForm({ role: employee.role ?? 'Karyawan' });

    const run = (action: () => void) => {
        setProcessing(true);
        action();
    };

    const finish = {
        preserveScroll: true,
        onFinish: () => {
            setProcessing(false);
            setConfirm(null);
        },
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Data Karyawan', href: index.url() },
                { title: employee.full_name, href: '#' },
            ]}
        >
            <Head title={employee.full_name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Kepala profil */}
                <section className="relative overflow-hidden rounded-card border border-border bg-surface p-5 md:p-6">
                    <div className="pointer-events-none absolute -top-16 -right-16 size-48 rounded-full bg-gradient-to-br from-primary/15 to-cyan/20" />
                    <div className="relative flex flex-col gap-5 md:flex-row md:items-center">
                        <EmployeeAvatar
                            name={employee.full_name}
                            photoUrl={employee.photo_url}
                            className="size-20 text-xl"
                        />
                        <div className="flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-2xl font-bold text-navy">
                                    {employee.full_name}
                                </h1>
                                {employee.is_active ? (
                                    <StatusBadge tone="success">
                                        Aktif
                                    </StatusBadge>
                                ) : (
                                    <StatusBadge tone="danger">
                                        Nonaktif
                                    </StatusBadge>
                                )}
                            </div>
                            <p className="mt-1 text-sm text-text-muted">
                                {employee.position ?? '—'} ·{' '}
                                {employee.department ?? '—'}
                            </p>
                            <p className="mt-1 font-mono text-xs text-text-muted">
                                NIK {employee.nik}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {can.printIdCard && (
                                <FilePreviewButton
                                    url={idCard.url(employee.id)}
                                    kind="pdf"
                                    title={`ID Card ${employee.full_name}`}
                                    icon={IdCard}
                                    variant="outline"
                                    className="rounded-control"
                                >
                                    ID Card
                                </FilePreviewButton>
                            )}
                            {can.update && (
                                <Button asChild className="rounded-control">
                                    <Link href={edit.url(employee.id)}>
                                        <Pencil className="size-4" />
                                        Ubah
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-3">
                    <div className="flex flex-col gap-6 xl:col-span-2">
                        <Card title="Data Pribadi">
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Info label="Email">{employee.email}</Info>
                                <Info label="Telepon">{employee.phone}</Info>
                                <Info label="Jenis kelamin">
                                    {employee.gender_label}
                                </Info>
                                <Info label="Tempat, tanggal lahir">
                                    {[
                                        employee.birth_place,
                                        employee.birth_date &&
                                            tanggal(employee.birth_date),
                                    ]
                                        .filter(Boolean)
                                        .join(', ')}
                                </Info>
                                <div className="sm:col-span-2">
                                    <Info label="Alamat">
                                        {employee.address}
                                    </Info>
                                </div>
                            </dl>
                        </Card>

                        <Card title="Kepegawaian">
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Info label="Departemen">
                                    {employee.department}
                                </Info>
                                <Info label="Jabatan">{employee.position}</Info>
                                <Info label="Atasan langsung">
                                    {employee.manager?.full_name}
                                </Info>
                                <Info label="Jumlah bawahan langsung">
                                    {String(employee.subordinates_count)}
                                </Info>
                                <Info label="Jadwal kerja">
                                    {employee.schedule ?? 'Belum ada jadwal'}
                                </Info>
                                <Info label="Status kepegawaian">
                                    {employee.employment_status}
                                </Info>
                                <Info label="Tanggal masuk">
                                    {tanggal(employee.join_date)}
                                </Info>
                                {employee.contract_end_date && (
                                    <Info label="Akhir kontrak">
                                        {tanggal(employee.contract_end_date)}
                                    </Info>
                                )}
                                {!employee.is_active &&
                                    employee.deactivated_at && (
                                        <Info label="Dinonaktifkan pada">
                                            {formatTanggal(
                                                employee.deactivated_at,
                                            )}
                                        </Info>
                                    )}
                            </dl>
                        </Card>

                        <Card
                            title="Riwayat Perubahan"
                            icon={<History className="size-4 text-primary" />}
                        >
                            {histories.length === 0 ? (
                                <p className="text-sm text-text-muted">
                                    Belum ada riwayat.
                                </p>
                            ) : (
                                <ol className="space-y-4">
                                    {histories.map((h) => (
                                        <li
                                            key={h.id}
                                            className="border-l-2 border-primary/20 pl-4"
                                        >
                                            <p className="text-sm font-semibold text-navy">
                                                {h.label}
                                            </p>
                                            <p className="text-xs text-text-muted">
                                                {h.actor}
                                                {h.created_at &&
                                                    ` · ${formatTanggal(h.created_at)}, ${formatJam(h.created_at)}`}
                                            </p>
                                            {h.changes && (
                                                <ul className="mt-2 space-y-1 text-sm">
                                                    {Object.entries(
                                                        h.changes,
                                                    ).map(
                                                        ([
                                                            field,
                                                            [from, to],
                                                        ]) => (
                                                            <li key={field}>
                                                                <span className="text-text-muted">
                                                                    {field}:
                                                                </span>{' '}
                                                                {from !==
                                                                    null && (
                                                                    <>
                                                                        <span className="line-through decoration-danger/60">
                                                                            {
                                                                                from
                                                                            }
                                                                        </span>{' '}
                                                                        →{' '}
                                                                    </>
                                                                )}
                                                                <span className="font-medium">
                                                                    {to ?? '—'}
                                                                </span>
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            )}
                                            {h.note && (
                                                <p className="mt-1 text-sm text-text-muted italic">
                                                    {h.note}
                                                </p>
                                            )}
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </Card>
                    </div>

                    <div className="flex flex-col gap-6">
                        {can.viewBankAccount && (
                            <Card title="Rekening Bank">
                                {employee.bank_account ? (
                                    <dl className="grid gap-4">
                                        <Info label="Bank">
                                            {employee.bank_account.bank_name}
                                        </Info>
                                        <Info label="Nomor rekening">
                                            <span className="font-mono">
                                                {
                                                    employee.bank_account
                                                        .account_number
                                                }
                                            </span>
                                        </Info>
                                        <Info label="Atas nama">
                                            {
                                                employee.bank_account
                                                    .account_holder
                                            }
                                        </Info>
                                    </dl>
                                ) : (
                                    <p className="text-sm text-text-muted">
                                        Rekening belum diisi.
                                    </p>
                                )}
                            </Card>
                        )}

                        {(can.update || can.changeStatus || can.rotateQr) && (
                            <Card
                                title="Akun & Keamanan"
                                icon={
                                    <ShieldCheck className="size-4 text-primary" />
                                }
                            >
                                <p className="mb-4 text-sm text-text-muted">
                                    Role akun:{' '}
                                    <span className="font-semibold text-navy">
                                        {employee.role ?? 'Belum ada'}
                                    </span>
                                </p>
                                <div className="flex flex-col gap-2">
                                    {can.update && (
                                        <Button
                                            variant="outline"
                                            className="justify-start rounded-control"
                                            disabled={processing}
                                            onClick={() =>
                                                run(() =>
                                                    router.post(
                                                        passwordLink.url(
                                                            employee.id,
                                                        ),
                                                        {},
                                                        finish,
                                                    ),
                                                )
                                            }
                                        >
                                            <Mail className="size-4" />
                                            Kirim tautan atur kata sandi
                                        </Button>
                                    )}
                                    {can.rotateQr && (
                                        <Button
                                            variant="outline"
                                            className="justify-start rounded-control"
                                            onClick={() => setConfirm('qr')}
                                        >
                                            <QrCode className="size-4" />
                                            Ganti QR ID Card
                                        </Button>
                                    )}
                                    {can.changeStatus && (
                                        <Button
                                            variant="outline"
                                            className={
                                                employee.is_active
                                                    ? 'justify-start rounded-control text-danger hover:text-danger'
                                                    : 'justify-start rounded-control text-success hover:text-success'
                                            }
                                            onClick={() => setConfirm('status')}
                                        >
                                            <Power className="size-4" />
                                            {employee.is_active
                                                ? 'Nonaktifkan karyawan'
                                                : 'Aktifkan kembali'}
                                        </Button>
                                    )}
                                </div>
                            </Card>
                        )}

                        {can.changeRole && (
                            <Card
                                title="Role Akun"
                                icon={
                                    <KeyRound className="size-4 text-primary" />
                                }
                            >
                                <form
                                    className="flex flex-col gap-3"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        roleForm.put(
                                            roleRoute.url(employee.id),
                                            {
                                                preserveScroll: true,
                                            },
                                        );
                                    }}
                                >
                                    <label
                                        htmlFor="role"
                                        className="text-sm text-text-muted"
                                    >
                                        Menentukan menu dan hak akses akun ini.
                                    </label>
                                    <select
                                        id="role"
                                        value={roleForm.data.role}
                                        onChange={(e) =>
                                            roleForm.setData(
                                                'role',
                                                e.target.value,
                                            )
                                        }
                                        className={selectClassName}
                                    >
                                        {roles.map((r) => (
                                            <option key={r} value={r}>
                                                {r}
                                            </option>
                                        ))}
                                    </select>
                                    <Button
                                        type="submit"
                                        className="rounded-control"
                                        disabled={
                                            roleForm.processing ||
                                            roleForm.data.role === employee.role
                                        }
                                    >
                                        Simpan role
                                    </Button>
                                </form>
                            </Card>
                        )}
                    </div>
                </div>
            </div>

            <ConfirmDialog
                open={confirm === 'status'}
                onOpenChange={(open) => !open && setConfirm(null)}
                title={
                    employee.is_active
                        ? 'Nonaktifkan karyawan?'
                        : 'Aktifkan kembali karyawan?'
                }
                description={
                    employee.is_active
                        ? `${employee.full_name} tidak akan bisa login lagi dan sesi yang sedang terbuka akan diakhiri. Data tetap disimpan.`
                        : `${employee.full_name} akan bisa login kembali.`
                }
                confirmLabel={employee.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                destructive={employee.is_active}
                processing={processing}
                onConfirm={() =>
                    run(() =>
                        router.patch(
                            statusRoute.url(employee.id),
                            { is_active: !employee.is_active },
                            finish,
                        ),
                    )
                }
            />

            <ConfirmDialog
                open={confirm === 'qr'}
                onOpenChange={(open) => !open && setConfirm(null)}
                title="Ganti QR ID Card?"
                description="QR lama langsung tidak berlaku untuk absensi. Cetak ulang ID Card setelah ini."
                confirmLabel="Ganti QR"
                processing={processing}
                onConfirm={() =>
                    run(() =>
                        router.post(rotateQr.url(employee.id), {}, finish),
                    )
                }
            />
        </AppLayout>
    );
}
