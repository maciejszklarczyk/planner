import type { Metadata } from "next";
import { EventsView } from "@/components/events/EventsView";

export const metadata: Metadata = {
  title: "Wydarzenia",
};

export default function EventsPage() {
  return <EventsView />;
}
