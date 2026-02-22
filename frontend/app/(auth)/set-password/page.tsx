import { Suspense } from 'react';
import {SetPasswordForm} from "@/components/forms/SetPasswordForm";

export default function LoginPage() {
    return (
        <div className="flex min-h-screen items-center justify-center">
            <Suspense fallback={<div>Ładowanie...</div>}>
                <SetPasswordForm />
            </Suspense>
        </div>
    );
}