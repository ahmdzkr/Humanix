import { Head, Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import CareerLayout from '@/layouts/career-layout';
import { index } from '@/routes/careers';

export default function CareerThanks({
    company,
    title,
}: {
    company: string;
    title: string;
}) {
    return (
        <CareerLayout company={company}>
            <Head title="Lamaran terkirim" />
            <div className="mx-auto flex max-w-lg flex-col items-center rounded-card border border-border bg-surface p-10 text-center">
                <CheckCircle2 className="size-14 text-success" />
                <h1 className="mt-4 text-2xl font-bold text-navy">
                    Lamaran terkirim
                </h1>
                <p className="mt-2 text-sm text-text-muted">
                    Terima kasih telah melamar posisi <strong>{title}</strong>.
                    Tim HRD akan menghubungi Anda melalui email atau telepon
                    bila Anda lolos ke tahap berikutnya.
                </p>
                <Button
                    asChild
                    variant="outline"
                    className="mt-6 rounded-control"
                >
                    <Link href={index.url()}>Lihat lowongan lain</Link>
                </Button>
            </div>
        </CareerLayout>
    );
}
