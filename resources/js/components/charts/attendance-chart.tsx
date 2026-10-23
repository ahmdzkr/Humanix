import { BarChart3 } from 'lucide-react';
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
    SERIES,
    shortDay,
} from '@/components/charts/chart-kit';

export type AttendanceDay = {
    date: string;
    scheduled: number;
    present: number;
    late: number;
    absent: number;
    leave: number;
    not_yet: number;
    rate: number;
};

/** Tren kehadiran: Hadir vs Tidak Hadir per hari (dua seri, satu sumbu). */
export function AttendanceChart({
    data,
    title = 'Tren Kehadiran',
    className,
}: {
    data: AttendanceDay[];
    title?: string;
    className?: string;
}) {
    const hasData = data.some((d) => d.present + d.absent > 0);
    const dense = data.length > 10;

    return (
        <ChartCard
            title={title}
            icon={BarChart3}
            className={className}
            legend={[
                { label: 'Hadir', color: SERIES[0] },
                { label: 'Tidak Hadir', color: SERIES[1] },
            ]}
            table={{
                headers: [
                    'Tanggal',
                    'Terjadwal',
                    'Hadir',
                    'Terlambat',
                    'Tidak Hadir',
                    'Cuti',
                    '% Hadir',
                ],
                rows: data.map((d) => [
                    shortDay(d.date),
                    d.scheduled,
                    d.present,
                    d.late,
                    d.absent,
                    d.leave,
                    `${d.rate}%`,
                ]),
            }}
        >
            {hasData ? (
                <div className="h-64">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={data}
                            barGap={2}
                            margin={{ top: 4, right: 4, bottom: 0, left: -20 }}
                        >
                            <CartesianGrid vertical={false} stroke={GRID} />
                            <XAxis
                                dataKey="date"
                                tickFormatter={shortDay}
                                tick={AXIS}
                                axisLine={false}
                                tickLine={false}
                                interval={dense ? 4 : 0}
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
                                    <ChartTooltip formatLabel={shortDay} />
                                }
                            />
                            <Bar
                                dataKey="present"
                                name="Hadir"
                                fill={SERIES[0]}
                                radius={[4, 4, 0, 0]}
                                maxBarSize={dense ? 10 : 22}
                            />
                            <Bar
                                dataKey="absent"
                                name="Tidak Hadir"
                                fill={SERIES[1]}
                                radius={[4, 4, 0, 0]}
                                maxBarSize={dense ? 10 : 22}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            ) : (
                <EmptyChart message="Belum ada catatan kehadiran pada rentang ini." />
            )}
        </ChartCard>
    );
}
