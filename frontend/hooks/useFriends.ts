"use client";

import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { FriendsResponse } from "@/types/friends";

export function useFriends() {
  return useQuery<FriendsResponse>({
    queryKey: ["friends"],
    queryFn: () => api.get<FriendsResponse>("/friends"),
  });
}
