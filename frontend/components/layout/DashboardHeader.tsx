import { LogoutButton } from './LogoutButton';
import type { User } from '@/types/auth';

interface DashboardHeaderProps {
    user: User;
}

export function DashboardHeader({ user }: DashboardHeaderProps) {
    return (
        <header className="border-b bg-white">
            <div className="container mx-auto flex h-16 items-center justify-between px-4">
                <h1 className="text-xl font-bold">TripPlanner</h1>
                <div className="flex items-center gap-4">
                    <span className="text-sm text-gray-600">{user.email}</span>
                    <LogoutButton />
                </div>
            </div>
        </header>
    );
}