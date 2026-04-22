export interface Event {
    id: number
    name: string
    startDate: string // 'YYYY-MM-DD'
    endDate: string   // 'YYYY-MM-DD'
    location: string
    attendees: number
    category: string
}

export type EventStatus = 'live' | 'recent' | 'upcoming' | 'ended'
