import { Head, router, useForm } from '@inertiajs/react';
import { CalendarOff, Pencil, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { formatTanggal } from '@/lib/format';
import { destroy, index, store, update } from '@/routes/holidays';

type Holiday = { id: number; date: string; name: string };

export default function Holidays({
    year,
    holidays,
}: {
    year: number;
    holidays: Holiday[];
}) {
    const [editing, setEditing] = useState<Holiday | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Holiday | null>(null);
    const form = useForm({ date: '', name: '' });

    const open = (holiday: Holiday | 'new') => {
        form.clearErrors();
        form.setData(
            holiday === 'new'
                ? { date: `${year}-01-01`, name: '' }
                : { date: holiday.date, name: holiday.name },
        );
        setEditing(holiday);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        };

        if (editing && editing !== 'new') {
            form.put(update.url(editing.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pengaturan', href: '/pengaturan' },
                { title: 'Hari Libur', href: index.url() },
            ]}
        >
            <Head title="Hari Libur" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Hari Libur"
                    description="Pada hari libur, kios menolak absen dan tidak ada karyawan yang ditandai Tidak Hadir."
                    actions={
                        <>
                            <Input
                                type="number"
                                aria-label="Tahun"
                                value={year}
                                min={2000}
                                max={2100}
                                onChange={(e) =>
                                    e.target.value.length === 4 &&
                                    router.get(
                                        index.url(),
                                        { year: e.target.value },
                                        { preserveState: true, replace: true },
                                    )
                                }
                                className="w-24 rounded-control"
                            />
                            <Button
                                className="rounded-control"
                                onClick={() => open('new')}
                            >
                                <Plus className="size-4" />
                                Tambah
                            </Button>
                        </>
                    }
                />

                <section className="rounded-card border border-border bg-surface">
                    {holidays.length === 0 ? (
                        <div className="flex flex-col items-center p-10 text-center text-sm text-text-muted">
                            <CalendarOff className="mb-2 size-8 text-primary" />
                            Belum ada hari libur di tahun {year}.
                        </div>
                    ) : (
                        <ul className="divide-y divide-border">
                            {holidays.map((h) => (
                                <li
                                    key={h.id}
                                    className="flex items-center justify-between gap-3 p-4"
                                >
                                    <div>
                                        <p className="font-semibold text-navy">
                                            {h.name}
                                        </p>
                                        <p className="text-sm text-text-muted">
                                            {formatTanggal(
                                                `${h.date}T00:00:00+07:00`,
                                            )}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="rounded-control"
                                            aria-label={`Ubah ${h.name}`}
                                            onClick={() => open(h)}
                                        >
                                            <Pencil className="size-4" />
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="rounded-control text-danger hover:text-danger"
                                            aria-label={`Hapus ${h.name}`}
                                            onClick={() => setDeleting(h)}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(o) => !o && setEditing(null)}
            >
                <DialogContent className="rounded-card sm:max-w-md">
                    <form onSubmit={submit} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle className="text-navy">
                                {editing === 'new'
                                    ? 'Tambah Hari Libur'
                                    : 'Ubah Hari Libur'}
                            </DialogTitle>
                        </DialogHeader>
                        <FormField
                            id="date"
                            label="Tanggal"
                            required
                            error={form.errors.date}
                        >
                            <Input
                                id="date"
                                type="date"
                                value={form.data.date}
                                onChange={(e) =>
                                    form.setData('date', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="name"
                            label="Nama"
                            required
                            error={form.errors.name}
                        >
                            <Input
                                id="name"
                                value={form.data.name}
                                placeholder="Hari Kemerdekaan RI"
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
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

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(o) => !o && setDeleting(null)}
                title="Hapus hari libur?"
                description={`${deleting?.name ?? ''} akan dihapus dari kalender.`}
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
