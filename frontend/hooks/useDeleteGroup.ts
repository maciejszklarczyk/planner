'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { toast } from 'sonner';

export function useDeleteGroup() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (groupId: number) =>
            api.delete(`/admin/groups/${groupId}`),
        onSuccess: () => {
            toast.success('Grupa usunięta', {
                description: 'Grupa została pomyślnie usunięta',
            });
            queryClient.invalidateQueries({ queryKey: ['admin', 'groups'] });
        },
        onError: () => {
            toast.error('Błąd', {
                description: 'Nie udało się usunąć grupy',
            });
        },
    });
}
