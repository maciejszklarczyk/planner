"use client";

import { useState } from "react";
import { Search } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
} from "@/components/ui/input-group";
import { cn } from "@/lib/utils";
import { useSearchFriendCandidates } from "@/hooks/useSearchFriendCandidates";
import { useSendFriendRequest } from "@/hooks/useSendFriendRequest";
import { UserSearchResult } from "@/types/friends";

function initials(name: string) {
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);
}

const AVATAR_COLORS = [
  "bg-primary/10 text-primary",
  "bg-secondary text-secondary-foreground",
  "bg-accent text-accent-foreground",
  "bg-muted text-muted-foreground",
  "bg-primary/20 text-primary",
];

function avatarColor(id: number) {
  return AVATAR_COLORS[id % AVATAR_COLORS.length];
}

export function SendFriendRequestDialog({
  trigger,
}: {
  trigger: React.ReactNode;
}) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState("");

  const { data, isLoading } = useSearchFriendCandidates(search, open);
  const { mutate: sendRequest, isPending } = useSendFriendRequest();

  const results = data?.data ?? [];

  const handleSelect = (candidate: UserSearchResult) => {
    sendRequest(candidate.email, {
      onSuccess: () => {
        setOpen(false);
        setSearch("");
      },
    });
  };

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        setOpen(next);
        if (!next) {
          setSearch("");
        }
      }}
    >
      <DialogTrigger asChild>{trigger}</DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Zaproś znajomego</DialogTitle>
          <DialogDescription>
            Wyszukaj osobę po adresie email, aby wysłać jej zaproszenie do
            znajomych.
          </DialogDescription>
        </DialogHeader>

        <InputGroup>
          <InputGroupAddon align="inline-start">
            <Search />
          </InputGroupAddon>
          <InputGroupInput
            placeholder="Szukaj po adresie email..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            autoFocus
          />
        </InputGroup>

        <div className="flex flex-col gap-1 max-h-72 overflow-y-auto">
          {search.length > 0 && search.length < 2 && (
            <p className="text-xs text-muted-foreground py-4 text-center">
              Wpisz co najmniej 2 znaki, aby wyszukać.
            </p>
          )}

          {isLoading && search.length >= 2 && (
            <p className="text-xs text-muted-foreground py-4 text-center">
              Szukam...
            </p>
          )}

          {!isLoading && search.length >= 2 && results.length === 0 && (
            <p className="text-xs text-muted-foreground py-4 text-center">
              Nie znaleziono nikogo pasującego do &quot;{search}&quot;.
            </p>
          )}

          {results.map((candidate) => (
            <button
              key={candidate.id}
              type="button"
              disabled={isPending}
              onClick={() => handleSelect(candidate)}
              className="flex items-center gap-3 rounded-md p-2 text-left hover:bg-accent disabled:opacity-50 disabled:pointer-events-none"
            >
              <Avatar className="size-9 shrink-0">
                <AvatarFallback
                  className={cn(
                    "text-sm font-semibold",
                    avatarColor(candidate.id),
                  )}
                >
                  {initials(candidate.name)}
                </AvatarFallback>
              </Avatar>
              <div className="min-w-0 flex-1">
                <p className="text-sm font-medium truncate">{candidate.name}</p>
                <p className="text-xs text-muted-foreground truncate">
                  {candidate.email}
                </p>
              </div>
            </button>
          ))}
        </div>
      </DialogContent>
    </Dialog>
  );
}
