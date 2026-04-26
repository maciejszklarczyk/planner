import type { Metadata } from 'next'
import { Card } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'

export const metadata: Metadata = { title: 'Harmonogram' }

export default function SchedulePage() {
    return (
        <div className="flex flex-col gap-6">
            <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                Harmonogram
            </p>
            <div className="flex flex-col gap-3">
                {Array.from({ length: 4 }).map((_, i) => (
                    <Card key={i} className="p-4 gap-0">
                        <div className="flex gap-4">
                            <div className="flex flex-col items-center gap-1 shrink-0">
                                <Skeleton className="h-3.5 w-12" />
                                <div className="w-px flex-1 bg-border mt-1" />
                            </div>
                            <div className="flex flex-col gap-1.5 flex-1 pb-2">
                                <Skeleton className="h-4 w-48" />
                                <Skeleton className="h-3 w-32" />
                            </div>
                        </div>
                    </Card>
                ))}
            </div>
        </div>
    )
}
