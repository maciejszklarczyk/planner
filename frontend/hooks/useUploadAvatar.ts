'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { toast } from 'sonner';

interface UseUploadAvatarOptions {
    onSuccess?: (avatar: string) => void;
}

export function useUploadAvatar({ onSuccess }: UseUploadAvatarOptions = {}) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (file: File) => {
            const formData = new FormData();
            formData.append('avatar', file);
            return api.postFormData<{ avatar: string }>('/user/avatar', formData);
        },
        onSuccess: (data) => {
            toast.success('Avatar zaktualizowany');
            queryClient.invalidateQueries({ queryKey: ['auth', 'me'] });
            onSuccess?.(data.avatar);
        },
        onError: () => {
            toast.error('Błąd', { description: 'Nie udało się przesłać avatara' });
        },
    });
}

interface UseDeleteAvatarOptions {
    onSuccess?: () => void;
}

export function useDeleteAvatar({ onSuccess }: UseDeleteAvatarOptions = {}) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: () => api.delete<{ avatar: null }>('/user/avatar'),
        onSuccess: () => {
            toast.success('Avatar usunięty');
            queryClient.invalidateQueries({ queryKey: ['auth', 'me'] });
            onSuccess?.();
        },
        onError: () => {
            toast.error('Błąd', { description: 'Nie udało się usunąć avatara' });
        },
    });
}
