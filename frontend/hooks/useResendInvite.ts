"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { InvitationRequest } from "@/types/invitation";
import { toast } from "sonner";

export function useResendInvite() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (invitationRequest: InvitationRequest) =>
      api.post<string[]>("/admin/user-invite/resend", invitationRequest),

    onSuccess: () => {
      toast.success("Zaproszenie wysłane ponownie", {
        description:
          "Poprzednie zaproszenie zostało unieważnione. Nowe jest ważne przez 24h.",
      });
      queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
    },
    onError: () => {
      toast.error("Błąd", {
        description: "Nie udało się wysłać zaproszenia ponownie.",
      });
    },
  });
}
