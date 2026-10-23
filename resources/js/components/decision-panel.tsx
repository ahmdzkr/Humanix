import { router, useForm } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    decideUrl: string;
    cancelUrl: string;
    version: number;
    canDecide: boolean;
    canCancel: boolean;
};

/**
 * Tombol Setujui/Tolak (untuk penyetuju) dan Batalkan (untuk pemohon).
 * Versi dikirim agar keputusan ganda/terlambat ditolak server.
 */
export function DecisionPanel({
    decideUrl,
    cancelUrl,
    version,
    canDecide,
    canCancel,
}: Props) {
    const form = useForm({ decision: '', note: '', version });
    const [confirmCancel, setConfirmCancel] = useState(false);
    const [cancelling, setCancelling] = useState(false);

    if (!canDecide && !canCancel) {
        return null;
    }

    const submit = (decision: 'approve' | 'reject') => {
        form.transform((data) => ({ ...data, decision, version }));
        form.post(decideUrl, { preserveScroll: true });
    };

    return (
        <section className="rounded-card border border-border bg-surface p-5">
            {canDecide && (
                <div className="grid gap-3">
                    <h2 className="font-semibold text-navy">Keputusan Anda</h2>
                    <div className="grid gap-2">
                        <Label htmlFor="note">
                            Catatan{' '}
                            <span className="font-normal text-text-muted">
                                (wajib jika menolak)
                            </span>
                        </Label>
                        <textarea
                            id="note"
                            rows={3}
                            value={form.data.note}
                            onChange={(e) =>
                                form.setData('note', e.target.value)
                            }
                            maxLength={255}
                            className="w-full rounded-control border border-input bg-surface px-3 py-2 text-sm focus:border-primary-bright focus:ring-2 focus:ring-primary-bright/20 focus:outline-none"
                        />
                        <InputError message={form.errors.note} />
                        <InputError message={form.errors.version} />
                    </div>
                    <div className="flex gap-2">
                        <Button
                            className="flex-1 rounded-control bg-success hover:bg-success/90"
                            disabled={form.processing}
                            onClick={() => submit('approve')}
                        >
                            {form.processing ? (
                                <Spinner />
                            ) : (
                                <Check className="size-4" />
                            )}
                            Setujui
                        </Button>
                        <Button
                            variant="outline"
                            className="flex-1 rounded-control text-danger hover:text-danger"
                            disabled={form.processing}
                            onClick={() => submit('reject')}
                        >
                            <X className="size-4" />
                            Tolak
                        </Button>
                    </div>
                </div>
            )}
            {canCancel && (
                <div className="grid gap-2">
                    <p className="text-sm text-text-muted">
                        Pengajuan masih menunggu persetujuan dan bisa
                        dibatalkan.
                    </p>
                    <Button
                        variant="outline"
                        className="rounded-control text-danger hover:text-danger"
                        onClick={() => setConfirmCancel(true)}
                    >
                        Batalkan pengajuan
                    </Button>
                </div>
            )}
            <ConfirmDialog
                open={confirmCancel}
                onOpenChange={setConfirmCancel}
                title="Batalkan pengajuan?"
                description="Pengajuan yang dibatalkan tetap tersimpan di riwayat."
                confirmLabel="Batalkan"
                destructive
                processing={cancelling}
                onConfirm={() => {
                    setCancelling(true);
                    router.post(
                        cancelUrl,
                        {},
                        {
                            preserveScroll: true,
                            onFinish: () => {
                                setCancelling(false);
                                setConfirmCancel(false);
                            },
                        },
                    );
                }}
            />
        </section>
    );
}
