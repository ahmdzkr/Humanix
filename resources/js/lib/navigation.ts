import { usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Award,
    BarChart3,
    CalendarCheck,
    CalendarDays,
    Clock,
    LayoutDashboard,
    ScrollText,
    Settings,
    UserPlus,
    Users,
    Wallet,
} from 'lucide-react';
import { useMemo } from 'react';
import type { NavItem } from '@/types';

type MenuItem = {
    title: string;
    href: string;
    icon: LucideIcon;
    /** Menu tampil jika pengguna punya salah satu izin ini (kosong = semua). */
    permissions?: string[];
};

// Menu utama sidebar HUMANIX. Izin di sini hanya untuk menyembunyikan menu;
// pembatasan sebenarnya ada di middleware route dan Policy.
const menu: MenuItem[] = [
    { title: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
    {
        title: 'Data Karyawan',
        href: '/karyawan',
        icon: Users,
        permissions: ['employees.view'],
    },
    {
        title: 'Rekruitmen',
        href: '/rekruitmen',
        icon: UserPlus,
        permissions: ['recruitment.view'],
    },
    {
        title: 'Absensi',
        href: '/absensi',
        icon: CalendarCheck,
        permissions: ['attendance.view', 'attendance.self'],
    },
    {
        title: 'Cuti/Izin',
        href: '/cuti-izin',
        icon: CalendarDays,
        permissions: ['leave.view', 'leave.self'],
    },
    {
        title: 'Lembur',
        href: '/lembur',
        icon: Clock,
        permissions: ['overtime.view', 'overtime.self'],
    },
    {
        title: 'Gaji',
        href: '/gaji',
        icon: Wallet,
        permissions: ['payroll.view', 'payroll.self'],
    },
    {
        title: 'Penilaian Kinerja',
        href: '/penilaian-kinerja',
        icon: Award,
        permissions: ['performance.view', 'performance.self'],
    },
    {
        title: 'Laporan',
        href: '/laporan',
        icon: BarChart3,
        permissions: ['reports.view'],
    },
    {
        title: 'Audit Log',
        href: '/audit-log',
        icon: ScrollText,
        permissions: ['audit.view'],
    },
    {
        title: 'Pengaturan',
        href: '/pengaturan',
        icon: Settings,
        permissions: [
            'settings.manage',
            'departments.manage',
            'positions.manage',
            'attendance.manage',
        ],
    },
];

export function useCan() {
    const { auth } = usePage().props;

    return (...permissions: string[]) =>
        permissions.some((permission) => auth.permissions.includes(permission));
}

/** Menu sidebar yang boleh dilihat pengguna saat ini. */
export function useMainNavItems(): NavItem[] {
    const { auth } = usePage().props;

    return useMemo(
        () =>
            menu
                .filter(
                    (item) =>
                        !item.permissions ||
                        item.permissions.some((permission) =>
                            auth.permissions.includes(permission),
                        ),
                )
                .map((item) => ({
                    title: item.title,
                    icon: item.icon,
                    href:
                        item.title === 'Dashboard' && auth.dashboardUrl
                            ? auth.dashboardUrl
                            : item.href,
                })),
        [auth.permissions, auth.dashboardUrl],
    );
}
