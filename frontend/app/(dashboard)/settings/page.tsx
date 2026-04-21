import type {Metadata} from 'next'
import {Suspense} from "react";
import {SettingsTabs} from "@/components/settings/SettingsTabs";

export const metadata: Metadata = {
    title: 'Ustawienia',
}

export default function SettingsPage() {
    return (
        <div>
            <Suspense>
                <SettingsTabs/>
            </Suspense>
        </div>
    );
}
