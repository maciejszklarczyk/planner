"use client";

import { useMutation } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { RegisterCradentials } from "@/types/auth";
import { toast } from "sonner";

export function useSetPassword() {
  return useMutation({
    mutationFn: (registerCredentials: RegisterCradentials) =>
      api.post<string[]>("/invitation/complete", registerCredentials),

    onSuccess: () => {
      toast.success("Zarejestrowaned");
    },
  });
}
