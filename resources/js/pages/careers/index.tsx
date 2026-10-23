import { Head, Link } from '@inertiajs/react';
import { Briefcase, CalendarClock, MapPin } from 'lucide-react';
import { StatusBadge } from '@/components/status-badge';
import CareerLayout from '@/layouts/career-layout';
import { formatRange } from '@/lib/requests';
import { show } from '@/routes/careers';

type Vacancy = {
    slug: string;
    title: string;
    department: string | null;
    employment_status: string;
    location: string | null;
    deadline: string;
};

export default function Careers({
    company,
    vacancies,
}: {
    company: string;
    vacancies: Vacancy[];
}) {
    return (
        <CareerLayout company={company}>
            <Head title="Lowongan Kerja" />
            <section className="relative overflow-hidden rounded-card bg-navy p-8 text-white">
                <div className="pointer-events-none absolute -top-20 -right-20 size-72 rounded-full bg-gradient-to-br from-primary to-cyan opacity-60 blur-2xl" />
                <h1 className="relative text-3xl font-bold">
                    Bergabung dengan {company}
                </h1>
                <p className="relative mt-2 max-w-xl text-white/80">
                    Temukan posisi yang sesuai dan kirim lamaran Anda secara
                    online.
                </p>
            </section>

            <h2 className="mt-8 mb-4 text-lg font-semibold text-navy">
                {vacancies.length} lowongan dibuka
            </h2>

            {vacancies.length === 0 ? (
                <p className="rounded-card border border-border bg-surface p-10 text-center text-sm text-text-muted">
                    Belum ada lowongan yang dibuka saat ini. Silakan kembali
                    lagi nanti.
                </p>
            ) : (
                <ul className="grid gap-4 md:grid-cols-2">
                    {vacancies.map((v) => (
                        <li key={v.slug}>
                            <Link
                                href={show.url(v.slug)}
                                className="block h-full rounded-card border border-border bg-surface p-5 transition hover:border-primary/40 hover:shadow-[0_8px_24px_-12px_rgba(0,87,255,0.35)]"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <h3 className="font-semibold text-navy">
                                        {v.title}
                                    </h3>
                                    <StatusBadge tone="info">
                                        {v.employment_status}
                                    </StatusBadge>
                                </div>
                                <ul className="mt-3 space-y-1 text-sm text-text-muted">
                                    <li className="flex items-center gap-2">
                                        <Briefcase className="size-4" />
                                        {v.department ?? '—'}
                                    </li>
                                    {v.location && (
                                        <li className="flex items-center gap-2">
                                            <MapPin className="size-4" />
                                            {v.location}
                                        </li>
                                    )}
                                    <li className="flex items-center gap-2">
                                        <CalendarClock className="size-4" />
                                        Lamar sebelum{' '}
                                        {formatRange(v.deadline, v.deadline)}
                                    </li>
                                </ul>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </CareerLayout>
    );
}
