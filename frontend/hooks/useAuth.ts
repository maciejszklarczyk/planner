'use client';

import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { User } from '@/types/auth';
import { ApiError } from '@/lib/api';

export function useAuth() {
    const { data: user, isLoading, error } = useQuery<User | null>({
        queryKey: ['auth', 'me'],
        queryFn: async () => {
            try {
                return await api.get<User>('/auth/me');
            } catch (error) {
                if (error instanceof ApiError && error.status === 401) {
                    return null;
                }
                throw error;
            }
        },
        retry: false,
    });

    return {
        user,
        isAuthenticated: !!user,
        isLoading,
    };
}