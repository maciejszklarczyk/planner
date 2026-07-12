'use client'

import {useAuth} from "@/hooks/useAuth";
import CurrentUserEditForm from "@/components/forms/CurrentUserEditForm";
import {UsersTable} from "@/components/users/UsersTable";
import {columns} from "@/components/users/UsersTableColumn";
import {GroupsTable} from "@/components/users/GroupsTable";
import {groupColumns} from "@/components/users/GroupsTableColumn";
import {InviteUserDialog} from "@/components/users/InviteUserDialog";
import {SectionHeader, SettingCard, SettingRow} from "@/components/settings/SettingsPrimitives";

function Toggle({disabled}: {disabled?: boolean}) {
    return (
        <button
            role="switch"
            aria-checked={false}
            disabled={disabled}
            className="relative inline-flex h-5 w-9 items-center rounded-full bg-muted transition-colors disabled:cursor-not-allowed disabled:opacity-40"
        >
            <span className="translate-x-1 inline-block h-3.5 w-3.5 rounded-full bg-background shadow-sm transition-transform"/>
        </button>
    );
}

const SAMPLE_LOGS = [
    {date: '2026-04-07 14:32', event: 'Dodano wydarzenie „Spotkanie zespołu"'},
    {date: '2026-04-06 09:15', event: 'Dodano do grupy „Projekt Alpha"'},
    {date: '2026-04-04 11:48', event: 'Dodano wydarzenie „Przegląd kodu"'},
    {date: '2026-03-28 16:03', event: 'Usunięto z grupy „Testerzy"'},
    {date: '2026-03-20 08:00', event: 'Rejestracja konta'},
];

export function SettingsTabs() {
    const {user, isLoading} = useAuth();

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-10">
                <div className="h-8 w-8 animate-spin rounded-full border-4 border-border border-t-primary"/>
            </div>
        );
    }

    const isAdmin = user?.roles?.includes('ROLE_ADMIN');

    return (
        <div className="max-w-2xl flex flex-col gap-8">
            <div>
                <h1 className="text-lg font-semibold">Ustawienia</h1>
                <p className="text-xs text-muted-foreground mt-1">Zarządzaj kontem i preferencjami</p>
            </div>

            <CurrentUserEditForm/>

            <section>
                <SectionHeader>Powiadomienia</SectionHeader>
                <SettingCard>
                    <SettingRow label="Powiadomienia push" hint="Funkcja w przygotowaniu">
                        <Toggle disabled/>
                    </SettingRow>
                    <SettingRow label="Powiadomienia e-mail" hint="Funkcja w przygotowaniu">
                        <Toggle disabled/>
                    </SettingRow>
                </SettingCard>
            </section>

            {isAdmin && (
                <section>
                    <SectionHeader>Użytkownicy</SectionHeader>
                    <div className="flex flex-col gap-4">
                        <div className="flex justify-end">
                            <InviteUserDialog/>
                        </div>
                        <UsersTable columns={columns}/>
                    </div>
                </section>
            )}

            {isAdmin && (
                <section>
                    <SectionHeader>Grupy</SectionHeader>
                    <GroupsTable columns={groupColumns}/>
                </section>
            )}

            <section>
                <SectionHeader>Historia aktywności</SectionHeader>
                <p className="text-sm text-muted-foreground mb-3">
                    Funkcja w przygotowaniu — poniżej przykładowe dane.
                </p>
                <SettingCard>
                    {SAMPLE_LOGS.map((log, i) => (
                        <SettingRow key={i} label={log.event} hint={log.date}/>
                    ))}
                </SettingCard>
            </section>
        </div>
    );
}
