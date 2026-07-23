"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { InvitationRequest } from "@/types/invitation";
import { toast } from "sonner";

export function useInvite() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (invitationRequest: InvitationRequest) =>
      api.post<string[]>("/admin/user-invite", invitationRequest),

    onSuccess: () => {
      toast.success("Invitation sent successfully");
      queryClient.invalidateQueries({ queryKey: ["users"] });
    },
  });
}
