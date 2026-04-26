"use client"

import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from "@/components/ui/avatar"
import {
    SidebarMenu,
    SidebarMenuItem, SidebarSeparator,
} from "@/components/ui/sidebar"
import {User} from "@/types/auth";
import {NavSettings} from "@/components/sidebar/NavSettings";

interface NavUserProps {
    user: User;
}

export function NavUser({user}: NavUserProps) {
    const initials = user.name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)

    return (
        <>
            <NavSettings />
            <SidebarMenu>
                <SidebarSeparator/>
                <SidebarMenuItem>
                    <div className="flex w-full items-center gap-2 px-2 py-1.5 text-sm">
                        <Avatar className="size-8 rounded-lg grayscale">
                            <AvatarImage src={user.avatar} alt={user.name}/>
                            <AvatarFallback className="rounded-lg">{initials}</AvatarFallback>
                        </Avatar>
                        <div className="grid flex-1 text-left text-sm leading-tight">
                            <span className="truncate font-medium">{user.name}</span>
                            <span className="text-muted-foreground truncate text-xs">{user.email}</span>
                        </div>
                    </div>
                </SidebarMenuItem>
            </SidebarMenu>
        </>
    )
}
