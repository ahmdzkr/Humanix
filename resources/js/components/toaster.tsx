import { usePage } from '@inertiajs/react';
import { CheckCircle2, X, XCircle } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

type Toast = { id: number; type: 'success' | 'error'; message: string };

/**
 * Menampilkan pesan flash (session success/error) sebagai toast di pojok kanan atas.
 */
export function Toaster() {
    const page = usePage();
    const { flash } = page.props;
    const [toasts, setToasts] = useState<Toast[]>([]);
    // StrictMode menjalankan efek dua kali; kunjungan yang sama hanya ditampilkan sekali.
    const shownFor = useRef<unknown>(null);

    useEffect(() => {
        if (shownFor.current === page) {
            return;
        }

        shownFor.current = page;

        const push = (type: Toast['type'], message?: string | null) => {
            if (!message) {
                return;
            }

            const id = Date.now() + Math.random();
            setToasts((current) => [...current, { id, type, message }]);
            setTimeout(
                () =>
                    setToasts((current) => current.filter((t) => t.id !== id)),
                4500,
            );
        };

        push('success', flash?.success);
        push('error', flash?.error);
        // Dipicu tiap kunjungan halaman baru, supaya pesan yang sama tetap muncul lagi.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page]);

    return (
        <div
            aria-live="polite"
            className="pointer-events-none fixed top-4 right-4 z-50 flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2"
        >
            {toasts.map((toast) => (
                <div
                    key={toast.id}
                    role={toast.type === 'error' ? 'alert' : 'status'}
                    className={cn(
                        'pointer-events-auto flex items-start gap-3 rounded-card border bg-surface p-4 text-sm shadow-lg',
                        toast.type === 'success'
                            ? 'border-success/30'
                            : 'border-danger/30',
                    )}
                >
                    {toast.type === 'success' ? (
                        <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-success" />
                    ) : (
                        <XCircle className="mt-0.5 size-5 shrink-0 text-danger" />
                    )}
                    <p className="flex-1 text-text">{toast.message}</p>
                    <button
                        type="button"
                        aria-label="Tutup"
                        className="text-text-muted hover:text-text"
                        onClick={() =>
                            setToasts((current) =>
                                current.filter((t) => t.id !== toast.id),
                            )
                        }
                    >
                        <X className="size-4" />
                    </button>
                </div>
            ))}
        </div>
    );
}
