import type { LucideIcon } from 'lucide-react';
import { Table2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * Palet kategorikal tervalidasi (validate_palette.js: lolos CVD & normal-vision).
 * Urutan TETAP — warna mengikuti entitas, tidak diputar ulang.
 * Slot 3–5 < 3:1 kontras terhadap putih → selalu disertai label/legenda & tampilan tabel.
 */
export const SERIES = [
    '#0057FF',
    '#eb6834',
    '#1baf7a',
    '#eda100',
    '#e87ba4',
    '#008300',
    '#4a3aa7',
    '#e34948',
];

export const AXIS = { stroke: '#6B7280', fontSize: 12 };
export const GRID = '#E5E7EB';

export type LegendItem = { label: string; color: string; value?: string };

export function Legend({ items }: { items: LegendItem[] }) {
    return (
        <ul className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-text-muted">
            {items.map((item) => (
                <li key={item.label} className="flex items-center gap-1.5">
                    <span
                        aria-hidden
                        className="size-2.5 rounded-sm"
                        style={{ background: item.color }}
                    />
                    <span className="text-text">{item.label}</span>
                    {item.value && (
                        <span className="font-semibold text-navy">
                            {item.value}
                        </span>
                    )}
                </li>
            ))}
        </ul>
    );
}

/**
 * Kartu grafik: judul navy dengan ikon kotak, legenda kanan atas, dan tombol "Tabel"
 * sebagai tampilan alternatif yang dapat diakses (tidak bergantung warna).
 */
export function ChartCard({
    title,
    icon: Icon,
    legend,
    table,
    children,
    className,
}: {
    title: string;
    icon: LucideIcon;
    legend?: LegendItem[];
    table?: { headers: string[]; rows: (string | number)[][] };
    children: ReactNode;
    className?: string;
}) {
    const [showTable, setShowTable] = useState(false);

    return (
        <section
            className={cn(
                'flex flex-col rounded-card border border-border bg-surface p-5',
                className,
            )}
        >
            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <h2 className="flex items-center gap-2 text-base font-semibold text-navy">
                    <span className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Icon className="size-4" />
                    </span>
                    {title}
                </h2>
                <div className="flex items-center gap-3">
                    {legend && <Legend items={legend} />}
                    {table && (
                        <button
                            type="button"
                            onClick={() => setShowTable((v) => !v)}
                            aria-pressed={showTable}
                            className="flex items-center gap-1 rounded-md px-2 py-1 text-xs text-text-muted hover:bg-accent hover:text-navy"
                        >
                            <Table2 className="size-3.5" />
                            {showTable ? 'Grafik' : 'Tabel'}
                        </button>
                    )}
                </div>
            </div>
            {showTable && table ? (
                <div className="max-h-72 overflow-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs text-text-muted uppercase">
                                {table.headers.map((h) => (
                                    <th key={h} className="py-1.5 pr-3">
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {table.rows.map((row, i) => (
                                <tr key={i} className="border-t border-border">
                                    {row.map((cell, j) => (
                                        <td key={j} className="py-1.5 pr-3">
                                            {cell}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                children
            )}
        </section>
    );
}

/** Tooltip seragam untuk Recharts: label di atas, baris seri dengan penanda warna. */
export function ChartTooltip({
    active,
    label,
    payload,
    formatLabel,
    formatValue,
}: {
    active?: boolean;
    label?: string | number;
    payload?: {
        name?: string;
        value?: number | string;
        color?: string;
        payload?: { fill?: string };
    }[];
    formatLabel?: (label: string) => string;
    formatValue?: (value: number | string) => string;
}) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="rounded-control border border-border bg-surface px-3 py-2 text-xs shadow-lg">
            {label !== undefined && (
                <p className="mb-1 font-semibold text-navy">
                    {formatLabel ? formatLabel(String(label)) : label}
                </p>
            )}
            {payload.map((p) => (
                <p key={p.name} className="flex items-center gap-2 text-text">
                    <span
                        className="size-2 rounded-sm"
                        style={{ background: p.color ?? p.payload?.fill }}
                    />
                    {p.name}:{' '}
                    <span className="font-semibold">
                        {p.value !== undefined && formatValue
                            ? formatValue(p.value)
                            : p.value}
                    </span>
                </p>
            ))}
        </div>
    );
}

/** Daftar batang horizontal sederhana (HTML) untuk distribusi kecil; label & nilai selalu terlihat. */
export function BarList({
    items,
    color = SERIES[0],
    empty = 'Belum ada data.',
}: {
    items: { name: string; total: number }[];
    color?: string;
    empty?: string;
}) {
    const max = Math.max(1, ...items.map((i) => i.total));

    if (items.every((i) => i.total === 0)) {
        return (
            <p className="py-6 text-center text-sm text-text-muted">{empty}</p>
        );
    }

    return (
        <ul className="space-y-3">
            {items.map((item) => (
                <li key={item.name}>
                    <div className="mb-1 flex justify-between text-sm">
                        <span>{item.name}</span>
                        <span className="font-semibold text-navy">
                            {item.total}
                        </span>
                    </div>
                    <div className="h-2 rounded-full bg-muted">
                        <div
                            className="h-2 rounded-full"
                            style={{
                                width: `${(item.total / max) * 100}%`,
                                background: color,
                            }}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}

export function EmptyChart({ message }: { message: string }) {
    return (
        <div className="flex h-56 items-center justify-center rounded-lg border border-dashed border-border px-4 text-center text-sm text-text-muted">
            {message}
        </div>
    );
}

/** "2026-10-07" → "Rab 7" (WIB). */
export function shortDay(date: string): string {
    const d = new Date(`${date}T00:00:00+07:00`);
    const weekday = d.toLocaleDateString('id-ID', {
        weekday: 'short',
        timeZone: 'Asia/Jakarta',
    });

    return `${weekday} ${d.toLocaleDateString('id-ID', { day: 'numeric', timeZone: 'Asia/Jakarta' })}`;
}

/** "2026-10" → "Okt 26". */
export function shortMonth(month: string): string {
    return new Date(`${month}-01T00:00:00+07:00`).toLocaleDateString('id-ID', {
        month: 'short',
        year: '2-digit',
        timeZone: 'Asia/Jakarta',
    });
}
