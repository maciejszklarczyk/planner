"use client"

import {IconCalendarEvent, IconFriends, IconSettings, IconReportMoney} from "@tabler/icons-react"
import {usePathname} from "next/navigation";

import {
    SidebarGroup, SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar"
import {Badge} from "@/components/ui/badge"
import Link from "next/link";

type NavItem = {
    title: string
    url: string
    icon: React.ElementType
    badge?: number
}

export function NavPages({...props}) {
    const pathname = usePathname()

    const navPages: NavItem[] = [
        {
            title: "Events",
            url: "/events",
            icon: IconCalendarEvent,
        },
        {
            title: "Spendings",
            url: "https://split.msolve.it/",
            icon: IconReportMoney,
        },
        {
            title: "Friends",
            url: "/friends",
            icon: IconFriends,
            badge: 0,
        },
        {
            title: "Settings",
            url: "/settings",
            icon: IconSettings,
        },
    ];

    return (
        <SidebarGroup {...props}>
            <SidebarGroupContent>
                <SidebarMenu>
                    {navPages.map((item) => (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton asChild isActive={pathname.startsWith(item.url) && item.url !== '#'}>
                                <Link href={item.url}>
                                    <item.icon />
                                    <span>{item.title}</span>
                                    {item.badge !== undefined && (
                                        <Badge variant="secondary" className="ml-auto text-[10px] px-1.5 py-0">
                                            {item.badge}
                                        </Badge>
                                    )}
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    )
}
