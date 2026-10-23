import type { StatusTone } from '@/components/status-badge';

export type ReviewStatusValue =
    | 'draft'
    | 'self_assessed'
    | 'manager_reviewed'
    | 'returned'
    | 'final'
    | 'published';

export const reviewTone: Record<ReviewStatusValue, StatusTone> = {
    draft: 'neutral',
    self_assessed: 'warning',
    manager_reviewed: 'info',
    returned: 'danger',
    final: 'success',
    published: 'success',
};

export const periodTone: Record<string, StatusTone> = {
    draft: 'neutral',
    active: 'success',
    closed: 'info',
};

/** "87.50" → "87,5" */
export const formatScore = (score: string | number | null) =>
    score === null
        ? '—'
        : Number(score).toLocaleString('id-ID', { maximumFractionDigits: 2 });
