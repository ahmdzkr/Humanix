import type { LucideIcon } from 'lucide-react';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import {
    ChartCard,
    ChartTooltip,
    EmptyChart,
    SERIES,
} from '@/components/charts/chart-kit';

const MAX_SLICES = 6;

/**
 * Donut distribusi dengan total di tengah dan legenda berangka (identitas tidak hanya lewat warna).
 * Lebih dari 6 kategori digabung menjadi "Lainnya" — tidak membuat warna baru.
 */
export function DonutChart({
    title,
    icon,
    data,
    totalLabel,
    className,
}: {
    title: string;
    icon: LucideIcon;
    data: { name: string; total: number }[];
    totalLabel: string;
    className?: string;
}) {
    const sorted = [...data].sort((a, b) => b.total - a.total);
    const head = sorted.slice(0, MAX_SLICES);
    const rest = sorted.slice(MAX_SLICES).reduce((sum, d) => sum + d.total, 0);
    const slices =
        rest > 0 ? [...head, { name: 'Lainnya', total: rest }] : head;
    const total = slices.reduce((sum, d) => sum + d.total, 0);

    return (
        <ChartCard
            title={title}
            icon={icon}
            className={className}
            table={{
                headers: ['Kategori', 'Jumlah', '%'],
                rows: slices.map((s) => [
                    s.name,
                    s.total,
                    total ? `${Math.round((s.total / total) * 100)}%` : '0%',
                ]),
            }}
        >
            {total === 0 ? (
                <EmptyChart message="Belum ada data." />
            ) : (
                <div className="flex flex-col items-center gap-4 sm:flex-row xl:flex-col 2xl:flex-row">
                    <div className="relative h-48 w-48 shrink-0">
                        <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                                <Pie
                                    data={slices}
                                    dataKey="total"
                                    nameKey="name"
                                    innerRadius="62%"
                                    outerRadius="100%"
                                    stroke="#FFFFFF"
                                    strokeWidth={2}
                                    isAnimationActive={false}
                                >
                                    {slices.map((s, i) => (
                                        <Cell key={s.name} fill={SERIES[i]} />
                                    ))}
                                </Pie>
                                <Tooltip content={<ChartTooltip />} />
                            </PieChart>
                        </ResponsiveContainer>
                        <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                            <span className="text-2xl font-bold text-navy">
                                {total}
                            </span>
                            <span className="text-xs text-text-muted">
                                {totalLabel}
                            </span>
                        </div>
                    </div>
                    <ul className="w-full space-y-2 text-sm">
                        {slices.map((s, i) => (
                            <li
                                key={s.name}
                                className="flex items-center justify-between gap-2"
                            >
                                <span className="flex min-w-0 items-center gap-2">
                                    <span
                                        aria-hidden
                                        className="size-2.5 shrink-0 rounded-sm"
                                        style={{ background: SERIES[i] }}
                                    />
                                    <span className="truncate">{s.name}</span>
                                </span>
                                <span className="shrink-0 text-text-muted">
                                    <span className="font-semibold text-navy">
                                        {s.total}
                                    </span>{' '}
                                    · {Math.round((s.total / total) * 100)}%
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </ChartCard>
    );
}
