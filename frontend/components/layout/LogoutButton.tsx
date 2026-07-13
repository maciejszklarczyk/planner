"use client";

import { Button } from "@/components/ui/button";
import { useLogout } from "@/hooks/useLogout";

export function LogoutButton() {
  const { mutate: logout, isPending } = useLogout();

  return (
    <Button
      variant="outline"
      size="sm"
      onClick={() => logout()}
      disabled={isPending}
    >
      {isPending ? "Wylogowywanie..." : "Wyloguj"}
    </Button>
  );
}
