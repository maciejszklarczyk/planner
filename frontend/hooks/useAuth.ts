"use client";

import { useQuery } from "@tanstack/react-query";
import { useEffect, useRef } from "react";
import { toast } from "sonner";
import { api } from "@/lib/api";
import type { User } from "@/types/auth";
import { ApiError } from "@/lib/api";

export function useAuth() {
  const hasShownSessionExpiredToast = useRef(false);

  const { data: user, isLoading } = useQuery<User | null>({
    queryKey: ["auth", "me"],
    queryFn: async () => {
      try {
        return await api.get<User>("/auth/me");
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
          return null;
        }
        throw error;
      }
    },
    retry: false,
    // Auto-refresh session every 10 minutes to prevent timeout while user is active
    // Default Symfony session timeout is 24 minutes
    refetchInterval: 10 * 60 * 1000, // 10 minutes
    refetchIntervalInBackground: false, // Only when tab is active
  });

  // Show session expired toast when user becomes unauthenticated
  useEffect(() => {
    if (!isLoading && user === null && !hasShownSessionExpiredToast.current) {
      // Only show if we're not on login or set-password page (to avoid toast on initial load)
      if (
        typeof window !== "undefined" &&
        !window.location.pathname.startsWith("/login") &&
        !window.location.pathname.startsWith("/set-password")
      ) {
        toast.error("Sesja wygasła", {
          description: "Zaloguj się ponownie, aby kontynuować",
        });
        hasShownSessionExpiredToast.current = true;
      }
    }

    // Reset flag when user logs in
    if (user !== null) {
      hasShownSessionExpiredToast.current = false;
    }
  }, [user, isLoading]);

  return {
    user,
    isAuthenticated: !!user,
    isLoading,
  };
}
