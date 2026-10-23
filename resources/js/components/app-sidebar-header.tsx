import { usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { GlobalSearch } from '@/components/global-search';
import { NotificationBell } from '@/components/notification-bell';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';

export function AppSidebarHeader() {
    const { auth } = usePage().props;
    const getInitials = useInitials();

    return (
        <header className="sticky top-0 isolate z-20 flex h-[72px] shrink-0 items-center gap-3 border-b border-border bg-surface px-4 md:px-6">
            {/*
              Hiasan "X" HUMANIX di pojok kanan atas, di belakang lonceng & kartu profil.
              Dipotong oleh wadahnya sendiri (bukan header) agar dropdown pencarian tidak ikut terpotong.
            */}
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 -z-10 overflow-hidden"
            >
                <img
                    src="/images/x-kanan.webp"
                    alt=""
                    width={544}
                    height={372}
                    className="absolute -top-12 right-0 h-[260px] w-auto max-w-none select-none"
                />
            </div>
            <SidebarTrigger className="-ml-1 text-text-muted" />

            <GlobalSearch />

            <div className="ml-auto flex items-center gap-2 md:gap-4">
                <NotificationBell />

                <DropdownMenu>
                    <DropdownMenuTrigger
                        className="flex items-center gap-3 rounded-control border border-border bg-surface py-1.5 pr-3 pl-1.5 text-left hover:bg-accent focus-visible:ring-2 focus-visible:ring-primary-bright focus-visible:outline-none"
                        data-test="profile-menu-button"
                    >
                        <Avatar className="size-9 overflow-hidden rounded-full">
                            <AvatarImage
                                src={auth.user.avatar}
                                alt={auth.user.name}
                            />
                            <AvatarFallback className="bg-secondary text-sm font-semibold text-primary">
                                {getInitials(auth.user.name)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="hidden leading-tight md:block">
                            <p className="max-w-40 truncate text-sm font-semibold text-navy">
                                {auth.user.name}
                            </p>
                            <p className="text-xs text-text-muted">
                                {auth.roleLabel ?? 'Pengguna'}
                            </p>
                        </div>
                        <ChevronDown className="hidden size-4 text-text-muted md:block" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="min-w-56 rounded-card"
                        align="end"
                    >
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
