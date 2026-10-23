import { Head, Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';

const messages: Record<number, { title: string; description: string }> = {
    403: {
        title: 'Akses ditolak',
        description: 'Anda tidak memiliki izin untuk membuka halaman ini.',
    },
    404: {
        title: 'Halaman tidak ditemukan',
        description: 'Halaman yang Anda cari tidak ada atau sudah dipindahkan.',
    },
    419: {
        title: 'Sesi kedaluwarsa',
        description:
            'Sesi Anda sudah berakhir. Muat ulang halaman lalu coba lagi.',
    },
    500: {
        title: 'Terjadi kesalahan',
        description:
            'Ada masalah di server kami. Silakan coba beberapa saat lagi.',
    },
    503: {
        title: 'Sedang dalam pemeliharaan',
        description:
            'HUMANIX sedang diperbarui. Silakan kembali sebentar lagi.',
    },
};

export default function ErrorPage({ status }: { status: number }) {
    const message = messages[status] ?? messages[500];

    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-background p-6 text-center">
            <Head title={message.title} />
            <AppLogoIcon className="mb-6 size-12" />
            <p className="text-6xl font-extrabold text-primary">{status}</p>
            <h1 className="mt-3 text-2xl font-bold text-navy">
                {message.title}
            </h1>
            <p className="mt-2 max-w-md text-sm text-text-muted">
                {message.description}
            </p>
            <Button asChild className="mt-6 rounded-control">
                <Link href="/">Kembali ke beranda</Link>
            </Button>
        </div>
    );
}
