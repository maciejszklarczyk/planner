import { Button } from '@/components/ui/button';

export default function TripsPage() {
    return (
        <div>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-3xl font-bold">Twoje wycieczki</h1>
                <Button>+ Nowa wycieczka</Button>
            </div>
            <p className="text-gray-500">Brak wycieczek. Utwórz pierwszą!</p>
        </div>
    );
}