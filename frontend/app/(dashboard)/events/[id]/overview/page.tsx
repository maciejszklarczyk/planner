import type { Metadata } from "next";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";

export const metadata: Metadata = { title: "Przegląd wydarzenia" };

export default function OverviewPage() {
  return (
    <div className="flex flex-col gap-6">
      <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
        Przegląd
      </p>
      <div className="flex flex-col sm:flex-row gap-3">
        {["Rejestracje", "Potwierdzeni", "Oczekujący"].map((label) => (
          <Card key={label} className="flex-1 px-4 py-3 gap-0">
            <span className="text-muted-foreground text-xs">{label}</span>
            <Skeleton className="mt-2 h-8 w-16" />
          </Card>
        ))}
      </div>
      <Card className="p-6 gap-0">
        <p className="text-sm font-medium mb-3">Opis wydarzenia</p>
        <div className="flex flex-col gap-2">
          <Skeleton className="h-4 w-full" />
          <Skeleton className="h-4 w-4/5" />
          <Skeleton className="h-4 w-3/5" />
        </div>
      </Card>
    </div>
  );
}
