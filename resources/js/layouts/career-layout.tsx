import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Toaster } from '@/components/toaster';
import { index as careers } from '@/routes/careers';

/** Tata letak publik halaman karier (tanpa sidebar & tanpa login). */
export default function CareerLayout({
    company,
    children,
}: {
    company: string;
    children: ReactNode;
}) {
    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="border-b border-border bg-surface">
                <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4">
                    <Link
                        href={careers.url()}
                        className="flex items-center gap-3"
                    >
                        <img
                            src="/images/logo-humanix.png"
                            alt="HUMANIX"
                            className="h-10 w-auto"
                        />
                    </Link>
                    <span className="hidden text-sm font-medium text-navy sm:block">
                        Karier di {company}
                    </span>
                </div>
            </header>
            <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
                {children}
            </main>
            <footer className="border-t border-border bg-surface py-6 text-center text-xs text-text-muted">
                © {new Date().getFullYear()} {company} · Data lamaran diproses
                sesuai persetujuan Anda dan hanya untuk keperluan rekrutmen.
            </footer>
            <Toaster />
        </div>
    );
}
