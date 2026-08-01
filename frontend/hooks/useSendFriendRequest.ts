"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { getApiErrorMessage } from "@/lib/apiErrors";
import { toast } from "sonner";

export function useSendFriendRequest() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (email: string) => api.post("/friend-requests", { email }),
    onSuccess: () => {
      toast.success("Zaproszenie wysłane", {
        description: "Zaproszenie do znajomych zostało wysłane.",
      });
      queryClient.invalidateQueries({ queryKey: ["friends", "requests"] });
    },
    onError: (error) => {
      toast.error("Błąd", {
        description: getApiErrorMessage(error, "Nie udało się wysłać zaproszenia."),
      });
    },
  });
}
