"use client";

import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { GroupMembersResponse } from "@/types/groups";

export function useGroupMembers(groupId: number, enabled = true) {
  return useQuery<GroupMembersResponse>({
    queryKey: ["admin", "groups", groupId, "members"],
    queryFn: () =>
      api.get<GroupMembersResponse>(`/admin/groups/${groupId}/users`),
    enabled,
  });
}
