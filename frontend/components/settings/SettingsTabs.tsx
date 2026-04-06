'use client'

import {Tabs, TabsContent, TabsList, TabsTrigger} from "@/components/ui/tabs";
import {useAuth} from "@/hooks/useAuth";
import CurrentUserEditForm from "@/components/forms/CurrentUserEditForm";
import {UsersTable} from "@/components/users/UsersTable";
import {columns} from "@/components/users/UsersTableColumn";
import {GroupsTable} from "@/components/users/GroupsTable";
import {groupColumns} from "@/components/users/GroupsTableColumn";
import {InviteUserDialog} from "@/components/users/InviteUserDialog";
import {useState} from "react";

export function SettingsTabs() {
    const {user, isLoading} = useAuth();
    const [activeTab, setActiveTab] = useState('profile');

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-10">
                <div className="h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-orange-600"/>
            </div>
        );
    }

    const isAdmin = user?.roles?.includes('ROLE_ADMIN');

    return (
        <Tabs defaultValue="profile" onValueChange={setActiveTab} className="w-full flex-col justify-start gap-6">
            <div className="flex justify-between">
                <TabsList variant="line">
                    <TabsTrigger value="profile">Profil</TabsTrigger>
                    {isAdmin && <TabsTrigger value="users">Użytkownicy</TabsTrigger>}
                    {isAdmin && <TabsTrigger value="groups">Grupy</TabsTrigger>}
                    <TabsTrigger value="logs">Logi</TabsTrigger>
                </TabsList>
                {isAdmin && activeTab === 'users' && <InviteUserDialog/>}
            </div>
            <TabsContent value="profile">
                <CurrentUserEditForm/>
            </TabsContent>
            {isAdmin && (
                <TabsContent value="users">
                    <UsersTable columns={columns}/>
                </TabsContent>
            )}
            {isAdmin && (
                <TabsContent value="groups">
                    <GroupsTable columns={groupColumns}/>
                </TabsContent>
            )}
            <TabsContent value="logs">
                Logi
            </TabsContent>
        </Tabs>
    );
}
