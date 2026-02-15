'use client'

import {UsersTable} from "@/components/users/UsersTable";
import {columns} from "@/components/users/UsersTableColumn";
import {useAuth} from "@/hooks/useAuth";
import {GroupsTable} from "@/components/users/GroupsTable";
import {groupColumns} from "@/components/users/GroupsTableColumn";

export function AdminUserSettingsWrapper() {
    const {user, isLoading} = useAuth();

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-10">
                <div className="h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-orange-600"/>
            </div>
        );
    }

    const isAdmin = user?.roles?.includes('ROLE_ADMIN');

    if (!isAdmin) {
        return (
            <div className="flex flex-col items-center justify-center py-20">
                <h2 className="text-2xl font-bold mb-4">Brak dostępu</h2>
                <p className="text-gray-500">Nie masz uprawnień administratora, aby wyświetlić tę stronę.</p>
            </div>
        );
    }

    return (
        <div>
            <div className="container mx-auto py-10">
                <div className="mb-6">
                    <h2 className="text-2xl font-bold">Zarządzanie użytkownikami</h2>
                    <p className="text-gray-500">Lista wszystkich użytkowników w systemie</p>
                </div>
                <UsersTable columns={columns}/>
            </div>
            <div className="container mx-auto py-10">
                <div className="mb-6">
                    <h2 className="text-2xl font-bold">Zarządzanie Grupami</h2>
                    <p className="text-gray-500">Lista wszystkich grup w systemie</p>
                </div>
                <GroupsTable columns={groupColumns}/>
            </div>
        </div>
    );
}
