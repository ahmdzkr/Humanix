import {
    CheckCircle2,
    Circle,
    Clock,
    MinusCircle,
    XCircle,
} from 'lucide-react';
import { StatusBadge } from '@/components/status-badge';
import { formatJam, formatTanggal } from '@/lib/format';
import type { ApprovalStepView } from '@/lib/requests';
import { stepTone } from '@/lib/requests';
import { cn } from '@/lib/utils';

const icons = {
    pending: Clock,
    waiting: Circle,
    approved: CheckCircle2,
    rejected: XCircle,
    skipped: MinusCircle,
    cancelled: MinusCircle,
};

export function ApprovalTimeline({ steps }: { steps: ApprovalStepView[] }) {
    return (
        <ol className="space-y-4">
            {steps.map((s) => {
                const Icon = icons[s.status];

                return (
                    <li key={s.step} className="flex gap-3">
                        <Icon
                            className={cn(
                                'mt-0.5 size-5 shrink-0',
                                s.status === 'approved' && 'text-success',
                                s.status === 'rejected' && 'text-danger',
                                s.status === 'pending' && 'text-warning',
                                ['waiting', 'skipped', 'cancelled'].includes(
                                    s.status,
                                ) && 'text-text-muted',
                            )}
                        />
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="font-semibold text-navy">
                                    Langkah {s.step}: {s.role}
                                    {s.assignee && (
                                        <span className="font-normal text-text-muted">
                                            {' '}
                                            ({s.assignee})
                                        </span>
                                    )}
                                </p>
                                <StatusBadge tone={stepTone[s.status]}>
                                    {s.status_label}
                                </StatusBadge>
                            </div>
                            {s.decided_by && s.decided_at && (
                                <p className="text-xs text-text-muted">
                                    {s.decided_by} ·{' '}
                                    {formatTanggal(s.decided_at)},{' '}
                                    {formatJam(s.decided_at)}
                                </p>
                            )}
                            {s.note && (
                                <p className="mt-1 text-sm text-text-muted italic">
                                    “{s.note}”
                                </p>
                            )}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
