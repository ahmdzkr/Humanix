import { Head, Link, router } from '@inertiajs/react';
import { Bell, CheckCheck, ChevronRight, Inbox } from 'lucide-react';
import { Pagination } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { AppNotification } from '@/lib/notifications';
import {
    categoryIcon,
    openNotification,
    waktuRelatif,
} from '@/lib/notifications';
import { cn } from '@/lib/utils';
import { index, readAll } from '@/routes/notifications';
import type { Paginated } from '@/types';

type Props = {
    notifications: Paginated<AppNotification>;
    filters: { status: 'all' | 'unread' };
    categories: Record<string, string>;
};

export default function Notifications({
    notifications,
    filters,
    categories,
}: Props) {
    const hasUnread = notifications.data.some((n) => !n.read);

    return (
        <AppLayout breadcrumbs={[{ title: 'Notifikasi', href: index.url() }]}>
            <Head title="Notifikasi" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Notifikasi"
                    description="Pemberitahuan pengajuan, persetujuan, slip gaji, rekrutmen, dan penilaian kinerja."
                    actions={
                        <Button
                            variant="outline"
                            className="rounded-control"
                            disabled={!hasUnread && filters.status === 'all'}
                            onClick={() =>
                                router.post(
                                    readAll.url(),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <CheckCheck className="size-4" />
                            Tandai semua dibaca
                        </Button>
                    }
                />

                <div
                    className="inline-flex w-fit rounded-control border border-border bg-surface p-1"
                    role="group"
                    aria-label="Filter notifikasi"
                >
                    {(
                        [
                            ['all', 'Semua'],
                            ['unread', 'Belum dibaca'],
                        ] as const
                    ).map(([value, label]) => (
                        <Link
                            key={value}
                            href={index.url({
                                query: value === 'all' ? {} : { status: value },
                            })}
                            preserveScroll
                            aria-current={
                                filters.status === value ? 'page' : undefined
                            }
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm font-medium',
                                filters.status === value
                                    ? 'bg-primary text-white'
                                    : 'text-text-muted hover:text-navy',
                            )}
                        >
                            {label}
                        </Link>
                    ))}
                </div>

                <div className="rounded-card border border-border bg-surface">
                    {notifications.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center px-6 py-14 text-center">
                            <div className="mb-3 flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <Inbox className="size-6" />
                            </div>
                            <p className="font-semibold text-navy">
                                {filters.status === 'unread'
                                    ? 'Semua notifikasi sudah dibaca'
                                    : 'Belum ada notifikasi'}
                            </p>
                        </div>
                    ) : (
                        <ul className="divide-y divide-border">
                            {notifications.data.map((n) => {
                                const Icon = categoryIcon[n.category] ?? Bell;

                                return (
                                    <li key={n.id}>
                                        <button
                                            type="button"
                                            onClick={() => openNotification(n)}
                                            className={cn(
                                                'flex w-full items-start gap-3 px-4 py-4 text-left hover:bg-background/60 md:px-5',
                                                !n.read && 'bg-primary/5',
                                            )}
                                        >
                                            <span className="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-control bg-primary/10 text-primary">
                                                <Icon className="size-5" />
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="flex flex-wrap items-center gap-x-2 text-xs text-text-muted">
                                                    {categories[n.category] ??
                                                        n.category}
                                                    <span aria-hidden>·</span>
                                                    {waktuRelatif(n.at)}
                                                    {!n.read && (
                                                        <span className="rounded-full bg-primary px-2 py-0.5 text-[10px] font-semibold text-white">
                                                            Baru
                                                        </span>
                                                    )}
                                                </span>
                                                <span
                                                    className={cn(
                                                        'mt-0.5 block text-navy',
                                                        !n.read &&
                                                            'font-semibold',
                                                    )}
                                                >
                                                    {n.title}
                                                </span>
                                                <span className="mt-0.5 block text-sm text-text">
                                                    {n.body}
                                                </span>
                                            </span>
                                            {n.has_url && (
                                                <ChevronRight className="mt-3 size-4 shrink-0 text-text-muted" />
                                            )}
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                    <Pagination rows={notifications} />
                </div>
            </div>
        </AppLayout>
    );
}
