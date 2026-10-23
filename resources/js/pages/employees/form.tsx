import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo } from 'react';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { FormField, selectClassName } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { index, show, store, update } from '@/routes/employees';

type Option = { value: string; label: string };

type EmployeeData = {
    id: number;
    nik: string;
    full_name: string;
    email: string | null;
    gender: string | null;
    birth_place: string | null;
    birth_date: string | null;
    phone: string | null;
    address: string | null;
    department_id: number;
    position_id: number;
    manager_id: number | null;
    employment_status_value: string;
    work_schedule_id: number | null;
    join_date: string | null;
    contract_end_date: string | null;
    photo_url: string | null;
    bank_name: string | null;
    account_holder: string | null;
    account_number_masked: string | null;
};

type Props = {
    employee: EmployeeData | null;
    departments: { id: number; name: string }[];
    positions: { id: number; name: string; department_id: number | null }[];
    managers: { id: number; label: string }[];
    employmentStatuses: Option[];
    workSchedules: { id: number; label: string }[];
    genders: Option[];
};

type FormData = {
    nik: string;
    full_name: string;
    email: string;
    gender: string;
    birth_place: string;
    birth_date: string;
    phone: string;
    address: string;
    department_id: string;
    position_id: string;
    manager_id: string;
    employment_status: string;
    join_date: string;
    contract_end_date: string;
    work_schedule_id: string;
    photo: File | null;
    bank_name: string;
    account_number: string;
    account_holder: string;
};

const textareaClass =
    'w-full rounded-control border border-input bg-surface px-3 py-2 text-sm focus:border-primary-bright focus:ring-2 focus:ring-primary-bright/20 focus:outline-none';

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="rounded-card border border-border bg-surface p-5 md:p-6">
            <h2 className="text-base font-semibold text-navy">{title}</h2>
            <p className="mt-1 text-sm text-text-muted">{description}</p>
            <div className="mt-5 grid gap-4 md:grid-cols-2">{children}</div>
        </section>
    );
}

export default function EmployeeForm({
    employee,
    departments,
    positions,
    managers,
    employmentStatuses,
    workSchedules,
    genders,
}: Props) {
    const isEdit = employee !== null;
    const form = useForm<FormData>({
        nik: employee?.nik ?? '',
        full_name: employee?.full_name ?? '',
        email: employee?.email ?? '',
        gender: employee?.gender ?? '',
        birth_place: employee?.birth_place ?? '',
        birth_date: employee?.birth_date ?? '',
        phone: employee?.phone ?? '',
        address: employee?.address ?? '',
        department_id: employee ? String(employee.department_id) : '',
        position_id: employee ? String(employee.position_id) : '',
        manager_id: employee?.manager_id ? String(employee.manager_id) : '',
        employment_status: employee?.employment_status_value ?? 'probation',
        join_date: employee?.join_date ?? '',
        contract_end_date: employee?.contract_end_date ?? '',
        work_schedule_id: employee?.work_schedule_id
            ? String(employee.work_schedule_id)
            : workSchedules.length === 1
              ? String(workSchedules[0].id)
              : '',
        photo: null,
        bank_name: employee?.bank_name ?? '',
        account_number: '',
        account_holder: employee?.account_holder ?? '',
    });

    // Jabatan yang cocok: milik departemen terpilih atau lintas departemen.
    const positionOptions = useMemo(
        () =>
            positions.filter(
                (p) =>
                    p.department_id === null ||
                    String(p.department_id) === form.data.department_id,
            ),
        [positions, form.data.department_id],
    );

    const photoPreview = useMemo(
        () => (form.data.photo ? URL.createObjectURL(form.data.photo) : null),
        [form.data.photo],
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { forceFormData: true, preserveScroll: true };

        if (employee) {
            form.post(update.url(employee.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    const field = (
        name: keyof FormData,
        label: string,
        input: ReactNode,
        extra: { required?: boolean; hint?: string; wide?: boolean } = {},
    ) => (
        <div className={extra.wide ? 'md:col-span-2' : undefined}>
            <FormField
                id={name}
                label={label}
                required={extra.required}
                hint={extra.hint}
                error={form.errors[name]}
            >
                {input}
            </FormField>
        </div>
    );

    const text = (
        name: Exclude<keyof FormData, 'photo'>,
        props: React.ComponentProps<typeof Input> = {},
    ) => (
        <Input
            id={name}
            value={form.data[name]}
            onChange={(e) => form.setData(name, e.target.value)}
            {...props}
        />
    );

    const title = isEdit ? 'Ubah Data Karyawan' : 'Tambah Karyawan';

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Data Karyawan', href: index.url() },
                ...(employee
                    ? [
                          {
                              title: employee.full_name,
                              href: show.url(employee.id),
                          },
                      ]
                    : []),
                { title: isEdit ? 'Ubah' : 'Tambah', href: '#' },
            ]}
        >
            <Head title={title} />
            <form
                onSubmit={submit}
                className="flex flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <PageHeader
                    title={title}
                    description={
                        isEdit
                            ? 'Perubahan tercatat di riwayat karyawan.'
                            : 'Akun login dibuat otomatis dengan role Karyawan, dan tautan untuk mengatur kata sandi dikirim ke email karyawan.'
                    }
                />

                <Section
                    title="Data Pribadi"
                    description="Identitas dan kontak karyawan."
                >
                    {field(
                        'nik',
                        'NIK',
                        text('nik', {
                            placeholder: 'EMP-0001',
                            maxLength: 30,
                            className: 'uppercase',
                        }),
                        { required: true, hint: 'Nomor induk karyawan, unik.' },
                    )}
                    {field(
                        'full_name',
                        'Nama lengkap',
                        text('full_name', { maxLength: 150 }),
                        { required: true },
                    )}
                    {field(
                        'email',
                        'Email',
                        text('email', {
                            type: 'email',
                            placeholder: 'nama@perusahaan.co.id',
                        }),
                        { required: true, hint: 'Dipakai untuk login.' },
                    )}
                    {field(
                        'phone',
                        'Nomor telepon',
                        text('phone', {
                            type: 'tel',
                            placeholder: '0812-3456-7890',
                        }),
                    )}
                    {field(
                        'gender',
                        'Jenis kelamin',
                        <select
                            id="gender"
                            value={form.data.gender}
                            onChange={(e) =>
                                form.setData('gender', e.target.value)
                            }
                            className={selectClassName}
                        >
                            <option value="">— Pilih —</option>
                            {genders.map((g) => (
                                <option key={g.value} value={g.value}>
                                    {g.label}
                                </option>
                            ))}
                        </select>,
                    )}
                    <div className="grid grid-cols-2 gap-4">
                        {field(
                            'birth_place',
                            'Tempat lahir',
                            text('birth_place'),
                        )}
                        {field(
                            'birth_date',
                            'Tanggal lahir',
                            text('birth_date', { type: 'date' }),
                        )}
                    </div>
                    {field(
                        'address',
                        'Alamat',
                        <textarea
                            id="address"
                            rows={3}
                            value={form.data.address}
                            onChange={(e) =>
                                form.setData('address', e.target.value)
                            }
                            className={textareaClass}
                        />,
                        { wide: true },
                    )}
                    <div className="md:col-span-2">
                        <FormField
                            id="photo"
                            label="Foto"
                            hint="JPG atau PNG, maksimal 2 MB."
                            error={form.errors.photo}
                        >
                            <div className="flex items-center gap-4">
                                <EmployeeAvatar
                                    name={form.data.full_name || '?'}
                                    photoUrl={
                                        photoPreview ??
                                        employee?.photo_url ??
                                        null
                                    }
                                    className="size-14"
                                />
                                <Input
                                    id="photo"
                                    type="file"
                                    accept="image/jpeg,image/png"
                                    onChange={(e) =>
                                        form.setData(
                                            'photo',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                            </div>
                        </FormField>
                    </div>
                </Section>

                <Section
                    title="Kepegawaian"
                    description="Penempatan, atasan, dan status kerja."
                >
                    {field(
                        'department_id',
                        'Departemen',
                        <select
                            id="department_id"
                            value={form.data.department_id}
                            onChange={(e) => {
                                form.setData((data) => ({
                                    ...data,
                                    department_id: e.target.value,
                                    position_id: '',
                                }));
                            }}
                            className={selectClassName}
                        >
                            <option value="">— Pilih departemen —</option>
                            {departments.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.name}
                                </option>
                            ))}
                        </select>,
                        { required: true },
                    )}
                    {field(
                        'position_id',
                        'Jabatan',
                        <select
                            id="position_id"
                            value={form.data.position_id}
                            onChange={(e) =>
                                form.setData('position_id', e.target.value)
                            }
                            className={selectClassName}
                            disabled={form.data.department_id === ''}
                        >
                            <option value="">
                                {form.data.department_id === ''
                                    ? 'Pilih departemen dulu'
                                    : '— Pilih jabatan —'}
                            </option>
                            {positionOptions.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>,
                        { required: true },
                    )}
                    {field(
                        'manager_id',
                        'Atasan langsung',
                        <select
                            id="manager_id"
                            value={form.data.manager_id}
                            onChange={(e) =>
                                form.setData('manager_id', e.target.value)
                            }
                            className={selectClassName}
                        >
                            <option value="">— Tidak ada —</option>
                            {managers.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.label}
                                </option>
                            ))}
                        </select>,
                        {
                            hint: 'Menentukan siapa yang menyetujui cuti/lembur.',
                        },
                    )}
                    {field(
                        'employment_status',
                        'Status kepegawaian',
                        <select
                            id="employment_status"
                            value={form.data.employment_status}
                            onChange={(e) =>
                                form.setData(
                                    'employment_status',
                                    e.target.value,
                                )
                            }
                            className={selectClassName}
                        >
                            {employmentStatuses.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label}
                                </option>
                            ))}
                        </select>,
                        { required: true },
                    )}
                    {field(
                        'join_date',
                        'Tanggal masuk',
                        text('join_date', { type: 'date' }),
                        { required: true },
                    )}
                    {field(
                        'contract_end_date',
                        'Akhir kontrak',
                        text('contract_end_date', { type: 'date' }),
                        {
                            required:
                                form.data.employment_status === 'contract',
                            hint: 'Wajib untuk karyawan kontrak.',
                        },
                    )}
                    {field(
                        'work_schedule_id',
                        'Jadwal kerja',
                        <select
                            id="work_schedule_id"
                            value={form.data.work_schedule_id}
                            onChange={(e) =>
                                form.setData('work_schedule_id', e.target.value)
                            }
                            className={selectClassName}
                        >
                            <option value="">— Belum ada jadwal —</option>
                            {workSchedules.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.label}
                                </option>
                            ))}
                        </select>,
                        {
                            hint: 'Dipakai untuk absensi. Perubahan berlaku mulai hari ini.',
                        },
                    )}
                </Section>

                <Section
                    title="Rekening Bank"
                    description="Untuk pembayaran gaji. Nomor rekening disimpan terenkripsi."
                >
                    {field(
                        'bank_name',
                        'Nama bank',
                        text('bank_name', { placeholder: 'BCA' }),
                    )}
                    {field(
                        'account_holder',
                        'Nama pemilik rekening',
                        text('account_holder'),
                    )}
                    {field(
                        'account_number',
                        'Nomor rekening',
                        text('account_number', {
                            inputMode: 'numeric',
                            autoComplete: 'off',
                            placeholder: employee?.account_number_masked
                                ? `Tersimpan: ${employee.account_number_masked}`
                                : '',
                        }),
                        {
                            hint: employee?.account_number_masked
                                ? 'Kosongkan jika tidak ingin mengubah nomor rekening.'
                                : 'Hanya angka, 6–20 digit.',
                        },
                    )}
                </Section>

                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button
                        asChild
                        variant="outline"
                        className="rounded-control"
                    >
                        <Link
                            href={
                                employee ? show.url(employee.id) : index.url()
                            }
                        >
                            Batal
                        </Link>
                    </Button>
                    <Button
                        type="submit"
                        className="rounded-control"
                        disabled={form.processing}
                    >
                        {form.processing && <Spinner />}
                        {isEdit ? 'Simpan Perubahan' : 'Simpan Karyawan'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
