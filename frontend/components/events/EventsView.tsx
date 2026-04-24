'use client'

import { useState } from 'react'
import Link from 'next/link'
import { Calendar, MapPin, Users, Clock, Plus } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { cn } from '@/lib/utils'
import type { Event, EventStatus } from '@/types/events'

// ---------------------------------------------------------------------------
// Mock data — replace with API query when backend is ready
// ---------------------------------------------------------------------------
const MOCK_EVENTS: Event[] = [
    { id: 1, name: 'Konferencja Tech 2025', startDate: '2026-04-19', endDate: '2026-04-21', location: 'Centrum Kongresowe, Warszawa', attendees: 120, category: 'Technologia' },
    { id: 2, name: 'Warsztaty UX Design', startDate: '2026-04-24', endDate: '2026-04-24', location: 'Studio Kreatywne, Kraków', attendees: 30, category: 'Design' },
    { id: 3, name: 'Hackathon AI 2025', startDate: '2026-05-22', endDate: '2026-05-23', location: 'Hub Innowacji, Wrocław', attendees: 80, category: 'AI/ML' },
    { id: 4, name: 'Meetup React Warsaw', startDate: '2026-06-01', endDate: '2026-06-01', location: 'Coworking Space, Warszawa', attendees: 50, category: 'Frontend' },
    { id: 5, name: 'DevOps Summit', startDate: '2026-06-10', endDate: '2026-06-11', location: 'Hotel Marriott, Gdańsk', attendees: 200, category: 'DevOps' },
    { id: 6, name: 'Scrum Master Camp', startDate: '2026-06-14', endDate: '2026-06-14', location: 'Centrum Biznesu, Łódź', attendees: 45, category: 'Agile' },
    { id: 7, name: 'Cloud Native Day', startDate: '2026-06-20', endDate: '2026-06-20', location: 'Centrum Konferencyjne, Poznań', attendees: 150, category: 'Cloud' },
    { id: 8, name: 'Product Design Sprint', startDate: '2026-06-28', endDate: '2026-07-01', location: 'Startup Hub, Warszawa', attendees: 25, category: 'Product' },
]

const EVENTS_PER_PAGE = 6

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function getStatus(event: Event, now: Date): EventStatus {
    const start = new Date(event.startDate)
    const end = new Date(event.endDate)
    const cutoff = new Date(end)
    cutoff.setDate(cutoff.getDate() + 7)

    if (now >= start && now <= end) return 'live'
    if (now > end && now <= cutoff) return 'recent'
    if (now < start) return 'upcoming'
    return 'ended'
}

function daysUntil(dateStr: string, now: Date): number {
    const diff = new Date(dateStr).getTime() - now.getTime()
    return Math.ceil(diff / (1000 * 60 * 60 * 24))
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('pl-PL', { day: 'numeric', month: 'long', year: 'numeric' })
}

// ---------------------------------------------------------------------------
// SpotlightCard — hero card for active/recent or next upcoming event
// ---------------------------------------------------------------------------
function SpotlightCard({ event, status, now }: { event: Event; status: EventStatus; now: Date }) {
    const isActive = status === 'live' || status === 'recent'
    const days = isActive ? null : daysUntil(event.startDate, now)

    return (
        <Card className="overflow-hidden p-0 gap-0">
            {/* Dark banner — rounded corners come from Card's rounded-xl + overflow-hidden */}
            <div className={cn(
                'px-5 py-5',
                isActive ? 'bg-zinc-900' : 'bg-slate-800'
            )}>
                <div className="flex items-center gap-2 mb-2.5">
                    {isActive ? (
                        <>
                            <span className="relative flex size-2 shrink-0">
                                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75" />
                                <span className="relative inline-flex size-2 rounded-full bg-green-500" />
                            </span>
                            <span className="text-[10px] font-semibold uppercase tracking-widest text-zinc-400">
                                {status === 'live' ? 'Trwa teraz' : 'Ostatnio zakończone'}
                            </span>
                        </>
                    ) : (
                        <>
                            <span className="size-2 shrink-0 rounded-full bg-blue-400" />
                            <span className="text-[10px] font-semibold uppercase tracking-widest text-blue-300">Następne</span>
                        </>
                    )}
                </div>
                <h2 className="text-lg font-bold text-white leading-snug">{event.name}</h2>
            </div>

            {/* Body */}
            <div className="px-5 py-4 flex flex-col gap-3">
                <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                    <span className="flex items-center gap-1.5">
                        <Calendar className="size-3.5 opacity-60 shrink-0" />
                        {formatDate(event.startDate)}
                        {event.startDate !== event.endDate && ` – ${formatDate(event.endDate)}`}
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
                                {status === 'live' ? 'Na żywo' : 'Ostatnie 7 dni'}
                            </Badge>
                        ) : (
                            <span className="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-400 dark:border-blue-800">
                                <Clock className="size-3 shrink-0" />
                                za {days} {days === 1 ? 'dzień' : 'dni'}
                            </span>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm">Szczegóły</Button>
                        <Button size="sm">Edytuj</Button>
                    </div>
                </div>
            </div>
        </Card>
    )
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
                    <Badge variant="secondary" className="text-[11px]">{event.category}</Badge>
                    <span className="text-[11px] text-muted-foreground flex items-center gap-1">
                        <Users className="size-3 opacity-60 shrink-0" />
                        {event.attendees}
                    </span>
                </div>
            </div>
        </Card>
    )
}

// ---------------------------------------------------------------------------
// EventsView — main page component
// ---------------------------------------------------------------------------
export function EventsView() {
    const [page, setPage] = useState(1)
    const now = new Date()

    const withStatus = MOCK_EVENTS.map(e => ({ event: e, status: getStatus(e, now) }))

    const activeEntry = withStatus.find(e => e.status === 'live') ?? withStatus.find(e => e.status === 'recent')
    const nextEntry = withStatus.find(e => e.status === 'upcoming')

    const gridEvents = withStatus
        .filter(e => e.status === 'upcoming' && e.event.id !== nextEntry?.event.id)
        .map(e => e.event)

    const totalPages = Math.ceil(gridEvents.length / EVENTS_PER_PAGE)
    const pagedEvents = gridEvents.slice((page - 1) * EVENTS_PER_PAGE, page * EVENTS_PER_PAGE)

    const hasSpotlight = activeEntry || nextEntry

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

            {/* Spotlight */}
            {hasSpotlight && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    {activeEntry && (
                        <SpotlightCard event={activeEntry.event} status={activeEntry.status} now={now} />
                    )}
                    {nextEntry && (
                        <SpotlightCard event={nextEntry.event} status={nextEntry.status} now={now} />
                    )}
                </div>
            )}

            {/* Upcoming grid */}
            {pagedEvents.length > 0 && (
                <div className="flex flex-col gap-3">
                    <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                        Nadchodzące
                    </p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        {pagedEvents.map(event => (
                            <EventCard key={event.id} event={event} />
                        ))}
                    </div>

                    {totalPages > 1 && (
                        <div className="flex items-center justify-center gap-1 pt-4">
                            <Button
                                variant="outline"
                                size="icon"
                                disabled={page === 1}
                                onClick={() => setPage(p => p - 1)}
                            >
                                ‹
                            </Button>
                            {Array.from({ length: totalPages }).map((_, i) => (
                                <Button
                                    key={i}
                                    variant={page === i + 1 ? 'default' : 'outline'}
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
                                onClick={() => setPage(p => p + 1)}
                            >
                                ›
                            </Button>
                        </div>
                    )}
                </div>
            )}

            {/* Empty state */}
            {!hasSpotlight && pagedEvents.length === 0 && (
                <div className="flex flex-col items-center justify-center py-20 text-muted-foreground gap-2">
                    <p className="text-sm">Brak wydarzeń. Utwórz pierwsze!</p>
                </div>
            )}
        </div>
    )
}
