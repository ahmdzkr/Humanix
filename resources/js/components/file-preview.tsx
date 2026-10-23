import { Download, Eye, Printer } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

/**
 * Pratinjau file di dalam aplikasi sebelum diunduh.
 * - pdf: ditampilkan inline di iframe (server mengirim Content-Disposition inline).
 * - image: ditampilkan sebagai gambar.
 * - excel: tabel dari `?preview=1` (data ekspor yang sama); file .xlsx baru diunduh lewat tombol Unduh.
 * Unduhan PDF/gambar memakai `?download=1`. Otorisasi tetap di server untuk semua mode.
 */
export type PreviewKind = 'pdf' | 'image' | 'excel';

type ExcelPreview = {
    title: string;
    filename: string;
    headings: string[];
    rows: (string | number | null)[][];
    total: number;
    truncated: boolean;
};

function withParam(url: string, key: string): string {
    const u = new URL(url, window.location.origin);
    u.searchParams.set(key, '1');

    return u.pathname + u.search;
}

function escapeHtml(value: unknown): string {
    return String(value ?? '').replace(
        /[&<>"']/g,
        (c) =>
            ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            })[c]!,
    );
}

/** Cetak HTML lewat iframe tersembunyi agar halaman aplikasi tidak ikut tercetak. */
function printHtml(html: string) {
    const frame = document.createElement('iframe');
    frame.style.position = 'fixed';
    frame.style.width = '0';
    frame.style.height = '0';
    frame.style.border = '0';
    frame.srcdoc = html;
    frame.onload = () => {
        frame.contentWindow?.focus();
        frame.contentWindow?.print();
        setTimeout(() => frame.remove(), 1000);
    };
    document.body.appendChild(frame);
}

export function FilePreviewDialog({
    open,
    onOpenChange,
    url,
    kind,
    title,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    url: string;
    kind: PreviewKind;
    title: string;
}) {
    const frameRef = useRef<HTMLIFrameElement>(null);
    const [data, setData] = useState<ExcelPreview | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open || kind !== 'excel') {
            return;
        }

        const controller = new AbortController();
        fetch(withParam(url, 'preview'), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then(async (res) => {
                if (!res.ok) {
                    throw new Error(
                        res.status === 403
                            ? 'Anda tidak memiliki akses ke file ini.'
                            : 'Pratinjau gagal dimuat.',
                    );
                }

                setData((await res.json()) as ExcelPreview);
            })
            .catch((e: Error) => {
                if (e.name !== 'AbortError') {
                    setError(e.message);
                }
            });

        return () => controller.abort();
    }, [open, kind, url]);

    const downloadUrl = kind === 'excel' ? url : withParam(url, 'download');

    const print = () => {
        if (kind === 'pdf') {
            frameRef.current?.contentWindow?.focus();
            frameRef.current?.contentWindow?.print();
        } else if (kind === 'image') {
            printHtml(
                `<!doctype html><title>${escapeHtml(title)}</title><style>body{margin:0;display:flex;justify-content:center}img{max-width:100%}</style><img src="${escapeHtml(url)}">`,
            );
        } else if (data) {
            const head = data.headings
                .map((h) => `<th>${escapeHtml(h)}</th>`)
                .join('');
            const body = data.rows
                .map(
                    (r) =>
                        `<tr>${r.map((c) => `<td>${escapeHtml(c)}</td>`).join('')}</tr>`,
                )
                .join('');
            printHtml(
                `<!doctype html><title>${escapeHtml(data.title)}</title><style>body{font:11px sans-serif}table{border-collapse:collapse;width:100%}th,td{border:1px solid #999;padding:3px 5px;text-align:left}th{background:#eee}</style><h3>${escapeHtml(title)}</h3><table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table>`,
            );
        }
    };

    // Data lama dibuang saat ditutup agar pembukaan berikutnya memuat ulang (filter bisa sudah berubah).
    const handleOpenChange = (next: boolean) => {
        if (!next) {
            setData(null);
            setError(null);
        }

        onOpenChange(next);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="flex h-[90vh] max-h-[90vh] flex-col gap-3 sm:max-w-5xl">
                <DialogHeader className="pr-8">
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        {kind === 'excel'
                            ? 'Pratinjau data. Unduh untuk mendapatkan file Excel lengkap.'
                            : 'Pratinjau file. Gunakan tombol di bawah untuk mengunduh atau mencetak.'}
                    </DialogDescription>
                </DialogHeader>

                <div
                    className="min-h-0 flex-1 overflow-auto rounded-control border border-border bg-muted/30"
                    data-testid="file-preview-body"
                >
                    {kind === 'pdf' && open && (
                        <iframe
                            ref={frameRef}
                            src={url}
                            title={title}
                            className="size-full min-h-[60vh] bg-white"
                        />
                    )}
                    {kind === 'image' && open && (
                        <div className="flex size-full items-center justify-center p-4">
                            <img
                                src={url}
                                alt={title}
                                className="max-h-full max-w-full object-contain"
                            />
                        </div>
                    )}
                    {kind === 'excel' &&
                        (error ? (
                            <p className="p-6 text-sm text-danger">{error}</p>
                        ) : !data ? (
                            <div className="flex h-40 items-center justify-center gap-2 text-sm text-text-muted">
                                <Spinner /> Memuat pratinjau…
                            </div>
                        ) : data.rows.length === 0 ? (
                            <p className="p-6 text-sm text-text-muted">
                                Tidak ada data untuk ditampilkan.
                            </p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="sticky top-0 bg-surface">
                                    <tr>
                                        {data.headings.map((h, i) => (
                                            <th
                                                key={i}
                                                className="border-b border-border px-3 py-2 text-left font-semibold whitespace-nowrap text-navy"
                                            >
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.rows.map((r, i) => (
                                        <tr
                                            key={i}
                                            className="border-b border-border/60 bg-surface"
                                        >
                                            {r.map((c, j) => (
                                                <td
                                                    key={j}
                                                    className="px-3 py-1.5 whitespace-nowrap"
                                                >
                                                    {typeof c === 'number'
                                                        ? c.toLocaleString(
                                                              'id-ID',
                                                          )
                                                        : (c ?? '')}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        ))}
                </div>

                <div className="flex flex-wrap items-center justify-end gap-2">
                    {kind === 'excel' && data?.truncated && (
                        <p className="mr-auto text-xs text-text-muted">
                            Menampilkan {data.rows.length} dari {data.total}{' '}
                            baris. File Excel berisi semua baris.
                        </p>
                    )}
                    <Button
                        variant="outline"
                        onClick={print}
                        disabled={kind === 'excel' && !data}
                    >
                        <Printer className="size-4" />
                        Cetak
                    </Button>
                    <Button asChild>
                        {/* Unduhan file biasa (bukan kunjungan Inertia). */}
                        <a href={downloadUrl}>
                            <Download className="size-4" />
                            Unduh
                        </a>
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

/** Tombol yang membuka pratinjau file; dipakai di semua titik unduhan. */
export function FilePreviewButton({
    url,
    kind,
    title,
    children,
    icon: Icon = Eye,
    ...buttonProps
}: {
    url: string;
    kind: PreviewKind;
    title: string;
    children?: ReactNode;
    icon?: LucideIcon;
} & Omit<ComponentProps<typeof Button>, 'onClick' | 'asChild'>) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                type="button"
                {...buttonProps}
                onClick={() => setOpen(true)}
            >
                <Icon className="size-4" />
                {children}
            </Button>
            <FilePreviewDialog
                open={open}
                onOpenChange={setOpen}
                url={url}
                kind={kind}
                title={title}
            />
        </>
    );
}
