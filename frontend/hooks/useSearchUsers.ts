'use client';

import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { UsersResponse } from '@/types/api';

export function useSearchUsers(search: string, excludeGroupId: number, enabled = true) {
    const params = new URLSearchParams({ search, excludeGroupId: String(excludeGroupId) });

    return useQuery<UsersResponse>({
        queryKey: ['admin', 'users', 'search', search, 'excludeGroup', excludeGroupId],
        queryFn: () => api.get<UsersResponse>(`/admin/users?${params}`),
        enabled: enabled && search.length >= 2,
    });
}
