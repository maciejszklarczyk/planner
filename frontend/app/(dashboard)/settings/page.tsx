import type {Metadata} from 'next'
import {AdminUserSettingsWrapper} from "@/components/users/AdminUserSettingsWrapper";
import CurrentUserEditForm from "@/components/forms/CurrentUserEditForm";

export const metadata: Metadata = {
    title: 'Ustawienia',
}

export default function SettingsPage() {
    return (
        <div>
            <div className="mb-6 flex items-center justify-between">
            </div>
            <CurrentUserEditForm />
            <AdminUserSettingsWrapper/>
        </div>
    );
}