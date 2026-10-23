import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Users } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
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
import { assign, destroy, store, update } from '@/routes/work-schedules';

type Schedule = {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    work_days: number[];
    late_tolerance_minutes: number | null;
    check_in_opens_minutes: number;
    check_out_closes_minutes: number;
    is_active: boolean;
    employees_count: number;
};

type Props = {
    schedules: Schedule[];
    dayNames: Record<string, string>;
    departments: { id: number; name: string }[];
};

type FormData = {
    name: string;
    start_time: string;
    end_time: string;
    work_days: number[];
    late_tolerance_minutes: string;
    check_in_opens_minutes: string;
    check_out_closes_minutes: string;
    is_active: boolean;
};

const emptyForm: FormData = {
    name: '',
    start_time: '08:00',
    end_time: '17:00',
    work_days: [1, 2, 3, 4, 5],
    late_tolerance_minutes: '',
    check_in_opens_minutes: '120',
    check_out_closes_minutes: '360',
    is_active: true,
};

export default function Schedules({ schedules, dayNames, departments }: Props) {
    const [editing, setEditing] = useState<Schedule | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Schedule | null>(null);
    const [assigning, setAssigning] = useState<Schedule | null>(null);
    const form = useForm<FormData>(emptyForm);
    const assignForm = useForm({ department_id: '' });

    const open = (schedule: Schedule | null) => {
        setEditing(schedule);
        form.clearErrors();
        form.setData(
            schedule
                ? {
                      name: schedule.name,
                      start_time: schedule.start_time,
                      end_time: schedule.end_time,
                      work_days: schedule.work_days,
                      late_tolerance_minutes:
                          schedule.late_tolerance_minutes?.toString() ?? '',
                      check_in_opens_minutes: String(
                          schedule.check_in_opens_minutes,
                      ),
                      check_out_closes_minutes: String(
                          schedule.check_out_closes_minutes,
                      ),
                      is_active: schedule.is_active,
                  }
                : emptyForm,
        );
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

    const toggleDay = (day: number) =>
        form.setData(
            'work_days',
            form.data.work_days.includes(day)
                ? form.data.work_days.filter((d) => d !== day)
                : [...form.data.work_days, day].sort(),
        );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pengaturan', href: '/pengaturan' },
                { title: 'Jadwal Kerja', href: '/pengaturan/jadwal-kerja' },
            ]}
        >
            <Head title="Jadwal Kerja" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Jadwal Kerja"
                    description="Jam kerja dipakai untuk menentukan absen masuk, pulang, dan keterlambatan (waktu WIB)."
                    actions={
                        <Button
                            className="rounded-control"
                            onClick={() => open(null)}
                        >
                            <Plus className="size-4" />
                            Tambah Jadwal
                        </Button>
                    }
                />

                {schedules.length === 0 ? (
                    <p className="rounded-card border border-border bg-surface p-10 text-center text-sm text-text-muted">
                        Belum ada jadwal kerja. Tambahkan jadwal agar karyawan
                        bisa absen.
                    </p>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {schedules.map((s) => (
                            <section
                                key={s.id}
                                className="flex flex-col gap-3 rounded-card border border-border bg-surface p-5"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <h2 className="font-semibold text-navy">
                                            {s.name}
                                        </h2>
                                        <p className="font-mono text-2xl font-bold text-primary">
                                            {s.start_time}–{s.end_time}
                                        </p>
                                    </div>
                                    {s.is_active ? (
                                        <StatusBadge tone="success">
                                            Aktif
                                        </StatusBadge>
                                    ) : (
                                        <StatusBadge>Nonaktif</StatusBadge>
                                    )}
                                </div>
                                <div className="flex flex-wrap gap-1">
                                    {Object.entries(dayNames).map(
                                        ([d, name]) => (
                                            <span
                                                key={d}
                                                className={
                                                    s.work_days.includes(
                                                        Number(d),
                                                    )
                                                        ? 'rounded-md bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary'
                                                        : 'rounded-md bg-muted px-2 py-0.5 text-xs text-text-muted line-through'
                                                }
                                            >
                                                {name.slice(0, 3)}
                                            </span>
                                        ),
                                    )}
                                </div>
                                <p className="text-xs text-text-muted">
                                    Toleransi terlambat:{' '}
                                    {s.late_tolerance_minutes ??
                                        'default sistem'}
                                    {s.late_tolerance_minutes !== null &&
                                        ' menit'}{' '}
                                    · Absen masuk dibuka{' '}
                                    {s.check_in_opens_minutes} menit sebelum jam
                                    masuk
                                </p>
                                <p className="flex items-center gap-1 text-sm text-navy">
                                    <Users className="size-4" />
                                    {s.employees_count} karyawan
                                </p>
                                <div className="mt-auto flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="rounded-control"
                                        onClick={() => open(s)}
                                    >
                                        <Pencil className="size-4" />
                                        Ubah
                                    </Button>
                                    {s.is_active && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="rounded-control"
                                            onClick={() => {
                                                assignForm.reset();
                                                setAssigning(s);
                                            }}
                                        >
                                            Terapkan ke departemen
                                        </Button>
                                    )}
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="ml-auto rounded-control text-danger hover:text-danger"
                                        aria-label={`Hapus ${s.name}`}
                                        onClick={() => setDeleting(s)}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </section>
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="rounded-card sm:max-w-lg">
                    <form onSubmit={submit} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle className="text-navy">
                                {editing ? 'Ubah Jadwal' : 'Tambah Jadwal'}
                            </DialogTitle>
                            <DialogDescription>
                                Semua jam dalam WIB.
                            </DialogDescription>
                        </DialogHeader>
                        <FormField
                            id="name"
                            label="Nama jadwal"
                            required
                            error={form.errors.name}
                        >
                            <Input
                                id="name"
                                value={form.data.name}
                                placeholder="Reguler"
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                        </FormField>
                        <div className="grid grid-cols-2 gap-4">
                            <FormField
                                id="start_time"
                                label="Jam masuk"
                                required
                                error={form.errors.start_time}
                            >
                                <Input
                                    id="start_time"
                                    type="time"
                                    value={form.data.start_time}
                                    onChange={(e) =>
                                        form.setData(
                                            'start_time',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="end_time"
                                label="Jam pulang"
                                required
                                error={form.errors.end_time}
                            >
                                <Input
                                    id="end_time"
                                    type="time"
                                    value={form.data.end_time}
                                    onChange={(e) =>
                                        form.setData('end_time', e.target.value)
                                    }
                                />
                            </FormField>
                        </div>
                        <fieldset className="grid gap-2">
                            <legend className="mb-2 text-sm font-medium">
                                Hari kerja{' '}
                                <span className="text-danger">*</span>
                            </legend>
                            <div className="flex flex-wrap gap-3">
                                {Object.entries(dayNames).map(([d, name]) => (
                                    <label
                                        key={d}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={form.data.work_days.includes(
                                                Number(d),
                                            )}
                                            onCheckedChange={() =>
                                                toggleDay(Number(d))
                                            }
                                        />
                                        {name}
                                    </label>
                                ))}
                            </div>
                            {form.errors.work_days && (
                                <p className="text-sm text-danger">
                                    {form.errors.work_days}
                                </p>
                            )}
                        </fieldset>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <FormField
                                id="late_tolerance_minutes"
                                label="Toleransi (menit)"
                                hint="Kosong = default sistem"
                                error={form.errors.late_tolerance_minutes}
                            >
                                <Input
                                    id="late_tolerance_minutes"
                                    type="number"
                                    min={0}
                                    value={form.data.late_tolerance_minutes}
                                    onChange={(e) =>
                                        form.setData(
                                            'late_tolerance_minutes',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="check_in_opens_minutes"
                                label="Absen dibuka (mnt)"
                                hint="Sebelum jam masuk"
                                error={form.errors.check_in_opens_minutes}
                            >
                                <Input
                                    id="check_in_opens_minutes"
                                    type="number"
                                    min={0}
                                    value={form.data.check_in_opens_minutes}
                                    onChange={(e) =>
                                        form.setData(
                                            'check_in_opens_minutes',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="check_out_closes_minutes"
                                label="Batas pulang (mnt)"
                                hint="Setelah jam pulang"
                                error={form.errors.check_out_closes_minutes}
                            >
                                <Input
                                    id="check_out_closes_minutes"
                                    type="number"
                                    min={0}
                                    value={form.data.check_out_closes_minutes}
                                    onChange={(e) =>
                                        form.setData(
                                            'check_out_closes_minutes',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </div>
                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="is_active"
                                checked={form.data.is_active}
                                onCheckedChange={(c) =>
                                    form.setData('is_active', c === true)
                                }
                            />
                            <Label htmlFor="is_active" className="font-normal">
                                Jadwal aktif
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

            <Dialog
                open={assigning !== null}
                onOpenChange={(o) => !o && setAssigning(null)}
            >
                <DialogContent className="rounded-card sm:max-w-md">
                    <form
                        className="grid gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();

                            if (assigning) {
                                assignForm.post(assign.url(assigning.id), {
                                    preserveScroll: true,
                                    onSuccess: () => setAssigning(null),
                                });
                            }
                        }}
                    >
                        <DialogHeader>
                            <DialogTitle className="text-navy">
                                Terapkan {assigning?.name}
                            </DialogTitle>
                            <DialogDescription>
                                Semua karyawan aktif di departemen terpilih
                                memakai jadwal ini mulai hari ini.
                            </DialogDescription>
                        </DialogHeader>
                        <FormField
                            id="department_id"
                            label="Departemen"
                            required
                            error={assignForm.errors.department_id}
                        >
                            <select
                                id="department_id"
                                value={assignForm.data.department_id}
                                onChange={(e) =>
                                    assignForm.setData(
                                        'department_id',
                                        e.target.value,
                                    )
                                }
                                className={selectClassName}
                            >
                                <option value="">— Pilih —</option>
                                {departments.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <DialogFooter>
                            <Button
                                type="submit"
                                className="rounded-control"
                                disabled={assignForm.processing}
                            >
                                Terapkan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(o) => !o && setDeleting(null)}
                title="Hapus jadwal?"
                description={`Jadwal ${deleting?.name ?? ''} akan dihapus. Jadwal yang pernah dipakai tidak bisa dihapus; nonaktifkan saja.`}
                confirmLabel="Hapus"
                destructive
                onConfirm={() =>
                    deleting &&
                    router.delete(destroy.url(deleting.id), {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    })
                }
            />
        </AppLayout>
    );
}
