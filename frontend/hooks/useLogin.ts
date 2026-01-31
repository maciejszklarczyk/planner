'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { LoginCredentials, LoginResponse } from '@/types/auth';

export function useLogin() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (credentials: LoginCredentials) =>
            api.post<LoginResponse>('/auth/login', credentials),

        onSuccess: (data) => {
            queryClient.setQueryData(['auth', 'me'], data.user);
        },
    });
}