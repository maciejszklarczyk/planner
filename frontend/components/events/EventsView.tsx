"use client";

import { useState } from "react";
import Link from "next/link";
import {
  Calendar,
  MapPin,
  Users,
  Clock,
  Plus,
  CalendarClock,
  CalendarCheck,
  Star,
} from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { cn } from "@/lib/utils";
import { Event, EventsResponse, EventStatus } from "@/types/events";
import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";

type Filter = "upcoming" | "past" | "all";

const EVENTS_PER_PAGE = 3;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function getStatus(event: Event, now: Date): EventStatus {
  const start = new Date(event.startDate);
  const end = new Date(event.endDate);
  const cutoff = new Date(end);
  cutoff.setDate(cutoff.getDate() + 7);

  if (now >= start && now <= end) return "live";
  if (now > end && now <= cutoff) return "recent";
  if (now < start) return "upcoming";
  return "ended";
}

function daysUntil(dateStr: string, now: Date): number {
  const diff = new Date(dateStr).getTime() - now.getTime();
  return Math.ceil(diff / (1000 * 60 * 60 * 24));
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString("pl-PL", {
    day: "numeric",
    month: "long",
    year: "numeric",
  });
}

// ---------------------------------------------------------------------------
// SpotlightCard — hero card for active/recent or next upcoming event
// ---------------------------------------------------------------------------
function SpotlightCard({
  event,
  status,
  now,
}: {
  event: Event;
  status: EventStatus;
  now: Date;
}) {
  const isActive = status === "live" || status === "recent";
  const days = isActive ? null : daysUntil(event.startDate, now);

  return (
    <Card className="overflow-hidden p-0 gap-0">
      {/* Dark banner — rounded corners come from Card's rounded-xl + overflow-hidden */}
      <div
        className={cn("px-5 py-5", isActive ? "bg-zinc-900" : "bg-slate-800")}
      >
        <div className="flex items-center gap-2 mb-2.5">
          {isActive ? (
            <>
              {status === "live" ? (
                <span className="relative flex size-2 shrink-0">
                  <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75" />
                  <span className="relative inline-flex size-2 rounded-full bg-green-500" />
                </span>
              ) : (
                <span className="size-2 shrink-0 rounded-full bg-red-500" />
              )}
              <span className="text-[10px] font-semibold uppercase tracking-widest text-zinc-400">
                {status === "live" ? "Trwa teraz" : "Ostatnio zakończone"}
              </span>
            </>
          ) : (
            <>
              <span className="size-2 shrink-0 rounded-full bg-blue-400" />
              <span className="text-[10px] font-semibold uppercase tracking-widest text-blue-300">
                Następne
              </span>
            </>
          )}
        </div>
        <h2 className="text-lg font-bold text-white leading-snug">
          {event.name}
        </h2>
      </div>

      {/* Body */}
      <div className="px-5 py-4 flex flex-col gap-3">
        <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
          <span className="flex items-center gap-1.5">
            <Calendar className="size-3.5 opacity-60 shrink-0" />
            {formatDate(event.startDate)}
            {event.startDate !== event.endDate &&
              ` – ${formatDate(event.endDate)}`}
          </span>
          <span className="flex items-center gap-1.5">
            <MapPin className="size-3.5 opacity-60 shrink-0" />
            {event.location}
          </span>
          <span className="flex items-center gap-1.5">
            <Users className="size-3.5 opacity-60 shrink-0" />
            {event.attendees} uczestników
          </span>
        </div>

        <div className="flex items-center justify-between flex-wrap gap-2">
          <div className="flex flex-wrap gap-1.5">
            <Badge variant="secondary">{event.category}</Badge>
            {isActive ? (
              <Badge
                variant="outline"
                className="border-green-200 bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-400 dark:border-green-800"
              >
                {status === "live" ? "Na żywo" : "Ostatnie 7 dni"}
              </Badge>
            ) : (
              <span className="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-400 dark:border-blue-800">
                <Clock className="size-3 shrink-0" />
                za {days} {days === 1 ? "dzień" : "dni"}
              </span>
            )}
          </div>
          <div className="flex gap-2">
            <Button variant="outline" size="sm">
              Szczegóły
            </Button>
            <Button size="sm">Edytuj</Button>
          </div>
        </div>
      </div>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// EventCard — compact grid tile
// ---------------------------------------------------------------------------
function EventCard({ event }: { event: Event }) {
  return (
    <Card className="p-4 gap-0 cursor-pointer transition-shadow hover:shadow-md">
      <div className="flex flex-col gap-2.5">
        <div>
          <p className="font-semibold text-sm truncate">{event.name}</p>
          <div className="flex flex-col gap-0.5 mt-1 text-xs text-muted-foreground">
            <span className="flex items-center gap-1.5">
              <Calendar className="size-3 opacity-60 shrink-0" />
              {formatDate(event.startDate)}
            </span>
            <span className="flex items-center gap-1.5">
              <MapPin className="size-3 opacity-60 shrink-0" />
              {event.location}
            </span>
          </div>
        </div>
        <div className="flex items-center justify-between">
          <Badge variant="secondary" className="text-[11px]">
            {event.category}
          </Badge>
          <span className="text-[11px] text-muted-foreground flex items-center gap-1">
            <Users className="size-3 opacity-60 shrink-0" />
            {event.attendees}
          </span>
        </div>
      </div>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// StatsRow — placeholder stats, values come from API later
// ---------------------------------------------------------------------------
function StatsRow({ upcomingCount }: { upcomingCount: number }) {
  const stats = [
    { label: "Nadchodzące", value: upcomingCount, icon: CalendarClock },
    { label: "Zakończone", value: "—", icon: CalendarCheck },
    { label: "Twoje", value: "—", icon: Star },
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
// EventsView — main page component
// ---------------------------------------------------------------------------
const GRID_LABELS: Record<Filter, string> = {
  upcoming: "Nadchodzące",
  past: "Minione",
  all: "Wszystkie",
};

export function EventsView() {
  const [page, setPage] = useState(1);
  const [filter, setFilter] = useState<Filter>("upcoming");
  const now = new Date();

  const { data: events = [], isLoading } = useQuery<
    EventsResponse,
    Error,
    Event[]
  >({
    queryKey: ["events"],
    queryFn: () => api.get<EventsResponse>("/events"),
    select: (r) => r.data,
  });

  if (isLoading)
    return (
      <div className="flex flex-col gap-8">
        <div className="flex items-center justify-between">
          <Skeleton className="h-8 w-48" />
          <Skeleton className="h-9 w-36" />
        </div>
        <div className="flex flex-col sm:flex-row gap-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-20 flex-1" />
          ))}
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Skeleton className="h-44" />
          <Skeleton className="h-44" />
        </div>
        <Skeleton className="h-9 w-64" />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-28" />
          ))}
        </div>
      </div>
    );

  const withStatus = events.map((e) => ({
    event: e,
    status: getStatus(e, now),
  }));

  const activeEntry =
    withStatus.find((e) => e.status === "live") ??
    withStatus.find((e) => e.status === "recent");
  const nextEntry = withStatus.find((e) => e.status === "upcoming");

  const hasSpotlight = activeEntry || nextEntry;

  const gridEvents = (() => {
    if (filter === "upcoming") {
      return withStatus
        .filter(
          (e) => e.status === "upcoming" && e.event.id !== nextEntry?.event.id,
        )
        .map((e) => e.event);
    }
    if (filter === "past") {
      return withStatus
        .filter((e) => e.status === "ended" || e.status === "recent")
        .map((e) => e.event);
    }
    return withStatus
      .filter(
        (e) =>
          e.event.id !== activeEntry?.event.id &&
          e.event.id !== nextEntry?.event.id,
      )
      .map((e) => e.event);
  })();

  const totalPages = Math.ceil(gridEvents.length / EVENTS_PER_PAGE);
  const pagedEvents = gridEvents.slice(
    (page - 1) * EVENTS_PER_PAGE,
    page * EVENTS_PER_PAGE,
  );

  const upcomingCount = withStatus.filter(
    (e) => e.status === "upcoming",
  ).length;

  const handleFilterChange = (value: string) => {
    setFilter(value as Filter);
    setPage(1);
  };

  return (
    <div className="flex flex-col gap-8">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h1 className="text-2xl font-bold tracking-tight">Twoje wydarzenia</h1>
        <Button asChild>
          <Link href="/events/new">
            <Plus data-icon="inline-start" />
            Nowe wydarzenie
          </Link>
        </Button>
      </div>

      {/* Stats */}
      <StatsRow upcomingCount={upcomingCount} />

      {/* Spotlight — always visible, not affected by filter */}
      {hasSpotlight && (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          {activeEntry && (
            <SpotlightCard
              event={activeEntry.event}
              status={activeEntry.status}
              now={now}
            />
          )}
          {nextEntry && (
            <SpotlightCard
              event={nextEntry.event}
              status={nextEntry.status}
              now={now}
            />
          )}
        </div>
      )}

      {/* Filter tabs */}
      <Tabs value={filter} onValueChange={handleFilterChange}>
        <TabsList>
          <TabsTrigger value="upcoming">Nadchodzące</TabsTrigger>
          <TabsTrigger value="past">Minione</TabsTrigger>
          <TabsTrigger value="all">Wszystkie</TabsTrigger>
        </TabsList>
      </Tabs>

      {/* Grid */}
      {pagedEvents.length > 0 && (
        <div className="flex flex-col gap-3">
          <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
            {GRID_LABELS[filter]}
          </p>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {pagedEvents.map((event) => (
              <EventCard key={event.id} event={event} />
            ))}
          </div>

          {totalPages > 1 && (
            <div className="flex items-center justify-center gap-1 pt-4">
              <Button
                variant="outline"
                size="icon"
                disabled={page === 1}
                onClick={() => setPage((p) => p - 1)}
              >
                ‹
              </Button>
              {Array.from({ length: totalPages }).map((_, i) => (
                <Button
                  key={i}
                  variant={page === i + 1 ? "default" : "outline"}
                  size="icon"
                  onClick={() => setPage(i + 1)}
                >
                  {i + 1}
                </Button>
              ))}
              <Button
                variant="outline"
                size="icon"
                disabled={page === totalPages}
                onClick={() => setPage((p) => p + 1)}
              >
                ›
              </Button>
            </div>
          )}
        </div>
      )}

      {/* Empty state */}
      {pagedEvents.length === 0 && (
        <div className="flex flex-col items-center justify-center py-20 text-muted-foreground gap-2">
          <p className="text-sm">Brak wydarzeń. Utwórz pierwsze!</p>
        </div>
      )}
    </div>
  );
}
