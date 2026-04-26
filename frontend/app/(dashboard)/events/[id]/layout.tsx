'use client'

import { use } from 'react'
import { usePathname, useRouter } from 'next/navigation'
import { ArrowLeft, Calendar, MapPin, Users } from 'lucide-react'
import Link from 'next/link'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Separator } from '@/components/ui/separator'

// Minimal mock — replace with API fetch when backend ready
const MOCK_EVENTS: Record<string, { name: string; startDate: string; location: string; category: string; attendees: number }> = {
    '1': { name: 'Konferencja Tech 2025', startDate: '2026-04-19', location: 'Centrum Kongresowe, Warszawa', category: 'Technologia', attendees: 120 },
    '2': { name: 'Warsztaty UX Design', startDate: '2026-04-24', location: 'Studio Kreatywne, Kraków', category: 'Design', attendees: 30 },
    '3': { name: 'Hackathon AI 2025', startDate: '2026-05-22', location: 'Hub Innowacji, Wrocław', category: 'AI/ML', attendees: 80 },
    '4': { name: 'Meetup React Warsaw', startDate: '2026-06-01', location: 'Coworking Space, Warszawa', category: 'Frontend', attendees: 50 },
    '5': { name: 'DevOps Summit', startDate: '2026-06-10', location: 'Hotel Marriott, Gdańsk', category: 'DevOps', attendees: 200 },
}

const TABS = [
    { value: 'overview', label: 'Przegląd' },
    { value: 'attendees', label: 'Uczestnicy' },
    { value: 'schedule', label: 'Harmonogram' },
    { value: 'budget', label: 'Budżet' },
] as const

function formatDate(dateStr: string) {
    return new Date(dateStr).toLocaleDateString('pl-PL', { day: 'numeric', month: 'long', year: 'numeric' })
}

export default function EventDetailLayout({
    children,
    params,
}: {
    children: React.ReactNode
    params: Promise<{ id: string }>
}) {
    const { id } = use(params)
    const pathname = usePathname()
    const router = useRouter()

    const event = MOCK_EVENTS[id]
    const activeTab = pathname.split('/').pop() ?? 'overview'

    return (
        <div className="flex flex-col gap-6">
            {/* Back + header */}
            <div className="flex flex-col gap-4">
                <Button variant="ghost" size="sm" className="-ml-2 w-fit" asChild>
                    <Link href="/events">
                        <ArrowLeft data-icon="inline-start" />
                        Wszystkie wydarzenia
                    </Link>
                </Button>

                <div className="flex items-start justify-between gap-4 flex-wrap">
                    <div className="flex flex-col gap-2">
                        <h1 className="text-2xl font-bold tracking-tight">
                            {event?.name ?? `Wydarzenie #${id}`}
                        </h1>
                        {event && (
                            <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                                <span className="flex items-center gap-1.5">
                                    <Calendar className="size-3.5 opacity-60 shrink-0" />
                                    {formatDate(event.startDate)}
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
                        )}
                    </div>
                    {event && (
                        <div className="flex items-center gap-2">
                            <Badge variant="secondary">{event.category}</Badge>
                            <Button variant="outline" size="sm">Edytuj</Button>
                        </div>
                    )}
                </div>
            </div>

            <Separator />

            {/* Section tabs */}
            <Tabs value={activeTab} onValueChange={v => router.push(`/events/${id}/${v}`)}>
                <TabsList>
                    {TABS.map(tab => (
                        <TabsTrigger key={tab.value} value={tab.value}>
                            {tab.label}
                        </TabsTrigger>
                    ))}
                </TabsList>
            </Tabs>

            {/* Section content */}
            {children}
        </div>
    )
}
