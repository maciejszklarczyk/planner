import type { Metadata } from 'next'
import { FriendsView } from '@/components/friends/FriendsView'

export const metadata: Metadata = {
    title: 'Znajomi',
}

export default function FriendsPage() {
    return <FriendsView />
}
