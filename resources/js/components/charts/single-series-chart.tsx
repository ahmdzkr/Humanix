import type { LucideIcon } from 'lucide-react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {
    AXIS,
    ChartCard,
    ChartTooltip,
    EmptyChart,
    GRID,
} from '@/components/charts/chart-kit';

/**
 * Satu seri per grafik (tanpa sumbu ganda). Dua ukuran berbeda skala → dua grafik.
 */
export function SingleSeriesChart({
    title,
    icon,
    data,
    dataKey,
    xKey,
    name,
    color,
    formatX,
    unit,
    className,
}: {
    title: string;
    icon: LucideIcon;
    data: Record<string, string | number>[];
    dataKey: string;
    xKey: string;
    name: string;
    color: string;
    formatX: (v: string) => string;
    unit: string;
    className?: string;
}) {
    const hasData = data.some((d) => Number(d[dataKey]) > 0);

    return (
        <ChartCard
            title={title}
            icon={icon}
            className={className}
            table={{
                headers: ['Periode', `${name} (${unit})`],
                rows: data.map((d) => [formatX(String(d[xKey])), d[dataKey]]),
            }}
        >
            {hasData ? (
                <div className="h-48">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={data}
                            margin={{ top: 4, right: 4, bottom: 0, left: -20 }}
                        >
                            <CartesianGrid vertical={false} stroke={GRID} />
                            <XAxis
                                dataKey={xKey}
                                tickFormatter={formatX}
                                tick={AXIS}
                                axisLine={false}
                                tickLine={false}
                            />
                            <YAxis
                                allowDecimals={false}
                                tick={AXIS}
                                axisLine={false}
                                tickLine={false}
                            />
                            <Tooltip
                                cursor={{ fill: 'rgba(0,87,255,0.06)' }}
                                content={
                                    <ChartTooltip
                                        formatLabel={formatX}
                                        formatValue={(v) =>
                                            `${Number(v).toLocaleString('id-ID')} ${unit}`
                                        }
                                    />
                                }
                            />
                            <Bar
                                dataKey={dataKey}
                                name={name}
                                fill={color}
                                radius={[4, 4, 0, 0]}
                                maxBarSize={28}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            ) : (
                <EmptyChart message="Belum ada data pada 6 bulan terakhir." />
            )}
        </ChartCard>
    );
}
