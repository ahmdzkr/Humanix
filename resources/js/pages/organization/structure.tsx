import { Head } from '@inertiajs/react';
import { Building2, ChevronDown, ChevronRight, Network } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { useCan } from '@/lib/navigation';

type PositionNode = { id: number; code: string; name: string; level: number };

type DepartmentNode = {
    id: number;
    code: string;
    name: string;
    positions: PositionNode[];
    children: DepartmentNode[];
};

type Props = {
    tree: DepartmentNode[];
    unassignedPositions: PositionNode[];
};

function PositionChips({ positions }: { positions: PositionNode[] }) {
    if (positions.length === 0) {
        return (
            <p className="text-xs text-text-muted">Belum ada jabatan aktif.</p>
        );
    }

    return (
        <ul className="flex flex-wrap gap-2">
            {positions.map((position) => (
                <li
                    key={position.id}
                    className="rounded-full bg-secondary px-3 py-1 text-xs font-medium text-navy"
                >
                    {position.name}
                    <span className="ml-1 text-text-muted">
                        · L{position.level}
                    </span>
                </li>
            ))}
        </ul>
    );
}

function DepartmentItem({ node }: { node: DepartmentNode }) {
    const [open, setOpen] = useState(true);
    const hasChildren = node.children.length > 0;

    return (
        <li>
            <div className="rounded-card border border-border bg-surface p-4">
                <div className="flex items-center gap-3">
                    {hasChildren ? (
                        <button
                            type="button"
                            onClick={() => setOpen((value) => !value)}
                            aria-expanded={open}
                            aria-label={
                                open
                                    ? `Ciutkan ${node.name}`
                                    : `Buka ${node.name}`
                            }
                            className="flex size-7 items-center justify-center rounded-md text-text-muted hover:bg-accent"
                        >
                            {open ? (
                                <ChevronDown className="size-4" />
                            ) : (
                                <ChevronRight className="size-4" />
                            )}
                        </button>
                    ) : (
                        <span className="size-7" />
                    )}
                    <span className="flex size-9 items-center justify-center rounded-control bg-primary/10 text-primary">
                        <Building2 className="size-4" />
                    </span>
                    <div className="min-w-0">
                        <p className="truncate font-semibold text-navy">
                            {node.name}
                        </p>
                        <p className="font-mono text-xs text-text-muted">
                            {node.code}
                        </p>
                    </div>
                </div>
                <div className="mt-3 pl-10">
                    <PositionChips positions={node.positions} />
                </div>
            </div>

            {hasChildren && open && (
                <ul className="mt-3 ml-5 space-y-3 border-l-2 border-primary/15 pl-5">
                    {node.children.map((child) => (
                        <DepartmentItem key={child.id} node={child} />
                    ))}
                </ul>
            )}
        </li>
    );
}

export default function Structure({ tree, unassignedPositions }: Props) {
    const can = useCan();

    return (
        <AppLayout
            breadcrumbs={
                can('settings.manage', 'departments.manage', 'positions.manage')
                    ? [
                          { title: 'Pengaturan', href: '/pengaturan' },
                          {
                              title: 'Struktur Organisasi',
                              href: '/organisasi/struktur',
                          },
                      ]
                    : []
            }
        >
            <Head title="Struktur Organisasi" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Struktur Organisasi"
                    description="Departemen dan jabatan aktif, disusun mengikuti departemen induknya."
                />

                {unassignedPositions.length > 0 && (
                    <section className="rounded-card border border-border bg-gradient-to-br from-primary to-cyan p-5 text-white">
                        <p className="text-sm font-semibold">
                            Pimpinan / lintas departemen
                        </p>
                        <ul className="mt-3 flex flex-wrap gap-2">
                            {unassignedPositions.map((position) => (
                                <li
                                    key={position.id}
                                    className="rounded-full bg-white/20 px-3 py-1 text-xs font-medium"
                                >
                                    {position.name}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {tree.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-card border border-border bg-surface px-6 py-16 text-center">
                        <div className="mb-3 flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <Network className="size-6" />
                        </div>
                        <p className="font-semibold text-navy">
                            Struktur organisasi masih kosong
                        </p>
                        <p className="mt-1 text-sm text-text-muted">
                            Tambahkan departemen aktif di menu Pengaturan.
                        </p>
                    </div>
                ) : (
                    <ul className="space-y-3">
                        {tree.map((node) => (
                            <DepartmentItem key={node.id} node={node} />
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
