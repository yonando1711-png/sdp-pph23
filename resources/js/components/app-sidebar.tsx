import { Link } from '@inertiajs/react';
import { LayoutGrid, UploadCloud, Settings } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import type { NavItem } from '@/types';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    useSidebar,
} from '@/components/ui/sidebar';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
];

const utilitiesNavItems: NavItem[] = [
    {
        title: 'Import Data',
        href: '/dashboard/import',
        icon: UploadCloud,
    },
    {
        title: 'Utilities',
        href: '/dashboard/utilities',
        icon: Settings,
    },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { state } = useSidebar();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className={state === 'collapsed' ? 'p-2 flex items-center justify-center h-16' : 'p-6 border-b border-sidebar-border/30 flex justify-center items-center'}>
                {state === 'collapsed' ? (
                    <Link href="/dashboard" prefetch className="focus:outline-none">
                        <AppLogoIcon className="h-6 w-auto object-contain" />
                    </Link>
                ) : (
                    <Link href="/dashboard" prefetch className="flex flex-col items-center gap-2 pt-2 focus:outline-none w-full">
                        <AppLogoIcon className="h-10 w-auto object-contain" />
                        <span className="text-xs font-semibold tracking-wide text-sidebar-foreground text-center mt-1">
                            PPh 23 Converter
                        </span>
                    </Link>
                )}
            </SidebarHeader>

            <SidebarContent className="flex flex-col gap-4">
                <NavMain items={mainNavItems} label="Platform" />
                <NavMain items={utilitiesNavItems} label="Utilities" />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
