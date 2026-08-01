"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { getApiErrorMessage } from "@/lib/apiErrors";
import { toast } from "sonner";

export function useCancelFriendRequest() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.post(`/friend-requests/${id}/cancel`),
    onSuccess: () => {
      toast.success("Zaproszenie cofnięte");
      queryClient.invalidateQueries({ queryKey: ["friends"] });
      queryClient.invalidateQueries({ queryKey: ["friends", "requests"] });
    },
    onError: (error) => {
      toast.error("Błąd", {
        description: getApiErrorMessage(error, "Nie udało się cofnąć zaproszenia."),
      });
    },
  });
}
