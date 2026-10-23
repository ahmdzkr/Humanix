import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Briefcase, CalendarClock, MapPin } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import CareerLayout from '@/layouts/career-layout';
import { formatRange } from '@/lib/requests';
import { apply, index } from '@/routes/careers';

type Props = {
    company: string;
    vacancy: {
        slug: string;
        title: string;
        department: string | null;
        position: string | null;
        employment_status: string;
        location: string | null;
        deadline: string;
        description: string;
        requirements: string;
        closed: boolean;
    };
};

const textarea =
    'w-full rounded-control border border-input bg-surface px-3 py-2 text-sm focus:border-primary-bright focus:ring-2 focus:ring-primary-bright/20 focus:outline-none';

export default function CareerShow({ company, vacancy }: Props) {
    const form = useForm<{
        full_name: string;
        email: string;
        phone: string;
        address: string;
        last_education: string;
        cover_letter: string;
        cv: File | null;
        consent: boolean;
        website: string;
    }>({
        full_name: '',
        email: '',
        phone: '',
        address: '',
        last_education: '',
        cover_letter: '',
        cv: null,
        consent: false,
        website: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(apply.url(vacancy.slug), { forceFormData: true });
    };

    const errors = form.errors as Record<string, string | undefined>;

    return (
        <CareerLayout company={company}>
            <Head title={vacancy.title} />
            <Link
                href={index.url()}
                className="mb-4 inline-flex items-center gap-1 text-sm text-primary-bright hover:underline"
            >
                <ArrowLeft className="size-4" /> Semua lowongan
            </Link>

            <div className="grid gap-6 lg:grid-cols-5">
                <article className="rounded-card border border-border bg-surface p-6 lg:col-span-3">
                    <h1 className="text-2xl font-bold text-navy">
                        {vacancy.title}
                    </h1>
                    <ul className="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-text-muted">
                        <li className="flex items-center gap-1">
                            <Briefcase className="size-4" />
                            {vacancy.department} · {vacancy.employment_status}
                        </li>
                        {vacancy.location && (
                            <li className="flex items-center gap-1">
                                <MapPin className="size-4" />
                                {vacancy.location}
                            </li>
                        )}
                        <li className="flex items-center gap-1">
                            <CalendarClock className="size-4" />
                            Tenggat{' '}
                            {formatRange(vacancy.deadline, vacancy.deadline)}
                        </li>
                    </ul>
                    <h2 className="mt-6 font-semibold text-navy">
                        Deskripsi pekerjaan
                    </h2>
                    <p className="mt-2 text-sm whitespace-pre-line">
                        {vacancy.description}
                    </p>
                    <h2 className="mt-6 font-semibold text-navy">
                        Kualifikasi
                    </h2>
                    <p className="mt-2 text-sm whitespace-pre-line">
                        {vacancy.requirements}
                    </p>
                </article>

                {vacancy.closed ? (
                    <div
                        className="flex flex-col items-start gap-2 rounded-card border border-border bg-surface p-6 lg:col-span-2"
                        data-testid="vacancy-closed"
                    >
                        <h2 className="text-lg font-semibold text-navy">
                            Lowongan sudah ditutup
                        </h2>
                        <p className="text-sm text-text-muted">
                            Terima kasih atas minat Anda. Lowongan ini sudah
                            tidak menerima lamaran baru. Silakan lihat lowongan
                            lain yang masih dibuka.
                        </p>
                        <Button asChild variant="outline" className="mt-2">
                            <Link href={index.url()}>Lihat lowongan lain</Link>
                        </Button>
                    </div>
                ) : (
                    <form
                        onSubmit={submit}
                        className="flex flex-col gap-4 rounded-card border border-border bg-surface p-6 lg:col-span-2"
                    >
                        <h2 className="text-lg font-semibold text-navy">
                            Kirim lamaran
                        </h2>
                        {errors.vacancy && (
                            <InputError message={errors.vacancy} />
                        )}
                        <FormField
                            id="full_name"
                            label="Nama lengkap"
                            required
                            error={errors.full_name}
                        >
                            <Input
                                id="full_name"
                                autoComplete="name"
                                value={form.data.full_name}
                                onChange={(e) =>
                                    form.setData('full_name', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="email"
                            label="Email"
                            required
                            error={errors.email}
                        >
                            <Input
                                id="email"
                                type="email"
                                autoComplete="email"
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="phone"
                            label="Nomor telepon"
                            required
                            error={errors.phone}
                        >
                            <Input
                                id="phone"
                                type="tel"
                                autoComplete="tel"
                                value={form.data.phone}
                                onChange={(e) =>
                                    form.setData('phone', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="last_education"
                            label="Pendidikan terakhir"
                            error={errors.last_education}
                        >
                            <Input
                                id="last_education"
                                placeholder="S1 Akuntansi"
                                value={form.data.last_education}
                                onChange={(e) =>
                                    form.setData(
                                        'last_education',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            id="address"
                            label="Alamat"
                            error={errors.address}
                        >
                            <textarea
                                id="address"
                                rows={2}
                                className={textarea}
                                value={form.data.address}
                                onChange={(e) =>
                                    form.setData('address', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="cover_letter"
                            label="Surat lamaran singkat"
                            error={errors.cover_letter}
                        >
                            <textarea
                                id="cover_letter"
                                rows={4}
                                maxLength={3000}
                                className={textarea}
                                value={form.data.cover_letter}
                                onChange={(e) =>
                                    form.setData('cover_letter', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="cv"
                            label="CV (PDF)"
                            required
                            hint="Maksimal 5 MB."
                            error={errors.cv}
                        >
                            <Input
                                id="cv"
                                type="file"
                                accept="application/pdf"
                                onChange={(e) =>
                                    form.setData(
                                        'cv',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </FormField>
                        {/* Honeypot anti-spam: disembunyikan dari pengguna. */}
                        <div className="hidden" aria-hidden>
                            <label htmlFor="website">Website</label>
                            <input
                                id="website"
                                tabIndex={-1}
                                autoComplete="off"
                                value={form.data.website}
                                onChange={(e) =>
                                    form.setData('website', e.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-1">
                            <label className="flex items-start gap-3 text-sm">
                                <Checkbox
                                    checked={form.data.consent}
                                    onCheckedChange={(c) =>
                                        form.setData('consent', c === true)
                                    }
                                    className="mt-0.5"
                                />
                                <span>
                                    Saya menyetujui {company} memproses data
                                    pribadi dan CV saya untuk keperluan
                                    rekrutmen.
                                </span>
                            </label>
                            <InputError message={errors.consent} />
                        </div>
                        <Button
                            type="submit"
                            className="h-11 rounded-control"
                            disabled={form.processing}
                        >
                            {form.processing && <Spinner />}
                            Kirim lamaran
                        </Button>
                    </form>
                )}
            </div>
        </CareerLayout>
    );
}
