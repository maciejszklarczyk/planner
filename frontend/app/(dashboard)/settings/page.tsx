import type {Metadata} from 'next'
import {SettingsTabs} from "@/components/settings/SettingsTabs";

export const metadata: Metadata = {
    title: 'Ustawienia',
}

export default function SettingsPage() {
    return (
        <div>
            <SettingsTabs/>
        </div>
    );
}
