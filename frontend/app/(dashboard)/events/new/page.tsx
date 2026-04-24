import type { Metadata } from 'next'

export const metadata: Metadata = {
    title: 'Nowe wydarzenie',
}

export default function NewEventPage() {
    return (
        <div className="flex flex-col gap-8">
            <div className="flex items-center justify-between flex-wrap gap-3">
                <h1 className="text-2xl font-bold tracking-tight">Nowe wydarzenie</h1>
            </div>
        </div>
    )
}
