'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { toast } from 'sonner';

interface UpdateUserData {
    id: number;
    name: string;
    email: string;
}

interface UseUpdateUserOptions {
    onSuccess?: () => void;
    invalidateKeys?: string[][];
}

export function useUpdateUser({ onSuccess, invalidateKeys = [['admin', 'users']] }: UseUpdateUserOptions = {}) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: UpdateUserData) => api.put('/user', data),
        onSuccess: () => {
            toast.success('Dane zaktualizowane', {
                description: 'Dane użytkownika zostały pomyślnie zaktualizowane',
            });
            invalidateKeys.forEach((key) =>
                queryClient.invalidateQueries({ queryKey: key })
            );
            onSuccess?.();
        },
        onError: () => {
            toast.error('Błąd', {
                description: 'Nie udało się zaktualizować danych użytkownika',
            });
        },
    });
}
