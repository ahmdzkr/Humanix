import type { Page, TestInfo } from '@playwright/test';
import { expect } from '@playwright/test';

export type RoleKey = 'admin' | 'hrd' | 'manager' | 'karyawan';
export type AccountKey = RoleKey | 'supervisor';

/** Akun demo dari DatabaseSeeder (hanya lingkungan lokal). */
export const accounts: Record<
    AccountKey,
    { email: string; dashboard: string; name: string }
> = {
    admin: {
        email: 'admin@humanix.test',
        dashboard: '/admin/dashboard',
        name: 'Admin',
    },
    hrd: {
        email: 'hrd@humanix.test',
        dashboard: '/hr/dashboard',
        name: 'Rina',
    },
    manager: {
        email: 'manager@humanix.test',
        dashboard: '/manager/dashboard',
        name: 'Bayu',
    },
    karyawan: {
        email: 'karyawan@humanix.test',
        dashboard: '/me/dashboard',
        name: 'Siti',
    },
    // Atasan langsung Siti: berperan Karyawan, tetapi menyetujui bawahannya lewat struktur.
    supervisor: {
        email: 'agus@humanix.test',
        dashboard: '/me/dashboard',
        name: 'Agus',
    },
};

export const DEMO_PASSWORD = 'password';

export async function login(page: Page, role: AccountKey) {
    const account = accounts[role];

    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(account.email);
    await page.getByLabel('Kata Sandi', { exact: true }).fill(DEMO_PASSWORD);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.waitForURL(`**${account.dashboard}`);
}

/**
 * Pantau error console & error JavaScript selama tes. Kembalikan daftar yang terkumpul.
 * Kegagalan memuat favicon/sumber pihak ketiga tidak diabaikan: semua error dicatat apa adanya.
 */
export function watchConsole(page: Page): string[] {
    const errors: string[] = [];

    page.on('console', (msg) => {
        if (msg.type() === 'error') {
            errors.push(`console: ${msg.text()}`);
        }
    });
    page.on('pageerror', (err) => errors.push(`pageerror: ${err.message}`));

    return errors;
}

/** Tidak boleh ada scroll horizontal di level halaman. */
export async function expectNoHorizontalOverflow(page: Page, info: TestInfo) {
    const { scrollWidth, clientWidth } = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
    }));

    expect
        .soft(
            scrollWidth,
            `${info.project.name} ${page.url()}: lebar konten ${scrollWidth}px > layar ${clientWidth}px`,
        )
        .toBeLessThanOrEqual(clientWidth);
}
