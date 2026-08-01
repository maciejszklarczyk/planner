"use client";

import { useState } from "react";
import {
  UserPlus,
  Search,
  Users,
  Clock,
  MoreHorizontal,
  UserCheck,
  UserX,
  Mail,
  CheckCheck,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Separator } from "@/components/ui/separator";
import { Skeleton } from "@/components/ui/skeleton";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
} from "@/components/ui/input-group";
import { cn } from "@/lib/utils";
import { useFriends } from "@/hooks/useFriends";
import { useFriendRequests } from "@/hooks/useFriendRequests";
import { useAcceptFriendRequest } from "@/hooks/useAcceptFriendRequest";
import { useDeclineFriendRequest } from "@/hooks/useDeclineFriendRequest";
import { useCancelFriendRequest } from "@/hooks/useCancelFriendRequest";
import { FriendRequestDto, FriendRequestOtherUser } from "@/types/friends";
import { SendFriendRequestDialog } from "@/components/friends/SendFriendRequestDialog";

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------
type Tab = "friends" | "invitations";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function initials(name: string) {
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);
}

// Semantic-token based avatar colors — no raw dark: overrides
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

// ---------------------------------------------------------------------------
// StatsRow
// ---------------------------------------------------------------------------
function StatsRow({
  friendsCount,
  pendingCount,
}: {
  friendsCount: number;
  pendingCount: number;
}) {
  const stats = [
    { label: "Znajomi", value: friendsCount, icon: Users },
    { label: "Oczekujące", value: pendingCount || "—", icon: Clock },
  ];

  return (
    <div className="flex flex-col sm:flex-row gap-3">
      {stats.map(({ label, value, icon: Icon }) => (
        <Card key={label} className="flex-1 px-4 py-3 gap-0">
          <div className="flex items-center justify-between mb-2">
            <span className="text-muted-foreground text-xs">{label}</span>
            <div className="flex size-6 items-center justify-center rounded-md bg-primary/10 shrink-0">
              <Icon className="size-3.5 text-primary shrink-0" />
            </div>
          </div>
          <p className="text-2xl font-bold tracking-tight">{value}</p>
        </Card>
      ))}
    </div>
  );
}

// ---------------------------------------------------------------------------
// FriendCard
// ---------------------------------------------------------------------------
function FriendCard({ friend }: { friend: FriendRequestOtherUser }) {
  return (
    <Card className="p-4 gap-0">
      <div className="flex items-start gap-3">
        <Avatar className="size-10 shrink-0">
          <AvatarFallback
            className={cn("text-sm font-semibold", avatarColor(friend.id))}
          >
            {initials(friend.name)}
          </AvatarFallback>
        </Avatar>

        <div className="min-w-0 flex-1">
          <p className="font-semibold text-sm truncate leading-snug">
            {friend.name}
          </p>
          <p className="text-xs text-muted-foreground truncate">
            {friend.email}
          </p>
        </div>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button
              variant="ghost"
              size="icon"
              className="size-7 shrink-0 -mr-1 -mt-0.5"
            >
              <MoreHorizontal />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuGroup>
              {/* Not implemented — no messaging feature exists in this app. */}
              <DropdownMenuItem disabled title="Wkrótce dostępne">
                <Mail />
                Wyślij wiadomość
              </DropdownMenuItem>
              {/* Not implemented — unfriending is explicitly parked, see roadmap. */}
              <DropdownMenuItem
                disabled
                variant="destructive"
                title="Wkrótce dostępne"
              >
                <UserX />
                Usuń ze znajomych
              </DropdownMenuItem>
            </DropdownMenuGroup>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// PendingReceivedCard
// ---------------------------------------------------------------------------
function PendingReceivedCard({ request }: { request: FriendRequestDto }) {
  const { mutate: accept, isPending: isAccepting } = useAcceptFriendRequest();
  const { mutate: decline, isPending: isDeclining } = useDeclineFriendRequest();
  const isBusy = isAccepting || isDeclining;

  return (
    <Card className="p-4 gap-0">
      <div className="flex items-center gap-3">
        <Avatar className="size-10 shrink-0">
          <AvatarFallback
            className={cn(
              "text-sm font-semibold",
              avatarColor(request.otherUser.id),
            )}
          >
            {initials(request.otherUser.name)}
          </AvatarFallback>
        </Avatar>

        <div className="min-w-0 flex-1">
          <p className="font-semibold text-sm truncate">
            {request.otherUser.name}
          </p>
          <p className="text-xs text-muted-foreground truncate">
            {request.otherUser.email}
          </p>
        </div>
      </div>

      <Separator className="my-3" />

      <div className="flex gap-2">
        <Button
          size="sm"
          className="flex-1"
          disabled={isBusy}
          onClick={() => accept(request.id)}
        >
          <UserCheck data-icon="inline-start" />
          Akceptuj
        </Button>
        <Button
          size="sm"
          variant="outline"
          className="flex-1"
          disabled={isBusy}
          onClick={() => decline(request.id)}
        >
          <UserX data-icon="inline-start" />
          Odrzuć
        </Button>
      </div>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// PendingSentCard
// ---------------------------------------------------------------------------
function PendingSentCard({ request }: { request: FriendRequestDto }) {
  const { mutate: cancel, isPending: isCancelling } = useCancelFriendRequest();

  return (
    <Card className="p-4 gap-0">
      <div className="flex items-center gap-3">
        <Avatar className="size-10 shrink-0">
          <AvatarFallback
            className={cn(
              "text-sm font-semibold",
              avatarColor(request.otherUser.id),
            )}
          >
            {initials(request.otherUser.name)}
          </AvatarFallback>
        </Avatar>

        <div className="min-w-0 flex-1">
          <p className="font-semibold text-sm truncate">
            {request.otherUser.name}
          </p>
          <p className="text-xs text-muted-foreground truncate">
            {request.otherUser.email}
          </p>
          <Badge variant="outline" className="mt-1.5 gap-1">
            <Clock />
            Oczekuje na odpowiedź
          </Badge>
        </div>

        <Button
          size="sm"
          variant="outline"
          className="shrink-0 text-xs"
          disabled={isCancelling}
          onClick={() => cancel(request.id)}
        >
          Cofnij
        </Button>
      </div>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// EmptyState
// ---------------------------------------------------------------------------
function EmptyState({ tab }: { tab: Tab }) {
  const configs: Record<
    Tab,
    { icon: React.ElementType; title: string; description: string }
  > = {
    friends: {
      icon: Users,
      title: "Brak znajomych",
      description: "Zaproś pierwszą osobę, aby wspólnie planować wydarzenia.",
    },
    invitations: {
      icon: CheckCheck,
      title: "Brak zaproszeń",
      description: "Nie masz żadnych oczekujących zaproszeń.",
    },
  };

  const { icon: Icon, title, description } = configs[tab];

  return (
    <div className="flex flex-col items-center justify-center py-20 gap-2">
      <Icon className="size-8 text-muted-foreground opacity-30" />
      <p className="text-sm font-medium">{title}</p>
      <p className="text-xs text-muted-foreground text-center max-w-xs">
        {description}
      </p>
    </div>
  );
}

// ---------------------------------------------------------------------------
// LoadingSkeleton
// ---------------------------------------------------------------------------
function LoadingSkeleton() {
  return (
    <div className="flex flex-col gap-8">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-9 w-40" />
      </div>
      <div className="flex flex-col sm:flex-row gap-3">
        <Skeleton className="h-20 flex-1" />
        <Skeleton className="h-20 flex-1" />
      </div>
      <Skeleton className="h-9 w-64" />
      <Skeleton className="h-9 w-72" />
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <Skeleton className="h-24" />
        <Skeleton className="h-24" />
        <Skeleton className="h-24" />
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// FriendsView
// ---------------------------------------------------------------------------
export function FriendsView() {
  const [tab, setTab] = useState<Tab>("friends");
  const [search, setSearch] = useState("");

  const { data: friendsData, isLoading: isFriendsLoading } = useFriends();
  const { data: requestsData, isLoading: isRequestsLoading } =
    useFriendRequests();

  if (isFriendsLoading || isRequestsLoading) {
    return <LoadingSkeleton />;
  }

  const friends = friendsData?.data ?? [];
  const incoming = requestsData?.incoming ?? [];
  const outgoing = requestsData?.outgoing ?? [];
  const pendingTotal = incoming.length + outgoing.length;

  const filteredFriends = friends.filter(
    (f) =>
      search === "" ||
      f.name.toLowerCase().includes(search.toLowerCase()) ||
      f.email.toLowerCase().includes(search.toLowerCase()),
  );

  return (
    <div className="flex flex-col gap-8">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h1 className="text-2xl font-bold tracking-tight">Znajomi</h1>
        <SendFriendRequestDialog
          trigger={
            <Button>
              <UserPlus data-icon="inline-start" />
              Zaproś znajomego
            </Button>
          }
        />
      </div>

      {/* Stats */}
      <StatsRow friendsCount={friends.length} pendingCount={pendingTotal} />

      {/* Search */}
      <InputGroup>
        <InputGroupAddon align="inline-start">
          <Search />
        </InputGroupAddon>
        <InputGroupInput
          placeholder="Szukaj znajomych..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
      </InputGroup>

      {/* Tabs */}
      <Tabs
        value={tab}
        onValueChange={(v) => {
          setTab(v as Tab);
          setSearch("");
        }}
      >
        <TabsList>
          <TabsTrigger value="friends">Znajomi</TabsTrigger>
          <TabsTrigger value="invitations" className="gap-1.5">
            Zaproszenia
            {pendingTotal > 0 && (
              <Badge
                variant="secondary"
                className="text-[10px] px-1.5 py-0 h-4"
              >
                {pendingTotal}
              </Badge>
            )}
          </TabsTrigger>
        </TabsList>
      </Tabs>

      {/* Content */}
      {tab === "friends" &&
        (filteredFriends.length > 0 ? (
          <div className="flex flex-col gap-3">
            <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
              Twoi znajomi ({filteredFriends.length})
            </p>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              {filteredFriends.map((f) => (
                <FriendCard key={f.id} friend={f} />
              ))}
            </div>
          </div>
        ) : (
          <EmptyState tab="friends" />
        ))}

      {tab === "invitations" && (
        <div className="flex flex-col gap-6">
          {incoming.length > 0 && (
            <div className="flex flex-col gap-3">
              <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                Otrzymane ({incoming.length})
              </p>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                {incoming.map((r) => (
                  <PendingReceivedCard key={r.id} request={r} />
                ))}
              </div>
            </div>
          )}
          {outgoing.length > 0 && (
            <div className="flex flex-col gap-3">
              <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                Wysłane ({outgoing.length})
              </p>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                {outgoing.map((r) => (
                  <PendingSentCard key={r.id} request={r} />
                ))}
              </div>
            </div>
          )}
          {incoming.length === 0 && outgoing.length === 0 && (
            <EmptyState tab="invitations" />
          )}
        </div>
      )}
    </div>
  );
}
