'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { toast } from 'sonner';

export function useDeleteUser() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (userId: number) =>
            api.delete(`/user/${userId}`),
        onSuccess: () => {
            toast.success('Użytkownik usunięty', {
                description: 'Użytkownik został pomyślnie usunięty',
            });
            queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });
        },
        onError: () => {
            toast.error('Błąd', {
                description: 'Nie udało się usunąć użytkownika',
            });
        },
    });
}
