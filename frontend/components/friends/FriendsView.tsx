'use client'

import { useState } from 'react'
import {
    UserPlus, Search, Users, CalendarDays, Clock, MoreHorizontal,
    UserCheck, UserX, Mail, CheckCheck
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Separator } from '@/components/ui/separator'
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuGroup,
    DropdownMenuItem, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
    InputGroup, InputGroupAddon, InputGroupInput,
} from '@/components/ui/input-group'
import { cn } from '@/lib/utils'

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------
type FriendStatus = 'active' | 'pending-sent' | 'pending-received'
type Tab = 'friends' | 'invitations' | 'suggestions'

interface Friend {
    id: number
    name: string
    email: string
    mutualEvents: number
    status: FriendStatus
    joinedAt?: string
}

interface Suggestion {
    id: number
    name: string
    email: string
    mutualFriends: number
    mutualEvents: number
}

// ---------------------------------------------------------------------------
// Mock data — replace with API when backend ready
// ---------------------------------------------------------------------------
const MOCK_FRIENDS: Friend[] = [
    { id: 1, name: 'Anna Kowalska', email: 'anna.k@example.com', mutualEvents: 4, status: 'active', joinedAt: '2025-11' },
    { id: 2, name: 'Piotr Wiśniewski', email: 'p.wisniewski@example.com', mutualEvents: 2, status: 'active', joinedAt: '2026-01' },
    { id: 3, name: 'Karolina Nowak', email: 'karolina@example.com', mutualEvents: 7, status: 'active', joinedAt: '2025-09' },
    { id: 4, name: 'Tomasz Lewandowski', email: 'tomek.lew@example.com', mutualEvents: 1, status: 'active', joinedAt: '2026-03' },
    { id: 5, name: 'Marta Zielińska', email: 'marta.z@example.com', mutualEvents: 3, status: 'active', joinedAt: '2026-02' },
]

const MOCK_PENDING_SENT: Friend[] = [
    { id: 10, name: 'Bartosz Szymański', email: 'bartek@example.com', mutualEvents: 0, status: 'pending-sent' },
]

const MOCK_PENDING_RECEIVED: Friend[] = [
    { id: 11, name: 'Ola Dąbrowska', email: 'ola.d@example.com', mutualEvents: 2, status: 'pending-received' },
    { id: 12, name: 'Rafał Jankowski', email: 'rafal.j@example.com', mutualEvents: 0, status: 'pending-received' },
]

const MOCK_SUGGESTIONS: Suggestion[] = [
    { id: 20, name: 'Natalia Wójcik', email: 'natalia.w@example.com', mutualFriends: 3, mutualEvents: 2 },
    { id: 21, name: 'Kamil Kowalczyk', email: 'kamil.k@example.com', mutualFriends: 1, mutualEvents: 5 },
    { id: 22, name: 'Agnieszka Pawlak', email: 'agnieszka.p@example.com', mutualFriends: 2, mutualEvents: 0 },
]

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function initials(name: string) {
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}

// Semantic-token based avatar colors — no raw dark: overrides
const AVATAR_COLORS = [
    'bg-primary/10 text-primary',
    'bg-secondary text-secondary-foreground',
    'bg-accent text-accent-foreground',
    'bg-muted text-muted-foreground',
    'bg-primary/20 text-primary',
]

function avatarColor(id: number) {
    return AVATAR_COLORS[id % AVATAR_COLORS.length]
}

// ---------------------------------------------------------------------------
// StatsRow
// ---------------------------------------------------------------------------
function StatsRow({ friendsCount, pendingCount }: { friendsCount: number; pendingCount: number }) {
    const stats = [
        { label: 'Znajomi', value: friendsCount, icon: Users },
        { label: 'Oczekujące', value: pendingCount || '—', icon: Clock },
        { label: 'Wspólne eventy', value: '—', icon: CalendarDays },
    ]

    return (
        <div className="flex gap-3">
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
    )
}

// ---------------------------------------------------------------------------
// FriendCard
// ---------------------------------------------------------------------------
function FriendCard({ friend }: { friend: Friend }) {
    return (
        <Card className="p-4 gap-0">
            <div className="flex items-start gap-3">
                <Avatar className="size-10 shrink-0">
                    <AvatarFallback className={cn('text-sm font-semibold', avatarColor(friend.id))}>
                        {initials(friend.name)}
                    </AvatarFallback>
                </Avatar>

                <div className="min-w-0 flex-1">
                    <p className="font-semibold text-sm truncate leading-snug">{friend.name}</p>
                    <p className="text-xs text-muted-foreground truncate">{friend.email}</p>
                    <div className="flex items-center gap-1.5 mt-2">
                        <CalendarDays className="size-3 opacity-60 shrink-0" />
                        <span className="text-xs text-muted-foreground">
                            {friend.mutualEvents} wspólnych wydarzeń
                        </span>
                    </div>
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="size-7 shrink-0 -mr-1 -mt-0.5">
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuGroup>
                            <DropdownMenuItem>
                                <Mail />
                                Wyślij wiadomość
                            </DropdownMenuItem>
                            <DropdownMenuItem className="text-destructive focus:text-destructive">
                                <UserX />
                                Usuń ze znajomych
                            </DropdownMenuItem>
                        </DropdownMenuGroup>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </Card>
    )
}

// ---------------------------------------------------------------------------
// PendingReceivedCard
// ---------------------------------------------------------------------------
function PendingReceivedCard({ friend }: { friend: Friend }) {
    return (
        <Card className="p-4 gap-0">
            <div className="flex items-center gap-3">
                <Avatar className="size-10 shrink-0">
                    <AvatarFallback className={cn('text-sm font-semibold', avatarColor(friend.id))}>
                        {initials(friend.name)}
                    </AvatarFallback>
                </Avatar>

                <div className="min-w-0 flex-1">
                    <p className="font-semibold text-sm truncate">{friend.name}</p>
                    <p className="text-xs text-muted-foreground truncate">{friend.email}</p>
                </div>
            </div>

            <Separator className="my-3" />

            <div className="flex gap-2">
                <Button size="sm" className="flex-1">
                    <UserCheck data-icon="inline-start" />
                    Akceptuj
                </Button>
                <Button size="sm" variant="outline" className="flex-1">
                    <UserX data-icon="inline-start" />
                    Odrzuć
                </Button>
            </div>
        </Card>
    )
}

// ---------------------------------------------------------------------------
// PendingSentCard
// ---------------------------------------------------------------------------
function PendingSentCard({ friend }: { friend: Friend }) {
    return (
        <Card className="p-4 gap-0">
            <div className="flex items-center gap-3">
                <Avatar className="size-10 shrink-0">
                    <AvatarFallback className={cn('text-sm font-semibold', avatarColor(friend.id))}>
                        {initials(friend.name)}
                    </AvatarFallback>
                </Avatar>

                <div className="min-w-0 flex-1">
                    <p className="font-semibold text-sm truncate">{friend.name}</p>
                    <p className="text-xs text-muted-foreground truncate">{friend.email}</p>
                    <Badge variant="outline" className="mt-1.5 gap-1">
                        <Clock />
                        Oczekuje na odpowiedź
                    </Badge>
                </div>

                <Button size="sm" variant="outline" className="shrink-0 text-xs">
                    Cofnij
                </Button>
            </div>
        </Card>
    )
}

// ---------------------------------------------------------------------------
// SuggestionCard
// ---------------------------------------------------------------------------
function SuggestionCard({ suggestion }: { suggestion: Suggestion }) {
    return (
        <Card className="p-4 gap-0">
            <div className="flex items-start gap-3">
                <Avatar className="size-10 shrink-0">
                    <AvatarFallback className={cn('text-sm font-semibold', avatarColor(suggestion.id))}>
                        {initials(suggestion.name)}
                    </AvatarFallback>
                </Avatar>

                <div className="min-w-0 flex-1">
                    <p className="font-semibold text-sm truncate">{suggestion.name}</p>
                    <p className="text-xs text-muted-foreground truncate">{suggestion.email}</p>
                    <div className="flex items-center gap-3 mt-2">
                        <span className="text-xs text-muted-foreground flex items-center gap-1">
                            <Users className="size-3 opacity-60 shrink-0" />
                            {suggestion.mutualFriends} wspólnych
                        </span>
                        <span className="text-xs text-muted-foreground flex items-center gap-1">
                            <CalendarDays className="size-3 opacity-60 shrink-0" />
                            {suggestion.mutualEvents} eventów
                        </span>
                    </div>
                </div>

                <Button size="sm" variant="outline" className="shrink-0">
                    <UserPlus data-icon="inline-start" />
                    Dodaj
                </Button>
            </div>
        </Card>
    )
}

// ---------------------------------------------------------------------------
// EmptyState
// ---------------------------------------------------------------------------
function EmptyState({ tab }: { tab: Tab }) {
    const configs: Record<Tab, { icon: React.ElementType; title: string; description: string }> = {
        friends: {
            icon: Users,
            title: 'Brak znajomych',
            description: 'Zaproś pierwszą osobę, aby wspólnie planować wydarzenia.',
        },
        invitations: {
            icon: CheckCheck,
            title: 'Brak zaproszeń',
            description: 'Nie masz żadnych oczekujących zaproszeń.',
        },
        suggestions: {
            icon: UserPlus,
            title: 'Brak sugestii',
            description: 'Dodaj więcej znajomych, by zobaczyć sugestie.',
        },
    }

    const { icon: Icon, title, description } = configs[tab]

    return (
        <div className="flex flex-col items-center justify-center py-20 gap-2">
            <Icon className="size-8 text-muted-foreground opacity-30" />
            <p className="text-sm font-medium">{title}</p>
            <p className="text-xs text-muted-foreground text-center max-w-xs">{description}</p>
        </div>
    )
}

// ---------------------------------------------------------------------------
// FriendsView
// ---------------------------------------------------------------------------
export function FriendsView() {
    const [tab, setTab] = useState<Tab>('friends')
    const [search, setSearch] = useState('')

    const pendingTotal = MOCK_PENDING_RECEIVED.length + MOCK_PENDING_SENT.length

    const filteredFriends = MOCK_FRIENDS.filter(f =>
        search === '' ||
        f.name.toLowerCase().includes(search.toLowerCase()) ||
        f.email.toLowerCase().includes(search.toLowerCase())
    )

    return (
        <div className="flex flex-col gap-8">
            {/* Header */}
            <div className="flex items-center justify-between flex-wrap gap-3">
                <h1 className="text-2xl font-bold tracking-tight">Znajomi</h1>
                <Button>
                    <UserPlus data-icon="inline-start" />
                    Zaproś znajomego
                </Button>
            </div>

            {/* Stats */}
            <StatsRow friendsCount={MOCK_FRIENDS.length} pendingCount={pendingTotal} />

            {/* Search */}
            <InputGroup>
                <InputGroupAddon align="inline-start">
                    <Search />
                </InputGroupAddon>
                <InputGroupInput
                    placeholder="Szukaj znajomych..."
                    value={search}
                    onChange={e => setSearch(e.target.value)}
                />
            </InputGroup>

            {/* Tabs */}
            <Tabs value={tab} onValueChange={v => { setTab(v as Tab); setSearch('') }}>
                <TabsList>
                    <TabsTrigger value="friends">Znajomi</TabsTrigger>
                    <TabsTrigger value="invitations" className="gap-1.5">
                        Zaproszenia
                        {pendingTotal > 0 && (
                            <Badge variant="secondary" className="text-[10px] px-1.5 py-0 h-4">
                                {pendingTotal}
                            </Badge>
                        )}
                    </TabsTrigger>
                    <TabsTrigger value="suggestions">Sugestie</TabsTrigger>
                </TabsList>
            </Tabs>

            {/* Content */}
            {tab === 'friends' && (
                filteredFriends.length > 0 ? (
                    <div className="flex flex-col gap-3">
                        <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                            Twoi znajomi ({filteredFriends.length})
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            {filteredFriends.map(f => <FriendCard key={f.id} friend={f} />)}
                        </div>
                    </div>
                ) : (
                    <EmptyState tab="friends" />
                )
            )}

            {tab === 'invitations' && (
                <div className="flex flex-col gap-6">
                    {MOCK_PENDING_RECEIVED.length > 0 && (
                        <div className="flex flex-col gap-3">
                            <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                                Otrzymane ({MOCK_PENDING_RECEIVED.length})
                            </p>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                {MOCK_PENDING_RECEIVED.map(f => <PendingReceivedCard key={f.id} friend={f} />)}
                            </div>
                        </div>
                    )}
                    {MOCK_PENDING_SENT.length > 0 && (
                        <div className="flex flex-col gap-3">
                            <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                                Wysłane ({MOCK_PENDING_SENT.length})
                            </p>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                {MOCK_PENDING_SENT.map(f => <PendingSentCard key={f.id} friend={f} />)}
                            </div>
                        </div>
                    )}
                    {MOCK_PENDING_RECEIVED.length === 0 && MOCK_PENDING_SENT.length === 0 && (
                        <EmptyState tab="invitations" />
                    )}
                </div>
            )}

            {tab === 'suggestions' && (
                MOCK_SUGGESTIONS.length > 0 ? (
                    <div className="flex flex-col gap-3">
                        <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                            Może ich znasz ({MOCK_SUGGESTIONS.length})
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            {MOCK_SUGGESTIONS.map(s => <SuggestionCard key={s.id} suggestion={s} />)}
                        </div>
                    </div>
                ) : (
                    <EmptyState tab="suggestions" />
                )
            )}
        </div>
    )
}
