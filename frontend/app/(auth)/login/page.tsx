import { Suspense } from 'react';
import { LoginForm } from '@/components/forms/LoginForm';

export default function LoginPage() {
    return (
        <div className="flex min-h-screen items-center justify-center">
            <Suspense fallback={<div>Ładowanie...</div>}>
                <LoginForm />
            </Suspense>
        </div>
    );
}