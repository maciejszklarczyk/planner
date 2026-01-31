import { LogoutButton } from './LogoutButton';
import type { User } from '@/types/auth';
import {DarkModeToggle} from "@/components/layout/DarkModeToggle";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"

interface DashboardHeaderProps {
    user: User;
}

export function DashboardHeader({ user }: DashboardHeaderProps) {
    return (
        <header className="border-b">
            <div className="container mx-auto flex h-16 items-center justify-between px-4">
                <h1 className="text-xl font-bold">EventPlanner</h1>
                <div className="flex items-center gap-4">
                    <Avatar>
                        <AvatarImage src="https://github.com/shadcn.png" />
                        <AvatarFallback>CN</AvatarFallback>
                    </Avatar>
                    <span className="text-sm text-gray-600">{user.email}</span>
                    <DarkModeToggle />
                    <LogoutButton />
                </div>
            </div>
        </header>
    );
}