'use client';

import {useRouter} from 'next/navigation';
import {useAuth} from '@/hooks/useAuth';
import {DashboardHeader} from '@/components/layout/DashboardHeader';
import {SidebarInset, SidebarProvider, SidebarTrigger} from "@/components/ui/sidebar";
import {AppSidebar} from "@/components/layout/AppSidebar";
import {Separator} from "@/components/ui/separator";
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList, BreadcrumbPage,
    BreadcrumbSeparator
} from "@/components/ui/breadcrumb";
import {BreadcrumbHelper} from "@/components/layout/Breadcrumb";
import {DarkModeToggle} from "@/components/layout/DarkModeToggle";

export default function DashboardLayout({
                                            children,
                                        }: {
    children: React.ReactNode;
}) {
    const router = useRouter();
    const {user, isLoading} = useAuth();

    if (isLoading) {
        return (
            <div className="flex h-screen items-center justify-center">
                <div className="h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-orange-600"/>
            </div>
        );
    }

    if (!user) {
        router.push('/login');
        return null;
    }

    return (

        <SidebarProvider>
            <AppSidebar user={user}/>
            <SidebarInset>
                <header
                    className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
                    <div className="flex items-center gap-2 px-4 w-full">
                        <SidebarTrigger className="-ml-1"/>
                        <Separator
                            orientation="vertical"
                            className="mr-2 data-[orientation=vertical]:h-4"
                        />
                        <BreadcrumbHelper homeElement={'Events'}/>
                        <div className="ml-auto">
                            <DarkModeToggle/>
                        </div>
                    </div>
                </header>
                <main className="container mx-auto px-4 py-8">
                    {children}
                </main>
            </SidebarInset>
        </SidebarProvider>
    );
}