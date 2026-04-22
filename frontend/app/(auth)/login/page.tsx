import { Suspense } from 'react';
import { LoginForm } from '@/components/forms/LoginForm';
import type { Metadata } from 'next'

export const metadata: Metadata = {
    title: 'Logowanie',
}
export default function LoginPage() {
    return (
        <div className="flex min-h-screen items-center justify-center">
            <Suspense fallback={<div>Ładowanie...</div>}>
                <LoginForm />
            </Suspense>
        </div>
    );
}