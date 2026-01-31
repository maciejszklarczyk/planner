import { Button } from '@/components/ui/button';
import type { Metadata } from 'next'

export const metadata: Metadata = {
    title: 'O nas',
    description: 'Opis strony dla SEO',
    openGraph: {
        title: 'O nas - Moja Aplikacja',
        description: 'Opis dla social media',
        images: ['/og-image.png'],
    },
}

export default function EventsPage() {
    return (
        <div>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-3xl font-bold">Twoje wydarzenia</h1>
                <Button>+ Nowe wydarzenie</Button>
            </div>
            <p className="text-gray-500">Brak wydarzeń. Utwórz pierwsze!</p>
        </div>
    );
}