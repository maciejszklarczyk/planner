"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { toast } from "sonner";

export function useAddGroupMember(groupId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (userId: number) =>
      api.post(`/admin/groups/${groupId}/users`, { userId }),
    onSuccess: () => {
      toast.success("Użytkownik dodany", {
        description: "Użytkownik został dodany do grupy",
      });
      queryClient.invalidateQueries({
        queryKey: ["admin", "groups", groupId, "members"],
      });
      queryClient.invalidateQueries({ queryKey: ["admin", "groups"] });
    },
    onError: () => {
      toast.error("Błąd", {
        description: "Nie udało się dodać użytkownika do grupy",
      });
    },
  });
}
