import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type StatusTone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

const toneClasses: Record<StatusTone, string> = {
    success: 'bg-success/10 text-success',
    warning: 'bg-warning/15 text-[#b45309]',
    danger: 'bg-danger/10 text-danger',
    info: 'bg-primary/10 text-primary',
    neutral: 'bg-muted text-text-muted',
};

export function StatusBadge({
    tone = 'neutral',
    children,
}: {
    tone?: StatusTone;
    children: ReactNode;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold',
                toneClasses[tone],
            )}
        >
            {children}
        </span>
    );
}
