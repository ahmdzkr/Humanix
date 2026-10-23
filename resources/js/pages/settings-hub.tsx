import { Head, Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Briefcase,
    Building2,
    CalendarClock,
    CalendarOff,
    ChevronRight,
    Network,
    SlidersHorizontal,
} from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';

type Item = {
    key:
        | 'system'
        | 'departments'
        | 'positions'
        | 'structure'
        | 'schedules'
        | 'holidays';
    title: string;
    description: string;
    href: string;
};

const icons: Record<Item['key'], LucideIcon> = {
    system: SlidersHorizontal,
    departments: Building2,
    positions: Briefcase,
    structure: Network,
    schedules: CalendarClock,
    holidays: CalendarOff,
};

export default function SettingsHub({ items }: { items: Item[] }) {
    return (
        <AppLayout>
            <Head title="Pengaturan" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Pengaturan"
                    description="Atur data master organisasi HUMANIX."
                />
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {items.map((item) => {
                        const Icon = icons[item.key];

                        return (
                            <Link
                                key={item.key}
                                href={item.href}
                                prefetch
                                className="group flex items-start gap-4 rounded-card border border-border bg-surface p-5 transition hover:border-primary/40 hover:shadow-[0_8px_24px_-12px_rgba(0,87,255,0.35)]"
                            >
                                <span className="flex size-11 shrink-0 items-center justify-center rounded-control bg-primary/10 text-primary">
                                    <Icon className="size-5" />
                                </span>
                                <span className="flex-1">
                                    <span className="block font-semibold text-navy">
                                        {item.title}
                                    </span>
                                    <span className="mt-1 block text-sm text-text-muted">
                                        {item.description}
                                    </span>
                                </span>
                                <ChevronRight className="mt-1 size-4 text-text-muted transition group-hover:translate-x-0.5 group-hover:text-primary" />
                            </Link>
                        );
                    })}
                </div>
            </div>
        </AppLayout>
    );
}
