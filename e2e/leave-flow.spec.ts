import { expect, test } from '@playwright/test';
import { login, watchConsole } from './helpers';

/** Selasa dua minggu ke depan (hari kerja, jauh dari data seed). */
function futureTuesday(): string {
    const d = new Date();
    d.setDate(d.getDate() + 14 + ((2 - d.getDay() + 7) % 7));

    return d.toLocaleDateString('sv-SE', { timeZone: 'Asia/Jakarta' });
}

test('alur cuti lintas role: Karyawan mengajukan → atasan langsung menyetujui → HRD menyetujui → Karyawan diberi tahu', async ({
    page,
    context,
}, info) => {
    test.skip(
        info.project.name !== 'w1366',
        'Alur data dijalankan sekali (lebar 1366) agar tidak bentrok',
    );
    const errors = watchConsole(page);
    const date = futureTuesday();

    // 1. Karyawan mengajukan cuti tahunan 1 hari.
    await login(page, 'karyawan');
    await page.goto('/cuti-izin');
    await page.getByRole('button', { name: 'Ajukan Cuti/Izin' }).click();
    const dialog = page.getByRole('dialog');
    await dialog
        .locator('#leave_type_id')
        .selectOption({ label: 'Cuti Tahunan' });
    await dialog.locator('#start_date').fill(date);
    await dialog.locator('#end_date').fill(date);
    await dialog.locator('#reason').fill('Acara keluarga (uji E2E)');
    await dialog.getByRole('button', { name: 'Kirim pengajuan' }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByText('Menunggu').first()).toBeVisible();

    // 2. Atasan langsung (Agus, role Karyawan) membuka lewat notifikasi lalu menyetujui.
    await context.clearCookies();
    await login(page, 'supervisor');
    await page.goto('/notifikasi');
    await page
        .getByRole('button', {
            name: /Pengajuan Cuti\/Izin menunggu persetujuan/,
        })
        .first()
        .click();
    await page.waitForURL(/\/cuti-izin\/\d+$/);
    await page.getByRole('button', { name: 'Setujui' }).click();
    await expect(page.getByText(/disetujui/i).first()).toBeVisible();

    // 3. HRD membuka lewat notifikasi di lonceng lalu menyetujui.
    await context.clearCookies();
    await login(page, 'hrd');
    await page
        .getByRole('button', { name: /Notifikasi, \d+ belum dibaca/ })
        .click();
    await page
        .getByRole('menuitem', {
            name: /Pengajuan Cuti\/Izin menunggu persetujuan/,
        })
        .first()
        .click();
    await page.waitForURL(/\/cuti-izin\/\d+$/);
    await page.getByRole('button', { name: 'Setujui' }).click();
    await expect(page.getByText('Disetujui').first()).toBeVisible();

    // 4. Karyawan menerima notifikasi "disetujui".
    await context.clearCookies();
    await login(page, 'karyawan');
    await page.goto('/notifikasi');
    await expect(
        page.getByText('Pengajuan Cuti/Izin disetujui').first(),
    ).toBeVisible();

    expect(errors, errors.join('\n')).toEqual([]);
});
