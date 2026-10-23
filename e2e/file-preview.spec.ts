import { expect, test } from '@playwright/test';
import { login, watchConsole } from './helpers';

/** File tidak langsung diunduh: tombol membuka modal pratinjau dengan Unduh & Cetak. */
test('karyawan: ID Card dipratinjau di modal (PDF inline), unduhan lewat tombol', async ({
    page,
}) => {
    const errors = watchConsole(page);
    await login(page, 'karyawan');
    await page.goto('/profil-saya');

    await page.getByRole('button', { name: 'Lihat ID Card' }).click();

    const dialog = page.getByRole('dialog');
    await expect(
        dialog.getByRole('heading', { name: 'ID Card Saya' }),
    ).toBeVisible();
    await expect(dialog.locator('iframe')).toHaveAttribute(
        'src',
        /\/profil-saya\/id-card$/,
    );
    await expect(dialog.getByRole('link', { name: 'Unduh' })).toHaveAttribute(
        'href',
        /\/profil-saya\/id-card\?download=1$/,
    );
    await expect(dialog.getByRole('button', { name: 'Cetak' })).toBeEnabled();
    await expect(page).toHaveURL(/\/profil-saya$/);

    expect(errors).toEqual([]);
});

test('HRD: laporan Excel dipratinjau sebagai tabel, .xlsx hanya lewat Unduh', async ({
    page,
}) => {
    const errors = watchConsole(page);
    await login(page, 'hrd');
    await page.goto('/laporan');

    await page.getByRole('button', { name: 'Lihat' }).first().click();

    const dialog = page.getByRole('dialog');
    await expect(
        dialog.getByRole('heading', { name: 'Rekap Cuti & Lembur' }),
    ).toBeVisible();
    await expect(
        dialog.getByTestId('file-preview-body').locator('table, p'),
    ).toBeVisible();
    await expect(dialog.getByRole('link', { name: 'Unduh' })).toHaveAttribute(
        'href',
        /\/laporan\/cuti-lembur\?month=\d{4}-\d{2}$/,
    );
    await expect(page).toHaveURL(/\/laporan/);

    expect(errors).toEqual([]);
});
