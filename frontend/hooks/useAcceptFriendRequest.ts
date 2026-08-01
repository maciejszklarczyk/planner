"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { getApiErrorMessage } from "@/lib/apiErrors";
import { toast } from "sonner";

export function useAcceptFriendRequest() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.post(`/friend-requests/${id}/accept`),
    onSuccess: () => {
      toast.success("Zaproszenie zaakceptowane", {
        description: "Nowy znajomy został dodany do listy.",
      });
      queryClient.invalidateQueries({ queryKey: ["friends"] });
      queryClient.invalidateQueries({ queryKey: ["friends", "requests"] });
    },
    onError: (error) => {
      toast.error("Błąd", {
        description: getApiErrorMessage(error, "Nie udało się zaakceptować zaproszenia."),
      });
    },
  });
}
