import { Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Inbox, Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { selectClassName } from '@/components/form-field';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export type Column<T> = {
    key: string;
    header: string;
    cell: (row: T) => ReactNode;
    className?: string;
};

export type FilterOption = { value: string; label: string };

export type Filter = {
    name: string;
    label: string;
    options: FilterOption[];
};

type Props<T> = {
    rows: Paginated<T>;
    columns: Column<T>[];
    /** Nilai filter aktif dari server (termasuk `search`). */
    filters: Record<string, string>;
    filterDefs?: Filter[];
    searchPlaceholder?: string;
    emptyTitle?: string;
    emptyDescription?: string;
    rowKey: (row: T) => string | number;
    /** false = tanpa kotak pencarian (mis. log yang hanya difilter). */
    searchable?: boolean;
    /** Kontrol filter tambahan di baris filter (mis. rentang tanggal). */
    toolbar?: ReactNode;
};

/**
 * Tabel dengan pencarian, filter, dan pagination sisi server (query string).
 * Di layar kecil baris ditampilkan sebagai kartu.
 */
export function DataTable<T>({
    rows,
    columns,
    filters,
    filterDefs = [],
    searchPlaceholder = 'Cari…',
    emptyTitle = 'Belum ada data',
    emptyDescription = 'Data yang ditambahkan akan muncul di sini.',
    rowKey,
    searchable = true,
    toolbar,
}: Props<T>) {
    const [search, setSearch] = useState(filters.search ?? '');
    const visit = (next: Record<string, string>) => {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...next }).filter(([, v]) => v !== ''),
        );
        router.get(window.location.pathname, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    useEffect(() => {
        // Hanya cari jika teks berbeda dari filter aktif (juga aman saat StrictMode memasang ulang).
        if (search === (filters.search ?? '')) {
            return;
        }

        const timer = setTimeout(() => visit({ search, page: '' }), 350);

        return () => clearTimeout(timer);
        // visit sengaja tidak dijadikan dependensi agar tidak memicu ulang.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const hasActiveFilter =
        (filters.search ?? '') !== '' ||
        filterDefs.some((def) => (filters[def.name] ?? '') !== '');

    return (
        <div className="rounded-card border border-border bg-surface">
            <div className="flex flex-col gap-3 border-b border-border p-4 md:flex-row md:items-center">
                <div className={cn('relative flex-1', !searchable && 'hidden')}>
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-text-muted" />
                    <input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={searchPlaceholder}
                        aria-label="Cari"
                        className={cn(selectClassName, 'pl-9')}
                    />
                </div>
                {filterDefs.map((def) => (
                    <select
                        key={def.name}
                        aria-label={def.label}
                        value={filters[def.name] ?? ''}
                        onChange={(event) =>
                            visit({ [def.name]: event.target.value, page: '' })
                        }
                        className={cn(selectClassName, 'md:w-48')}
                    >
                        <option value="">{def.label}: Semua</option>
                        {def.options.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                ))}
                {toolbar}
            </div>

            {rows.data.length === 0 ? (
                <div className="flex flex-col items-center justify-center px-6 py-14 text-center">
                    <div className="mb-3 flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <Inbox className="size-6" />
                    </div>
                    <p className="font-semibold text-navy">
                        {hasActiveFilter ? 'Data tidak ditemukan' : emptyTitle}
                    </p>
                    <p className="mt-1 text-sm text-text-muted">
                        {hasActiveFilter
                            ? 'Coba ubah kata kunci atau filter.'
                            : emptyDescription}
                    </p>
                </div>
            ) : (
                <>
                    {/* Tabel untuk layar md ke atas */}
                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-muted/60 text-left text-xs font-semibold tracking-wide text-text-muted uppercase">
                                    {columns.map((column) => (
                                        <th
                                            key={column.key}
                                            scope="col"
                                            className={cn(
                                                'px-4 py-3',
                                                column.className,
                                            )}
                                        >
                                            {column.header}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.data.map((row) => (
                                    <tr
                                        key={rowKey(row)}
                                        className="border-t border-border hover:bg-background/60"
                                    >
                                        {columns.map((column) => (
                                            <td
                                                key={column.key}
                                                className={cn(
                                                    'px-4 py-3 align-middle',
                                                    column.className,
                                                )}
                                            >
                                                {column.cell(row)}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Kartu untuk ponsel */}
                    <ul className="divide-y divide-border md:hidden">
                        {rows.data.map((row) => (
                            <li key={rowKey(row)} className="space-y-2 p-4">
                                {columns.map((column) => (
                                    <div
                                        key={column.key}
                                        className="flex items-start justify-between gap-4 text-sm"
                                    >
                                        <span className="shrink-0 text-text-muted">
                                            {column.header}
                                        </span>
                                        <span className="text-right">
                                            {column.cell(row)}
                                        </span>
                                    </div>
                                ))}
                            </li>
                        ))}
                    </ul>
                </>
            )}

            <Pagination rows={rows} />
        </div>
    );
}

/** Ringkasan jumlah + tombol halaman sebelumnya/berikutnya (pagination Laravel). */
export function Pagination<T>({ rows }: { rows: Paginated<T> }) {
    if (rows.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-col items-center justify-between gap-3 border-t border-border p-4 text-sm text-text-muted sm:flex-row">
            <p>
                Menampilkan {rows.from}–{rows.to} dari {rows.total} data
            </p>
            <nav
                aria-label="Navigasi halaman"
                className="flex items-center gap-1"
            >
                <PageLink url={rows.prev_page_url} label="Sebelumnya">
                    <ChevronLeft className="size-4" />
                </PageLink>
                <span className="px-2">
                    Halaman {rows.current_page} dari {rows.last_page}
                </span>
                <PageLink url={rows.next_page_url} label="Berikutnya">
                    <ChevronRight className="size-4" />
                </PageLink>
            </nav>
        </div>
    );
}

function PageLink({
    url,
    label,
    children,
}: {
    url: string | null;
    label: string;
    children: ReactNode;
}) {
    const className =
        'flex size-9 items-center justify-center rounded-control border border-border';

    if (!url) {
        return (
            <span
                aria-disabled
                aria-label={label}
                className={cn(className, 'opacity-40')}
            >
                {children}
            </span>
        );
    }

    return (
        <Link
            href={url}
            aria-label={label}
            preserveScroll
            preserveState
            className={cn(className, 'text-navy hover:bg-accent')}
        >
            {children}
        </Link>
    );
}
