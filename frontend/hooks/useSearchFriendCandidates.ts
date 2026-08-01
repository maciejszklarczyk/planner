"use client";

import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { UserSearchResponse } from "@/types/friends";

export function useSearchFriendCandidates(search: string, enabled = true) {
  return useQuery<UserSearchResponse>({
    queryKey: ["users", "search", search],
    queryFn: () => api.get<UserSearchResponse>(`/users?search=${encodeURIComponent(search)}`),
    enabled: enabled && search.length >= 2,
  });
}
