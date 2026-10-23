import { Link, router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    categoryIcon,
    openNotification,
    waktuRelatif,
} from '@/lib/notifications';
import { cn } from '@/lib/utils';
import { index, readAll } from '@/routes/notifications';

/** Lonceng header: jumlah belum dibaca + 5 notifikasi terbaru. */
export function NotificationBell() {
    const { notificationCount, latestNotifications } = usePage().props;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                aria-label={
                    notificationCount > 0
                        ? `Notifikasi, ${notificationCount} belum dibaca`
                        : 'Notifikasi'
                }
                className="relative flex size-11 items-center justify-center rounded-control border border-border bg-surface text-navy hover:bg-accent focus-visible:ring-2 focus-visible:ring-primary-bright focus-visible:outline-none"
            >
                <Bell className="size-5" />
                {notificationCount > 0 && (
                    <span className="absolute -top-1 -right-1 flex min-w-5 items-center justify-center rounded-full bg-danger px-1 text-[10px] leading-5 font-bold text-white">
                        {notificationCount > 99 ? '99+' : notificationCount}
                    </span>
                )}
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-[min(22rem,calc(100vw-2rem))] rounded-card p-0"
            >
                <div className="flex items-center justify-between border-b border-border px-4 py-3">
                    <p className="font-semibold text-navy">Notifikasi</p>
                    {notificationCount > 0 && (
                        <button
                            type="button"
                            onClick={() =>
                                router.post(
                                    readAll.url(),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            className="flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                        >
                            <CheckCheck className="size-3.5" />
                            Tandai semua dibaca
                        </button>
                    )}
                </div>
                {latestNotifications.length === 0 ? (
                    <p className="px-4 py-8 text-center text-sm text-text-muted">
                        Belum ada notifikasi.
                    </p>
                ) : (
                    <div className="max-h-96 overflow-y-auto py-1">
                        {latestNotifications.map((n) => {
                            const Icon = categoryIcon[n.category] ?? Bell;

                            return (
                                <DropdownMenuItem
                                    key={n.id}
                                    onSelect={() => openNotification(n)}
                                    className={cn(
                                        'flex cursor-pointer items-start gap-3 rounded-none px-4 py-3',
                                        !n.read && 'bg-primary/5',
                                    )}
                                >
                                    <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <Icon className="size-4" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-center gap-2">
                                            <span
                                                className={cn(
                                                    'truncate text-sm text-navy',
                                                    !n.read && 'font-semibold',
                                                )}
                                            >
                                                {n.title}
                                            </span>
                                            {!n.read && (
                                                <span className="size-2 shrink-0 rounded-full bg-primary">
                                                    <span className="sr-only">
                                                        Belum dibaca
                                                    </span>
                                                </span>
                                            )}
                                        </span>
                                        <span className="line-clamp-2 text-xs text-text-muted">
                                            {n.body}
                                        </span>
                                        <span className="text-[11px] text-text-muted">
                                            {waktuRelatif(n.at)}
                                        </span>
                                    </span>
                                </DropdownMenuItem>
                            );
                        })}
                    </div>
                )}
                <Link
                    href={index.url()}
                    className="block border-t border-border px-4 py-3 text-center text-sm font-medium text-primary hover:bg-accent"
                >
                    Lihat semua notifikasi
                </Link>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
