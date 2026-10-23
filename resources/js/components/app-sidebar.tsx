import { Link } from '@inertiajs/react';
import { Headset } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useMainNavItems } from '@/lib/navigation';
import { dashboard } from '@/routes';

export function AppSidebar() {
    const mainNavItems = useMainNavItems();

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader className="px-4 pt-5 pb-4">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="hover:bg-transparent"
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter className="p-4 group-data-[collapsible=icon]:hidden">
                <div className="relative overflow-hidden rounded-card bg-gradient-to-br from-primary to-cyan p-4 text-white">
                    <div className="absolute -top-6 -right-6 size-20 rounded-full bg-white/15" />
                    <div className="mb-3 flex size-9 items-center justify-center rounded-[10px] bg-white/20">
                        <Headset className="size-5" />
                    </div>
                    <p className="text-sm font-semibold">Butuh bantuan?</p>
                    <p className="mt-1 text-xs text-white/80">
                        Hubungi helpdesk HUMANIX untuk kendala sistem.
                    </p>
                </div>
            </SidebarFooter>
        </Sidebar>
    );
}
