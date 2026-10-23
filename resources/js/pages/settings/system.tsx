import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { index, update } from '@/routes/system-settings';

type Settings = {
    company_name: string;
    late_tolerance_minutes: number;
    max_minutes_per_day: number;
    overtime_hourly_divisor: number;
    overtime_first_hour_multiplier: string;
    overtime_next_hour_multiplier: string;
    interview_indicators: string[];
};

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
        <section className="grid gap-4 rounded-card border border-border bg-surface p-5 md:grid-cols-3">
            <div>
                <h2 className="text-base font-semibold text-navy">{title}</h2>
                <p className="mt-1 text-sm text-text-muted">{description}</p>
            </div>
            <div className="grid gap-4 md:col-span-2">{children}</div>
        </section>
    );
}

export default function SystemSettings({ settings }: { settings: Settings }) {
    const form = useForm({
        ...settings,
        late_tolerance_minutes: String(settings.late_tolerance_minutes),
        max_minutes_per_day: String(settings.max_minutes_per_day),
        overtime_hourly_divisor: String(settings.overtime_hourly_divisor),
    });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(update.url(), { preserveScroll: true });
    };

    const setIndicator = (i: number, value: string) =>
        form.setData(
            'interview_indicators',
            form.data.interview_indicators.map((v, j) => (j === i ? value : v)),
        );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pengaturan', href: '/pengaturan' },
                { title: 'Pengaturan Sistem', href: index.url() },
            ]}
        >
            <Head title="Pengaturan Sistem" />
            <form
                onSubmit={submit}
                className="flex flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <PageHeader
                    title="Pengaturan Sistem"
                    description="Perubahan berlaku segera dan tercatat di audit log."
                    actions={
                        <Button
                            type="submit"
                            className="rounded-control"
                            disabled={form.processing || !form.isDirty}
                        >
                            {form.processing && <Spinner />}
                            Simpan
                        </Button>
                    }
                />

                <Section
                    title="Perusahaan"
                    description="Tampil di slip gaji, kartu ID, dan halaman karier."
                >
                    <FormField
                        id="company_name"
                        label="Nama perusahaan"
                        required
                        error={errors.company_name}
                    >
                        <Input
                            id="company_name"
                            value={form.data.company_name}
                            maxLength={150}
                            onChange={(e) =>
                                form.setData('company_name', e.target.value)
                            }
                        />
                    </FormField>
                </Section>

                <Section
                    title="Absensi"
                    description="Dipakai bila jadwal kerja karyawan tidak menetapkan toleransinya sendiri."
                >
                    <FormField
                        id="late_tolerance_minutes"
                        label="Toleransi keterlambatan (menit)"
                        required
                        hint="0–180 menit."
                        error={errors.late_tolerance_minutes}
                    >
                        <Input
                            id="late_tolerance_minutes"
                            type="number"
                            min={0}
                            max={180}
                            value={form.data.late_tolerance_minutes}
                            onChange={(e) =>
                                form.setData(
                                    'late_tolerance_minutes',
                                    e.target.value,
                                )
                            }
                        />
                    </FormField>
                </Section>

                <Section
                    title="Lembur"
                    description="Upah lembur per jam = gaji pokok ÷ pembagi. Jam pertama dan jam berikutnya dikalikan pengali. Sesuaikan dengan peraturan yang berlaku."
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="max_minutes_per_day"
                            label="Maksimal lembur per hari (menit)"
                            required
                            hint="30–720 menit."
                            error={errors.max_minutes_per_day}
                        >
                            <Input
                                id="max_minutes_per_day"
                                type="number"
                                min={30}
                                max={720}
                                value={form.data.max_minutes_per_day}
                                onChange={(e) =>
                                    form.setData(
                                        'max_minutes_per_day',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            id="overtime_hourly_divisor"
                            label="Pembagi upah per jam"
                            required
                            hint="Umumnya 173."
                            error={errors.overtime_hourly_divisor}
                        >
                            <Input
                                id="overtime_hourly_divisor"
                                type="number"
                                min={1}
                                max={400}
                                value={form.data.overtime_hourly_divisor}
                                onChange={(e) =>
                                    form.setData(
                                        'overtime_hourly_divisor',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            id="overtime_first_hour_multiplier"
                            label="Pengali jam pertama"
                            required
                            error={errors.overtime_first_hour_multiplier}
                        >
                            <Input
                                id="overtime_first_hour_multiplier"
                                type="number"
                                step="0.01"
                                min={1}
                                max={5}
                                value={form.data.overtime_first_hour_multiplier}
                                onChange={(e) =>
                                    form.setData(
                                        'overtime_first_hour_multiplier',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            id="overtime_next_hour_multiplier"
                            label="Pengali jam berikutnya"
                            required
                            error={errors.overtime_next_hour_multiplier}
                        >
                            <Input
                                id="overtime_next_hour_multiplier"
                                type="number"
                                step="0.01"
                                min={1}
                                max={5}
                                value={form.data.overtime_next_hour_multiplier}
                                onChange={(e) =>
                                    form.setData(
                                        'overtime_next_hour_multiplier',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>
                    </div>
                    <p className="text-xs text-text-muted">
                        Perubahan tarif hanya berlaku untuk payroll yang
                        dihitung (ulang) setelah disimpan.
                    </p>
                </Section>

                <Section
                    title="Rekrutmen"
                    description="Indikator yang dinilai pewawancara (skala 1–5). Wawancara yang sudah dinilai tidak berubah."
                >
                    <div className="grid gap-2">
                        {form.data.interview_indicators.map((value, i) => (
                            <div key={i}>
                                <div className="flex gap-2">
                                    <Input
                                        aria-label={`Indikator ${i + 1}`}
                                        value={value}
                                        maxLength={80}
                                        onChange={(e) =>
                                            setIndicator(i, e.target.value)
                                        }
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        aria-label={`Hapus indikator ${i + 1}`}
                                        disabled={
                                            form.data.interview_indicators
                                                .length <= 1
                                        }
                                        onClick={() =>
                                            form.setData(
                                                'interview_indicators',
                                                form.data.interview_indicators.filter(
                                                    (_, j) => j !== i,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                                <InputError
                                    message={
                                        errors[`interview_indicators.${i}`]
                                    }
                                />
                            </div>
                        ))}
                        <InputError message={errors.interview_indicators} />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="w-fit"
                            disabled={
                                form.data.interview_indicators.length >= 10
                            }
                            onClick={() =>
                                form.setData('interview_indicators', [
                                    ...form.data.interview_indicators,
                                    '',
                                ])
                            }
                        >
                            <Plus className="size-4" />
                            Tambah indikator
                        </Button>
                    </div>
                </Section>
            </form>
        </AppLayout>
    );
}
