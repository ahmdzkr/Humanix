import { expect, test } from '@playwright/test';
import type { RoleKey } from './helpers';
import {
    accounts,
    expectNoHorizontalOverflow,
    login,
    watchConsole,
} from './helpers';

/** Halaman utama tiap role: [path, judul h1 yang diharapkan]. */
const pages: Record<RoleKey, [string, RegExp][]> = {
    admin: [
        ['/admin/dashboard', /Selamat (Pagi|Siang|Sore|Malam), Admin/],
        ['/karyawan', /Data Karyawan/],
        ['/pengaturan', /Pengaturan/],
        ['/pengaturan/sistem', /Pengaturan Sistem/],
        ['/pengaturan/departemen', /Departemen/],
        ['/audit-log', /Audit Log/],
        ['/notifikasi', /Notifikasi/],
    ],
    hrd: [
        ['/hr/dashboard', /Selamat (Pagi|Siang|Sore|Malam), Rina/],
        ['/karyawan', /Data Karyawan/],
        ['/absensi', /Absensi/],
        ['/cuti-izin', /Cuti/],
        ['/lembur', /Lembur/],
        ['/gaji', /Gaji|Payroll/],
        ['/rekruitmen', /Rekru/],
        ['/penilaian-kinerja', /Penilaian Kinerja/],
        ['/pengaturan/jadwal-kerja', /Jadwal Kerja/],
        ['/laporan', /Laporan/],
    ],
    manager: [
        ['/manager/dashboard', /Selamat (Pagi|Siang|Sore|Malam), Bayu/],
        ['/karyawan', /Data Karyawan/],
        ['/cuti-izin', /Cuti/],
        ['/lembur', /Lembur/],
        ['/penilaian-kinerja', /Penilaian Kinerja/],
        ['/rekruitmen', /Rekru/],
        ['/laporan', /Laporan/],
    ],
    karyawan: [
        ['/me/dashboard', /Selamat (Pagi|Siang|Sore|Malam), Siti/],
        ['/absensi/saya', /Absensi/],
        ['/cuti-izin', /Cuti/],
        ['/lembur', /Lembur/],
        ['/gaji/slip-saya', /Slip/],
        ['/penilaian-kinerja', /Penilaian Kinerja/],
        ['/profil-saya', /Profil/],
        ['/notifikasi', /Notifikasi/],
    ],
};

for (const role of Object.keys(pages) as RoleKey[]) {
    test(`${role}: halaman utama tampil tanpa error console dan tanpa overflow horizontal`, async ({
        page,
    }, info) => {
        const errors = watchConsole(page);
        await login(page, role);

        for (const [path, heading] of pages[role]) {
            const response = await page.goto(path);
            expect.soft(response?.status(), `${path} status`).toBeLessThan(400);
            await expect(page.locator('main h1').first()).toHaveText(heading);
            await page.waitForLoadState('networkidle');
            await expectNoHorizontalOverflow(page, info);
        }

        expect(errors, errors.join('\n')).toEqual([]);
    });
}

test('menu sidebar bisa dibuka dan dipakai di layar kecil', async ({
    page,
}, info) => {
    test.skip(
        (info.project.use.viewport?.width ?? 1366) >= 768,
        'Khusus layar ponsel',
    );
    await login(page, 'karyawan');

    await page.getByRole('button', { name: /toggle sidebar/i }).click();
    await page.getByRole('link', { name: 'Cuti/Izin' }).click();
    await page.waitForURL('**/cuti-izin');
    await expect(page.locator('main h1').first()).toHaveText(/Cuti/);
});

test('halaman yang tidak diizinkan menampilkan 403 HUMANIX, bukan error mentah', async ({
    page,
}) => {
    await login(page, 'karyawan');
    const response = await page.goto('/audit-log');

    expect(response?.status()).toBe(403);
    await expect(page.getByText('403')).toBeVisible();
    await page.getByRole('link', { name: 'Kembali ke beranda' }).click();
    await page.waitForURL(`**${accounts.karyawan.dashboard}`);
});
