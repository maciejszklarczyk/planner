"use client";

import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { FriendRequestsResponse } from "@/types/friends";

export function useFriendRequests() {
  return useQuery<FriendRequestsResponse>({
    queryKey: ["friends", "requests"],
    queryFn: () => api.get<FriendRequestsResponse>("/friend-requests"),
  });
}
