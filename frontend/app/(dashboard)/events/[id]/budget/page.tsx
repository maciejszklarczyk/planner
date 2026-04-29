import type { Metadata } from 'next'
import { Card } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'

export const metadata: Metadata = { title: 'Budżet' }

export default function BudgetPage() {
    return (
        <div className="flex flex-col gap-6">
            <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                Budżet
            </p>
            <div className="flex flex-col sm:flex-row gap-3">
                {['Całkowity budżet', 'Wydatki', 'Pozostało'].map(label => (
                    <Card key={label} className="flex-1 px-4 py-3 gap-0">
                        <span className="text-muted-foreground text-xs">{label}</span>
                        <Skeleton className="mt-2 h-8 w-20" />
                    </Card>
                ))}
            </div>
            <Card className="p-0 overflow-hidden gap-0">
                <div className="px-4 py-3 border-b flex items-center justify-between">
                    <Skeleton className="h-4 w-28" />
                    <Skeleton className="h-8 w-32" />
                </div>
                {Array.from({ length: 4 }).map((_, i) => (
                    <div key={i} className="flex items-center gap-3 px-4 py-3 border-b last:border-0">
                        <div className="flex flex-col gap-1.5 flex-1">
                            <Skeleton className="h-3.5 w-40" />
                            <Skeleton className="h-3 w-24" />
                        </div>
                        <Skeleton className="h-4 w-16" />
                    </div>
                ))}
            </Card>
        </div>
    )
}
