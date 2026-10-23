import { Head, useForm } from '@inertiajs/react';
import { IdCard, Lock } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo } from 'react';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { FilePreviewButton } from '@/components/file-preview';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { formatTanggal } from '@/lib/format';
import { idCard, update } from '@/routes/my-profile';

type Props = {
    employee: {
        nik: string;
        full_name: string;
        email: string;
        gender_label: string | null;
        birth_place: string | null;
        birth_date: string | null;
        phone: string | null;
        address: string | null;
        department: string | null;
        position: string | null;
        manager: string | null;
        employment_status: string;
        join_date: string | null;
        contract_end_date: string | null;
        photo_url: string | null;
        bank_account: {
            bank_name: string;
            account_number: string;
            account_holder: string;
        } | null;
    };
};

const tanggal = (value: string | null) =>
    value ? formatTanggal(`${value}T00:00:00+07:00`) : null;

function Info({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-xs text-text-muted">{label}</dt>
            <dd className="mt-0.5 text-sm font-medium break-words">
                {children || '—'}
            </dd>
        </div>
    );
}

export default function MyProfile({ employee }: Props) {
    const form = useForm<{
        phone: string;
        address: string;
        photo: File | null;
    }>({
        phone: employee.phone ?? '',
        address: employee.address ?? '',
        photo: null,
    });

    const photoPreview = useMemo(
        () => (form.data.photo ? URL.createObjectURL(form.data.photo) : null),
        [form.data.photo],
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(update.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.setData('photo', null),
        });
    };

    return (
        <AppLayout>
            <Head title="Profil Saya" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Profil Saya"
                    description="Data kepegawaian Anda. Anda dapat mengubah telepon, alamat, dan foto."
                    actions={
                        <FilePreviewButton
                            url={idCard.url()}
                            kind="pdf"
                            title="ID Card Saya"
                            icon={IdCard}
                            variant="outline"
                            className="rounded-control"
                        >
                            Lihat ID Card
                        </FilePreviewButton>
                    }
                />

                <div className="grid gap-6 xl:grid-cols-3">
                    <form
                        onSubmit={submit}
                        className="flex flex-col gap-5 rounded-card border border-border bg-surface p-5 xl:col-span-1"
                    >
                        <h2 className="text-base font-semibold text-navy">
                            Data yang bisa Anda ubah
                        </h2>
                        <div className="flex items-center gap-4">
                            <EmployeeAvatar
                                name={employee.full_name}
                                photoUrl={photoPreview ?? employee.photo_url}
                                className="size-16 text-lg"
                            />
                            <div className="min-w-0 flex-1">
                                <FormField
                                    id="photo"
                                    label="Foto"
                                    hint="JPG/PNG, maks. 2 MB."
                                    error={form.errors.photo}
                                >
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
                                </FormField>
                            </div>
                        </div>
                        <FormField
                            id="phone"
                            label="Nomor telepon"
                            error={form.errors.phone}
                        >
                            <Input
                                id="phone"
                                type="tel"
                                value={form.data.phone}
                                onChange={(e) =>
                                    form.setData('phone', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="address"
                            label="Alamat"
                            error={form.errors.address}
                        >
                            <textarea
                                id="address"
                                rows={4}
                                value={form.data.address}
                                onChange={(e) =>
                                    form.setData('address', e.target.value)
                                }
                                className="w-full rounded-control border border-input bg-surface px-3 py-2 text-sm focus:border-primary-bright focus:ring-2 focus:ring-primary-bright/20 focus:outline-none"
                            />
                        </FormField>
                        <Button
                            type="submit"
                            className="rounded-control"
                            disabled={form.processing || !form.isDirty}
                        >
                            {form.processing && <Spinner />}
                            Simpan perubahan
                        </Button>
                    </form>

                    <section className="rounded-card border border-border bg-surface p-5 xl:col-span-2">
                        <h2 className="mb-1 flex items-center gap-2 text-base font-semibold text-navy">
                            <Lock className="size-4 text-text-muted" />
                            Data kepegawaian
                        </h2>
                        <p className="mb-4 text-sm text-text-muted">
                            Hubungi HRD jika ada data yang perlu diperbaiki.
                        </p>
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <Info label="Nama lengkap">
                                {employee.full_name}
                            </Info>
                            <Info label="NIK">
                                <span className="font-mono">
                                    {employee.nik}
                                </span>
                            </Info>
                            <Info label="Email">{employee.email}</Info>
                            <Info label="Jenis kelamin">
                                {employee.gender_label}
                            </Info>
                            <Info label="Tempat, tanggal lahir">
                                {[
                                    employee.birth_place,
                                    tanggal(employee.birth_date),
                                ]
                                    .filter(Boolean)
                                    .join(', ')}
                            </Info>
                            <Info label="Departemen">
                                {employee.department}
                            </Info>
                            <Info label="Jabatan">{employee.position}</Info>
                            <Info label="Atasan">{employee.manager}</Info>
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
                            <Info label="Rekening">
                                {employee.bank_account
                                    ? `${employee.bank_account.bank_name} ${employee.bank_account.account_number} a.n. ${employee.bank_account.account_holder}`
                                    : null}
                            </Info>
                        </dl>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
