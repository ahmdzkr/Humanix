import { router } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Award,
    CalendarDays,
    Clock,
    ShieldAlert,
    UserPlus,
    Wallet,
} from 'lucide-react';
import { formatJam, formatTanggal } from '@/lib/format';
import { read } from '@/routes/notifications';

export type AppNotification = {
    id: string;
    category: string;
    title: string;
    body: string;
    has_url: boolean;
    read: boolean;
    at: string | null;
};

export const categoryIcon: Record<string, LucideIcon> = {
    leave: CalendarDays,
    overtime: Clock,
    recruitment: UserPlus,
    payroll: Wallet,
    performance: Award,
    account: ShieldAlert,
};

/** Tandai dibaca; server meneruskan ke halaman terkait bila ada. */
export function openNotification(n: AppNotification) {
    router.post(read.url(n.id), {}, { preserveScroll: true });
}

/** "5 menit lalu" / "2 jam lalu" / tanggal + jam (WIB). */
export function waktuRelatif(at: string | null): string {
    if (!at) {
        return '';
    }

    const minutes = Math.round((Date.now() - new Date(at).getTime()) / 60_000);

    if (minutes < 1) {
        return 'baru saja';
    }

    if (minutes < 60) {
        return `${minutes} menit lalu`;
    }

    if (minutes < 24 * 60) {
        return `${Math.floor(minutes / 60)} jam lalu`;
    }

    return `${formatTanggal(at)} · ${formatJam(at)}`;
}
