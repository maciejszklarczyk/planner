'use client';

import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useRouter, useSearchParams } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { toast } from 'sonner';
import { useLogin } from '@/hooks/useLogin';

const loginSchema = z.object({
    email: z.string().email('Nieprawidłowy email'),
    password: z.string().min(1, 'Hasło wymagane'),
});

type LoginFormData = z.infer<typeof loginSchema>;

export function LoginForm() {
    const router = useRouter();
    const searchParams = useSearchParams();
    const { mutate: login, isPending } = useLogin();

    const { register, handleSubmit, formState: { errors } } =
        useForm<LoginFormData>({
            resolver: zodResolver(loginSchema),
        });

    const onSubmit = (data: LoginFormData) => {
        login(data, {
            onSuccess: () => {
                toast.success('Zalogowano', {
                    description: 'Witaj z powrotem!',
                });
                const redirect = searchParams.get('redirect') || '/trips';
                router.push(redirect);
            },
            onError: () => {
                toast.error('Błąd logowania', {
                    description: 'Nieprawidłowy email lub hasło',
                });
            },
        });
    };

    return (
        <Card className="w-full max-w-md">
            <CardHeader>
                <CardTitle>Logowanie</CardTitle>
                <CardDescription>Zaloguj się do TripPlanner</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            {...register('email')}
                            disabled={isPending}
                        />
                        {errors.email && (
                            <p className="text-sm text-red-500">{errors.email.message}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="password">Hasło</Label>
                        <Input
                            id="password"
                            type="password"
                            {...register('password')}
                            disabled={isPending}
                        />
                        {errors.password && (
                            <p className="text-sm text-red-500">{errors.password.message}</p>
                        )}
                    </div>

                    <Button type="submit" className="w-full" disabled={isPending}>
                        {isPending ? 'Logowanie...' : 'Zaloguj się'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}