import type { StatusTone } from '@/components/status-badge';

export type AttendanceStatusValue = 'present' | 'late' | 'absent' | 'leave';

export type AttendanceRow = {
    id: number;
    employee_id: number;
    full_name: string | null;
    nik: string | null;
    department: string | null;
    work_date: string;
    check_in: string | null;
    check_out: string | null;
    status: AttendanceStatusValue;
    status_label: string;
    late_minutes: number;
    source: string;
    note: string | null;
    corrections: {
        reason: string;
        actor: string;
        old: Record<string, string | null> | null;
        new: Record<string, string | null>;
        created_at: string | null;
    }[];
};

export const statusTone: Record<AttendanceStatusValue, StatusTone> = {
    present: 'success',
    late: 'warning',
    absent: 'danger',
    leave: 'info',
};

export const sourceLabel: Record<string, string> = {
    scan: 'Scan QR',
    system: 'Otomatis',
    correction: 'Koreksi HRD',
};

/** Tanggal hari ini menurut WIB, format YYYY-MM-DD. */
export const todayWib = () =>
    new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Jakarta' });
