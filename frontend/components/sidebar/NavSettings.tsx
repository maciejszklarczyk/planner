"use client";

import { IconHelp, IconLogout } from "@tabler/icons-react";

import {
  SidebarGroup,
  SidebarGroupContent,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar";
import Link from "next/link";
import { useLogout } from "@/hooks/useLogout";

export function NavSettings({ ...props }) {
  const { mutate: logout, isPending } = useLogout();

  const navSettings = [
    {
      title: "Get Help",
      url: "#",
      icon: IconHelp,
    },
  ];

  return (
    <SidebarGroup {...props}>
      <SidebarGroupContent>
        <SidebarMenu>
          {navSettings.map((item) => (
            <SidebarMenuItem key={item.title}>
              <SidebarMenuButton asChild>
                <Link href={item.url}>
                  <item.icon />
                  <span>{item.title}</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
          ))}
          <SidebarMenuItem>
            <SidebarMenuButton onClick={() => logout()} disabled={isPending}>
              <IconLogout />
              {isPending ? "Wylogowywanie..." : "Log out"}
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarGroupContent>
    </SidebarGroup>
  );
}
