import type { StatusTone } from '@/components/status-badge';

export type PayrollStatusValue =
    'draft' | 'calculated' | 'reviewed' | 'approved' | 'closed';

export const payrollTone: Record<PayrollStatusValue, StatusTone> = {
    draft: 'neutral',
    calculated: 'info',
    reviewed: 'warning',
    approved: 'success',
    closed: 'neutral',
};

export const payrollSteps: { value: PayrollStatusValue; label: string }[] = [
    { value: 'draft', label: 'Draft' },
    { value: 'calculated', label: 'Dihitung' },
    { value: 'reviewed', label: 'Direview' },
    { value: 'approved', label: 'Disetujui' },
    { value: 'closed', label: 'Ditutup' },
];

export type PaymentStatusValue =
    'pending' | 'processing' | 'paid' | 'failed' | 'cancelled';

export const paymentTone: Record<PaymentStatusValue, StatusTone> = {
    pending: 'warning',
    processing: 'info',
    paid: 'success',
    failed: 'danger',
    cancelled: 'neutral',
};
