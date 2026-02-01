import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem,
} from "@/components/ui/sidebar"
import type {User} from "@/types/auth";
import {NavUser} from "@/components/sidebar/NavUser";
import {GalleryVerticalEnd} from "lucide-react";
import {NavSettings} from "@/components/sidebar/NavSettings";
import Link from "next/link";
import {ComponentProps} from "react";

type SidebarProps = ComponentProps<typeof Sidebar> & {
    user: User;
};

export function AppSidebar({user, ...props}: SidebarProps) {
    return (
        <Sidebar collapsible="offcanvas" {...props}>
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/events">
                                <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-lg">
                                    <GalleryVerticalEnd className="size-4" />
                                </div>
                                <div className="flex flex-col gap-0.5 leading-none">
                                    <span className="font-medium">EventPlanner4000</span>
                                    <span className="">v0.0.1-pre-alfa</span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <SidebarGroup/>
                <NavSettings className="mt-auto" />
            </SidebarContent>
            <SidebarFooter>
                <NavUser user={user}/>
            </SidebarFooter>
        </Sidebar>
    )
}