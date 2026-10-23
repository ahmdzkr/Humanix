import type { LucideIcon } from 'lucide-react';
import { TrendingDown, TrendingUp } from 'lucide-react';
import { cn } from '@/lib/utils';

type Tone = 'primary' | 'cyan' | 'success' | 'warning' | 'violet';

const toneClasses: Record<Tone, string> = {
    primary: 'bg-primary/10 text-primary',
    cyan: 'bg-cyan/15 text-[#0095b3]',
    success: 'bg-success/10 text-success',
    warning: 'bg-warning/15 text-[#b45309]',
    violet: 'bg-[#7c3aed]/10 text-[#6d28d9]',
};

type Props = {
    label: string;
    value: string;
    icon: LucideIcon;
    /** Ikon gambar kustom (mis. /images/hadir.png) di kotak ikon; Lucide tetap dipakai sebagai watermark. */
    image?: string;
    tone?: Tone;
    /** Perubahan dibanding periode lalu, mis. "+4,2%". */
    trend?: { value: string; direction: 'up' | 'down' };
    hint?: string;
};

export function KpiCard({
    label,
    value,
    icon: Icon,
    image,
    tone = 'primary',
    trend,
    hint,
}: Props) {
    return (
        <div className="relative overflow-hidden rounded-card border border-border bg-surface p-5">
            <Icon
                aria-hidden
                className="pointer-events-none absolute -right-3 -bottom-3 size-20 text-primary/[0.05]"
            />
            <div
                className={cn(
                    'mb-4 flex size-11 items-center justify-center rounded-control',
                    toneClasses[tone],
                )}
            >
                {image ? (
                    <img
                        src={image}
                        alt=""
                        aria-hidden
                        width={26}
                        height={26}
                        className="size-[26px] object-contain"
                    />
                ) : (
                    <Icon className="size-5" />
                )}
            </div>
            <p className="text-sm text-text-muted">{label}</p>
            <p className="mt-1 text-2xl leading-tight font-bold text-navy 2xl:text-[28px]">
                {value}
            </p>
            {trend ? (
                <p
                    className={cn(
                        'mt-2 flex items-center gap-1 text-xs font-medium',
                        trend.direction === 'up'
                            ? 'text-success'
                            : 'text-danger',
                    )}
                >
                    {trend.direction === 'up' ? (
                        <TrendingUp className="size-3.5" />
                    ) : (
                        <TrendingDown className="size-3.5" />
                    )}
                    {trend.value}
                </p>
            ) : (
                hint && <p className="mt-2 text-xs text-text-muted">{hint}</p>
            )}
        </div>
    );
}
