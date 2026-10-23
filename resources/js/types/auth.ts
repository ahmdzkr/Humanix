export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type RoleName = 'Administrator' | 'HRD' | 'Manager' | 'Karyawan';

export type Auth = {
    user: User;
    /** Role utama pengguna (null jika belum diberi role). */
    roleLabel: RoleName | null;
    /** URL dashboard sesuai role. */
    dashboardUrl: string | null;
    /** Akun terhubung dengan data karyawan (punya halaman Profil Saya). */
    hasEmployee: boolean;
    /** Seluruh permission pengguna (spatie). */
    permissions: string[];
};

export type Flash = {
    success?: string | null;
    error?: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
