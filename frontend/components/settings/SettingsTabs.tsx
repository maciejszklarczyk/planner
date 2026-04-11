'use client'

import {useAuth} from "@/hooks/useAuth";
import CurrentUserEditForm from "@/components/forms/CurrentUserEditForm";
import {UsersTable} from "@/components/users/UsersTable";
import {columns} from "@/components/users/UsersTableColumn";
import {GroupsTable} from "@/components/users/GroupsTable";
import {groupColumns} from "@/components/users/GroupsTableColumn";
import {InviteUserDialog} from "@/components/users/InviteUserDialog";
import {useState} from "react";
import {Button} from "@/components/ui/button";
import {cn} from "@/lib/utils";
import {Separator} from "@/components/ui/separator";

type Tab = 'profile' | 'notifications' | 'users' | 'groups' | 'logs';

export function SettingsTabs() {
    const {user, isLoading} = useAuth();
    const [activeTab, setActiveTab] = useState<Tab>('profile');

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-10">
                <div className="h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-orange-600"/>
            </div>
        );
    }

    const isAdmin = user?.roles?.includes('ROLE_ADMIN');

    const navItems: { value: Tab; label: string; adminOnly?: boolean }[] = [
        {value: 'profile', label: 'Profil'},
        {value: 'notifications', label: 'Powiadomienia'},
        {value: 'users', label: 'Użytkownicy', adminOnly: true},
        {value: 'groups', label: 'Grupy', adminOnly: true},
        {value: 'logs', label: 'Logi'},
    ];

    return (
        <div className="flex gap-8">
            <nav className="w-44 shrink-0 flex flex-col gap-1">
                {navItems
                    .filter(item => !item.adminOnly || isAdmin)
                    .map(item => (
                        <Button
                            key={item.value}
                            variant="ghost"
                            className={cn(
                                'justify-start',
                                activeTab === item.value && 'bg-muted font-medium'
                            )}
                            onClick={() => setActiveTab(item.value)}
                        >
                            {item.label}
                        </Button>
                    ))}
            </nav>
            <div className="flex-1 min-w-0">
                {activeTab === 'profile' && <CurrentUserEditForm/>}
                {isAdmin && activeTab === 'users' && (
                    <div className="flex flex-col gap-4">
                        <div className="flex justify-end">
                            <InviteUserDialog/>
                        </div>
                        <UsersTable columns={columns}/>
                    </div>
                )}
                {isAdmin && activeTab === 'groups' && <GroupsTable columns={groupColumns}/>}
                {activeTab === 'notifications' && <NotificationsPlaceholder/>}
                {activeTab === 'logs' && <LogsPlaceholder/>}
            </div>
        </div>
    );
}

const SAMPLE_LOGS = [
    {date: '2026-04-07 14:32', event: 'Dodano wydarzenie „Spotkanie zespołu"'},
    {date: '2026-04-06 09:15', event: 'Dodano do grupy „Projekt Alpha"'},
    {date: '2026-04-04 11:48', event: 'Dodano wydarzenie „Przegląd kodu"'},
    {date: '2026-03-28 16:03', event: 'Usunięto z grupy „Testerzy"'},
    {date: '2026-03-20 08:00', event: 'Rejestracja konta'},
];

function LogsPlaceholder() {
    return (
        <div>
            <h2 className="text-lg font-semibold mb-4">Historia aktywności</h2>
            <p className="text-sm text-muted-foreground mb-6">Funkcja w przygotowaniu — poniżej przykładowe dane.</p>
            <ul className="flex flex-col divide-y divide-border opacity-50">
                {SAMPLE_LOGS.map((log, i) => (
                    <li key={i} className="flex items-center gap-6 py-3 text-sm">
                        <span className="w-36 shrink-0 text-muted-foreground tabular-nums">{log.date}</span>
                        <span>{log.event}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function NotificationsPlaceholder() {
    return (
        <div className="flex flex-col gap-8">
            <section>
                <h2 className="text-lg font-semibold mb-1">Powiadomienia push</h2>
                <p className="text-sm text-muted-foreground mb-4">Funkcja w przygotowaniu.</p>
                <div className="flex flex-col gap-3 md:w-1/2 opacity-50 pointer-events-none">
                    <label className="flex items-center justify-between">
                        <span className="text-sm">Nowe wydarzenie</span>
                        <input type="checkbox" disabled/>
                    </label>
                    <label className="flex items-center justify-between">
                        <span className="text-sm">Przypomnienie o wydarzeniu</span>
                        <input type="checkbox" disabled/>
                    </label>
                    <label className="flex items-center justify-between">
                        <span className="text-sm">Dodanie do grupy</span>
                        <input type="checkbox" disabled/>
                    </label>
                </div>
            </section>

            <Separator/>

            <section>
                <h2 className="text-lg font-semibold mb-1">Powiadomienia e-mail</h2>
                <p className="text-sm text-muted-foreground mb-4">Funkcja w przygotowaniu.</p>
                <div className="flex flex-col gap-3 md:w-1/2 opacity-50 pointer-events-none">
                    <label className="flex items-center justify-between">
                        <span className="text-sm">Nowe wydarzenie</span>
                        <input type="checkbox" disabled/>
                    </label>
                    <label className="flex items-center justify-between">
                        <span className="text-sm">Przypomnienie o wydarzeniu</span>
                        <input type="checkbox" disabled/>
                    </label>
                    <label className="flex items-center justify-between">
                        <span className="text-sm">Dodanie do grupy</span>
                        <input type="checkbox" disabled/>
                    </label>
                </div>
            </section>
        </div>
    );
}
