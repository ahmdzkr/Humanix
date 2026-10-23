import { router } from '@inertiajs/react';
import { FileText, Search, UserRound } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useEffect, useId, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import { search } from '@/routes';

type Result = { title: string; subtitle?: string; url: string };
type Group = { label: string; icon: typeof Search; items: Result[] };

/**
 * Pencarian global: halaman yang boleh dibuka + karyawan yang boleh dilihat (dibatasi server).
 * Panah atas/bawah memilih, Enter membuka, Esc menutup.
 */
export function GlobalSearch() {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<{
        pages: Result[];
        employees: Result[];
    }>({ pages: [], employees: [] });
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(0);
    const [loading, setLoading] = useState(false);
    const listId = useId();
    const boxRef = useRef<HTMLFormElement>(null);

    const groups: Group[] = [
        { label: 'Halaman', icon: FileText, items: results.pages },
        { label: 'Karyawan', icon: UserRound, items: results.employees },
    ].filter((g) => g.items.length > 0);
    const flat = groups.flatMap((g) => g.items);

    useEffect(() => {
        const q = query.trim();

        if (q.length < 2) {
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true);
            fetch(search.url({ query: { q } }), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((r) => (r.ok ? r.json() : { pages: [], employees: [] }))
                .then((data) => {
                    setResults(data);
                    setActive(0);
                })
                .catch(() => undefined)
                .finally(() => setLoading(false));
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query]);

    useEffect(() => {
        const close = (e: MouseEvent) => {
            if (!boxRef.current?.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, []);

    const go = (item: Result | undefined) => {
        if (!item) {
            return;
        }

        setOpen(false);
        setQuery('');
        setResults({ pages: [], employees: [] });
        router.visit(item.url);
    };

    const onKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive((i) => Math.min(i + 1, flat.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((i) => Math.max(i - 1, 0));
        } else if (e.key === 'Escape') {
            setOpen(false);
        }
    };

    const showPanel = open && query.trim().length >= 2;
    let index = -1;

    return (
        <form
            ref={boxRef}
            role="search"
            className="relative hidden max-w-md flex-1 sm:block"
            onSubmit={(event) => {
                event.preventDefault();
                go(flat[active]);
            }}
        >
            <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-text-muted" />
            <input
                type="search"
                name="q"
                value={query}
                onChange={(e) => {
                    setQuery(e.target.value);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                onKeyDown={onKeyDown}
                autoComplete="off"
                role="combobox"
                aria-expanded={showPanel}
                aria-controls={listId}
                aria-label="Pencarian global"
                placeholder="Cari karyawan, halaman…"
                className="h-11 w-full rounded-control border border-border bg-background pr-4 pl-10 text-sm placeholder:text-text-muted focus:border-primary-bright focus:ring-2 focus:ring-primary-bright/20 focus:outline-none"
            />
            {showPanel && (
                <div
                    id={listId}
                    role="listbox"
                    className="absolute top-full right-0 left-0 z-50 mt-2 max-h-96 overflow-y-auto rounded-card border border-border bg-surface py-2 shadow-lg"
                >
                    {flat.length === 0 ? (
                        <p className="px-4 py-3 text-sm text-text-muted">
                            {loading
                                ? 'Mencari…'
                                : `Tidak ada hasil untuk "${query.trim()}".`}
                        </p>
                    ) : (
                        groups.map((g) => (
                            <div key={g.label}>
                                <p className="px-4 pt-2 pb-1 text-xs font-semibold tracking-wide text-text-muted uppercase">
                                    {g.label}
                                </p>
                                {g.items.map((item) => {
                                    index++;
                                    const i = index;

                                    return (
                                        <button
                                            key={item.url}
                                            type="button"
                                            role="option"
                                            aria-selected={i === active}
                                            onMouseEnter={() => setActive(i)}
                                            onClick={() => go(item)}
                                            className={cn(
                                                'flex w-full items-center gap-3 px-4 py-2 text-left text-sm',
                                                i === active
                                                    ? 'bg-accent'
                                                    : 'hover:bg-accent',
                                            )}
                                        >
                                            <g.icon className="size-4 shrink-0 text-text-muted" />
                                            <span className="min-w-0">
                                                <span className="block truncate font-medium text-navy">
                                                    {item.title}
                                                </span>
                                                {item.subtitle && (
                                                    <span className="block truncate text-xs text-text-muted">
                                                        {item.subtitle}
                                                    </span>
                                                )}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        ))
                    )}
                </div>
            )}
        </form>
    );
}
