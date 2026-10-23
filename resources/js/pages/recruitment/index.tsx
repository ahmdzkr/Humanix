import { Head, Link, router, useForm } from '@inertiajs/react';
import { ExternalLink, Pencil, Plus, Users, Video } from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import { FormField, selectClassName } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import type { StatusTone } from '@/components/status-badge';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
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
import { formatJam, formatTanggal } from '@/lib/format';
import { formatRange } from '@/lib/requests';
import { show as careerShow } from '@/routes/careers';
import applications from '@/routes/recruitment/applications';
import vacancies from '@/routes/recruitment/vacancies';

type Vacancy = {
    id: number;
    slug: string;
    title: string;
    department_id: number;
    position_id: number;
    employment_status: string;
    location: string | null;
    description: string;
    requirements: string;
    quota: number;
    deadline: string;
    department: string | null;
    position: string | null;
    status: 'draft' | 'published' | 'closed';
    status_label: string;
    closed_reason: 'quota' | 'manual' | null;
    is_open: boolean;
    applications_count: number;
    new_count: number;
    hired_count: number;
};

type MyInterview = {
    id: number;
    application_id: number;
    applicant: string;
    vacancy: string;
    scheduled_at: string;
    mode: 'online' | 'offline';
    location: string;
    status: 'scheduled' | 'completed';
};

type Props = {
    vacancies: Vacancy[];
    myInterviews: MyInterview[];
    canManage: boolean;
    options: {
        departments: { id: number; name: string }[];
        positions: { id: number; name: string; department_id: number | null }[];
        employmentStatuses: { value: string; label: string }[];
    } | null;
};

const vacancyTone: Record<Vacancy['status'], StatusTone> = {
    draft: 'neutral',
    published: 'success',
    closed: 'danger',
};

const emptyForm = {
    title: '',
    department_id: '',
    position_id: '',
    employment_status: 'contract',
    location: '',
    description: '',
    requirements: '',
    quota: '1',
    deadline: '',
};

const textarea =
    'w-full rounded-control border border-input bg-surface px-3 py-2 text-sm focus:border-primary-bright focus:ring-2 focus:ring-primary-bright/20 focus:outline-none';

export default function RecruitmentIndex({
    vacancies: rows,
    myInterviews,
    canManage,
    options,
}: Props) {
    const [editing, setEditing] = useState<Vacancy | 'new' | null>(null);
    const form = useForm(emptyForm);

    const positions = useMemo(
        () =>
            (options?.positions ?? []).filter(
                (p) =>
                    p.department_id === null ||
                    String(p.department_id) === form.data.department_id,
            ),
        [options, form.data.department_id],
    );

    const open = (v: Vacancy | 'new') => {
        form.clearErrors();
        form.setData(
            v === 'new'
                ? emptyForm
                : {
                      title: v.title,
                      department_id: String(v.department_id),
                      position_id: String(v.position_id),
                      employment_status: v.employment_status,
                      location: v.location ?? '',
                      description: v.description,
                      requirements: v.requirements,
                      quota: String(v.quota),
                      deadline: v.deadline,
                  },
        );
        setEditing(v);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const opts = {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        };

        if (editing && editing !== 'new') {
            form.put(vacancies.update.url(editing.id), opts);
        } else {
            form.post(vacancies.store.url(), opts);
        }
    };

    const setStatus = (v: Vacancy, status: 'published' | 'closed') =>
        router.post(
            vacancies.status.url(v.id),
            { status },
            { preserveScroll: true },
        );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Rekruitmen', href: '/rekruitmen' },
            ]}
        >
            <Head title="Rekruitmen" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Rekruitmen"
                    description={
                        canManage
                            ? 'Kelola lowongan, seleksi pelamar, wawancara, hingga onboarding karyawan baru.'
                            : 'Wawancara kandidat yang ditugaskan kepada Anda.'
                    }
                    actions={
                        canManage && (
                            <>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="rounded-control"
                                >
                                    <a
                                        href="/karier"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        <ExternalLink className="size-4" />
                                        Halaman karier
                                    </a>
                                </Button>
                                <Button
                                    className="rounded-control"
                                    onClick={() => open('new')}
                                >
                                    <Plus className="size-4" />
                                    Lowongan Baru
                                </Button>
                            </>
                        )
                    }
                />

                {myInterviews.length > 0 && (
                    <section className="rounded-card border border-border bg-surface">
                        <h2 className="border-b border-border p-4 font-semibold text-navy">
                            Wawancara Saya
                        </h2>
                        <ul className="divide-y divide-border">
                            {myInterviews.map((i) => (
                                <li
                                    key={i.id}
                                    className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p className="font-semibold text-navy">
                                            {i.applicant}
                                        </p>
                                        <p className="text-sm text-text-muted">
                                            {i.vacancy} ·{' '}
                                            {formatTanggal(i.scheduled_at)},{' '}
                                            {formatJam(i.scheduled_at)} ·{' '}
                                            {i.mode === 'online'
                                                ? 'Online'
                                                : 'Tatap muka'}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <StatusBadge
                                            tone={
                                                i.status === 'completed'
                                                    ? 'success'
                                                    : 'warning'
                                            }
                                        >
                                            {i.status === 'completed'
                                                ? 'Sudah dinilai'
                                                : 'Perlu dinilai'}
                                        </StatusBadge>
                                        <Button
                                            asChild
                                            size="sm"
                                            className="rounded-control"
                                        >
                                            <Link
                                                href={applications.show.url(
                                                    i.application_id,
                                                )}
                                            >
                                                <Video className="size-4" />
                                                Buka
                                            </Link>
                                        </Button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {canManage &&
                    (rows.length === 0 ? (
                        <p className="rounded-card border border-border bg-surface p-10 text-center text-sm text-text-muted">
                            Belum ada lowongan. Buat lowongan baru, lalu
                            publikasikan agar tampil di halaman karier.
                        </p>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {rows.map((v) => (
                                <section
                                    key={v.id}
                                    className="flex flex-col gap-3 rounded-card border border-border bg-surface p-5"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <Link
                                                href={vacancies.show.url(v.id)}
                                                className="font-semibold text-navy hover:text-primary"
                                            >
                                                {v.title}
                                            </Link>
                                            <p className="text-xs text-text-muted">
                                                {v.department} · {v.position}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            tone={vacancyTone[v.status]}
                                        >
                                            {v.closed_reason === 'quota'
                                                ? 'Ditutup otomatis: kuota terpenuhi'
                                                : v.status_label}
                                        </StatusBadge>
                                    </div>
                                    <p className="text-xs text-text-muted">
                                        Tenggat{' '}
                                        {formatRange(v.deadline, v.deadline)} ·
                                        kuota {v.hired_count}/{v.quota} diterima
                                        {v.status === 'published' &&
                                            !v.is_open &&
                                            ' · tenggat sudah lewat'}
                                    </p>
                                    <div className="grid grid-cols-3 gap-2 text-center">
                                        <div className="rounded-control bg-background p-2">
                                            <p className="text-lg font-bold text-navy">
                                                {v.applications_count}
                                            </p>
                                            <p className="text-[11px] text-text-muted">
                                                Pelamar
                                            </p>
                                        </div>
                                        <div className="rounded-control bg-background p-2">
                                            <p className="text-lg font-bold text-warning">
                                                {v.new_count}
                                            </p>
                                            <p className="text-[11px] text-text-muted">
                                                Baru
                                            </p>
                                        </div>
                                        <div className="rounded-control bg-background p-2">
                                            <p className="text-lg font-bold text-success">
                                                {v.hired_count}
                                            </p>
                                            <p className="text-[11px] text-text-muted">
                                                Diterima
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-auto flex flex-wrap gap-2">
                                        <Button
                                            asChild
                                            size="sm"
                                            className="rounded-control"
                                        >
                                            <Link
                                                href={vacancies.show.url(v.id)}
                                            >
                                                <Users className="size-4" />
                                                Pelamar
                                            </Link>
                                        </Button>
                                        {v.status !== 'closed' && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="rounded-control"
                                                onClick={() => open(v)}
                                            >
                                                <Pencil className="size-4" />
                                                Ubah
                                            </Button>
                                        )}
                                        {v.status === 'draft' && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="rounded-control"
                                                onClick={() =>
                                                    setStatus(v, 'published')
                                                }
                                            >
                                                Publikasikan
                                            </Button>
                                        )}
                                        {v.status === 'published' && (
                                            <>
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                    className="rounded-control"
                                                >
                                                    <a
                                                        href={careerShow.url(
                                                            v.slug,
                                                        )}
                                                        target="_blank"
                                                        rel="noopener"
                                                    >
                                                        Lihat
                                                    </a>
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="rounded-control text-danger hover:text-danger"
                                                    onClick={() =>
                                                        setStatus(v, 'closed')
                                                    }
                                                >
                                                    Tutup
                                                </Button>
                                            </>
                                        )}
                                    </div>
                                </section>
                            ))}
                        </div>
                    ))}

                {!canManage && myInterviews.length === 0 && (
                    <p className="rounded-card border border-border bg-surface p-10 text-center text-sm text-text-muted">
                        Belum ada wawancara yang ditugaskan kepada Anda.
                    </p>
                )}
            </div>

            {options && (
                <Dialog
                    open={editing !== null}
                    onOpenChange={(o) => !o && setEditing(null)}
                >
                    <DialogContent className="max-h-[90vh] overflow-y-auto rounded-card sm:max-w-2xl">
                        <form onSubmit={submit} className="grid gap-4">
                            <DialogHeader>
                                <DialogTitle className="text-navy">
                                    {editing === 'new'
                                        ? 'Lowongan Baru'
                                        : 'Ubah Lowongan'}
                                </DialogTitle>
                                <DialogDescription>
                                    Lowongan baru disimpan sebagai draft.
                                </DialogDescription>
                            </DialogHeader>
                            <FormField
                                id="title"
                                label="Judul lowongan"
                                required
                                error={form.errors.title}
                            >
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(e) =>
                                        form.setData('title', e.target.value)
                                    }
                                />
                            </FormField>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <FormField
                                    id="department_id"
                                    label="Departemen"
                                    required
                                    error={form.errors.department_id}
                                >
                                    <select
                                        id="department_id"
                                        value={form.data.department_id}
                                        onChange={(e) =>
                                            form.setData((d) => ({
                                                ...d,
                                                department_id: e.target.value,
                                                position_id: '',
                                            }))
                                        }
                                        className={selectClassName}
                                    >
                                        <option value="">— Pilih —</option>
                                        {options.departments.map((d) => (
                                            <option key={d.id} value={d.id}>
                                                {d.name}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>
                                <FormField
                                    id="position_id"
                                    label="Jabatan"
                                    required
                                    error={form.errors.position_id}
                                >
                                    <select
                                        id="position_id"
                                        value={form.data.position_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'position_id',
                                                e.target.value,
                                            )
                                        }
                                        className={selectClassName}
                                        disabled={!form.data.department_id}
                                    >
                                        <option value="">— Pilih —</option>
                                        {positions.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>
                                <FormField
                                    id="employment_status"
                                    label="Status"
                                    required
                                    error={form.errors.employment_status}
                                >
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
                                        {options.employmentStatuses.map((s) => (
                                            <option
                                                key={s.value}
                                                value={s.value}
                                            >
                                                {s.label}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <FormField
                                    id="location"
                                    label="Lokasi"
                                    error={form.errors.location}
                                >
                                    <Input
                                        id="location"
                                        placeholder="Jakarta"
                                        value={form.data.location}
                                        onChange={(e) =>
                                            form.setData(
                                                'location',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="quota"
                                    label="Kuota"
                                    required
                                    error={form.errors.quota}
                                >
                                    <Input
                                        id="quota"
                                        type="number"
                                        min={1}
                                        value={form.data.quota}
                                        onChange={(e) =>
                                            form.setData(
                                                'quota',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="deadline"
                                    label="Tenggat"
                                    required
                                    error={form.errors.deadline}
                                >
                                    <Input
                                        id="deadline"
                                        type="date"
                                        value={form.data.deadline}
                                        onChange={(e) =>
                                            form.setData(
                                                'deadline',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </div>
                            <FormField
                                id="description"
                                label="Deskripsi pekerjaan"
                                required
                                error={form.errors.description}
                            >
                                <textarea
                                    id="description"
                                    rows={5}
                                    className={textarea}
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="requirements"
                                label="Kualifikasi"
                                required
                                error={form.errors.requirements}
                            >
                                <textarea
                                    id="requirements"
                                    rows={5}
                                    className={textarea}
                                    value={form.data.requirements}
                                    onChange={(e) =>
                                        form.setData(
                                            'requirements',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
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
            )}
        </AppLayout>
    );
}
