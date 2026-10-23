import type { StatusTone } from '@/components/status-badge';

export type ApplicationStatusValue =
    'applied' | 'shortlisted' | 'interview' | 'accepted' | 'hired' | 'rejected';

export const applicationTone: Record<ApplicationStatusValue, StatusTone> = {
    applied: 'warning',
    shortlisted: 'info',
    interview: 'info',
    accepted: 'success',
    hired: 'success',
    rejected: 'danger',
};
