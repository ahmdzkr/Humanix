// Helper format Indonesia. Waktu dari server disimpan UTC dan
// selalu ditampilkan dalam zona Asia/Jakarta (WIB).
export const DISPLAY_TIMEZONE = 'Asia/Jakarta';

type DateInput = Date | string | number;

const toDate = (value: DateInput): Date =>
    value instanceof Date ? value : new Date(value);

/** Rabu, 7 Oktober 2026 */
export function formatTanggal(value: DateInput): string {
    return new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: DISPLAY_TIMEZONE,
    }).format(toDate(value));
}

/** 08:00 WIB */
export function formatJam(value: DateInput): string {
    const jam = new Intl.DateTimeFormat('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone: DISPLAY_TIMEZONE,
    })
        .format(toDate(value))
        .replace('.', ':');

    return `${jam} WIB`;
}

/** Rp 650.000.000 (pembulatan ke rupiah terdekat) */
export function formatRupiah(value: number | string): string {
    const angka = Math.round(Number(value) || 0);
    const absolut = new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 0,
    }).format(Math.abs(angka));

    return `${angka < 0 ? '-' : ''}Rp ${absolut}`;
}

/** Selamat Pagi / Siang / Sore / Malam berdasarkan jam WIB */
export function sapaanWaktu(value: DateInput = new Date()): string {
    const jam =
        Number(
            new Intl.DateTimeFormat('en-US', {
                hour: 'numeric',
                hour12: false,
                timeZone: DISPLAY_TIMEZONE,
            }).format(toDate(value)),
        ) % 24;

    if (jam < 11) {
        return 'Selamat Pagi';
    }

    if (jam < 15) {
        return 'Selamat Siang';
    }

    if (jam < 18) {
        return 'Selamat Sore';
    }

    return 'Selamat Malam';
}

/**
 * Rupiah dari string desimal server (mis. "10452312.14") tanpa melewati float untuk perhitungan:
 * Rp 10.452.312,14 — sen hanya ditampilkan bila bukan nol.
 */
export function formatUang(value: string | number | null | undefined): string {
    const raw = String(value ?? '0');
    const negative = raw.trim().startsWith('-');
    const [intPart, fracPart = ''] = raw.replace('-', '').split('.');
    const grouped = (intPart.replace(/^0+(?=\d)/, '') || '0').replace(
        /\B(?=(\d{3})+(?!\d))/g,
        '.',
    );
    const cents = fracPart.padEnd(2, '0').slice(0, 2);

    return `${negative ? '-' : ''}Rp\u00A0${grouped}${cents !== '00' ? `,${cents}` : ''}`;
}
