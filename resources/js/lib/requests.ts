import type { StatusTone } from '@/components/status-badge';

export type RequestStatusValue =
    'pending' | 'approved' | 'rejected' | 'cancelled';

export const requestTone: Record<RequestStatusValue, StatusTone> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
    cancelled: 'neutral',
};

export type ApprovalStepView = {
    step: number;
    role: string;
    assignee: string | null;
    status:
        | 'pending'
        | 'waiting'
        | 'approved'
        | 'rejected'
        | 'skipped'
        | 'cancelled';
    status_label: string;
    decided_by: string | null;
    decided_at: string | null;
    note: string | null;
};

export const stepTone: Record<ApprovalStepView['status'], StatusTone> = {
    pending: 'warning',
    waiting: 'neutral',
    approved: 'success',
    rejected: 'danger',
    skipped: 'neutral',
    cancelled: 'neutral',
};

/** Format rentang tanggal singkat: "7–9 Okt 2026" atau "30 Sep – 2 Okt 2026". */
export function formatRange(start: string, end: string): string {
    const opts: Intl.DateTimeFormatOptions = {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'Asia/Jakarta',
    };
    const s = new Date(`${start}T00:00:00+07:00`);
    const e = new Date(`${end}T00:00:00+07:00`);
    const fmt = new Intl.DateTimeFormat('id-ID', opts);

    return start === end ? fmt.format(s) : fmt.formatRange(s, e);
}

/** 150 → "2 jam 30 menit" */
export function formatDuration(minutes: number): string {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;

    if (h === 0) {
        return `${m} menit`;
    }

    return m === 0 ? `${h} jam` : `${h} jam ${m} menit`;
}
