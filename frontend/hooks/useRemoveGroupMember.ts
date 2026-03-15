'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { toast } from 'sonner';

export function useRemoveGroupMember(groupId: number) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (userId: number) =>
            api.delete(`/admin/groups/${groupId}/users/${userId}`),
        onSuccess: () => {
            toast.success('Użytkownik usunięty', {
                description: 'Użytkownik został usunięty z grupy',
            });
            queryClient.invalidateQueries({ queryKey: ['admin', 'groups', groupId, 'members'] });
            queryClient.invalidateQueries({ queryKey: ['admin', 'groups'] });
        },
        onError: () => {
            toast.error('Błąd', {
                description: 'Nie udało się usunąć użytkownika z grupy',
            });
        },
    });
}
