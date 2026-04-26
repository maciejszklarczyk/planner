import type { Metadata } from 'next'
import { Card } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'

export const metadata: Metadata = { title: 'Uczestnicy' }

export default function AttendeesPage() {
    return (
        <div className="flex flex-col gap-6">
            <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                Uczestnicy
            </p>
            <Card className="p-0 overflow-hidden gap-0">
                <div className="px-4 py-3 border-b flex items-center justify-between">
                    <Skeleton className="h-4 w-32" />
                    <Skeleton className="h-8 w-28" />
                </div>
                {Array.from({ length: 5 }).map((_, i) => (
                    <div key={i} className="flex items-center gap-3 px-4 py-3 border-b last:border-0">
                        <Skeleton className="size-8 rounded-full shrink-0" />
                        <div className="flex flex-col gap-1.5 flex-1">
                            <Skeleton className="h-3.5 w-36" />
                            <Skeleton className="h-3 w-48" />
                        </div>
                        <Skeleton className="h-5 w-20" />
                    </div>
                ))}
            </Card>
        </div>
    )
}
