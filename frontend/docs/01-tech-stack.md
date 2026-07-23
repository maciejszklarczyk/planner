# Frontend - Tech Stack

## Podsumowanie

```
Framework:      Next.js 14+ (App Router)
UI Library:     React 18+
Styling:        Tailwind CSS + shadcn/ui
State:          TanStack Query + Zustand
Platforma:      PWA (Progressive Web App)
```

---

## Technologie

### Framework: Next.js

Next.js z App Router zapewnia:
- Server-Side Rendering (SSR)
- Static Site Generation (SSG)
- API routes (opcjonalnie)
- Optymalizacja obrazów
- Automatyczny code splitting

**Dlaczego Next.js?**
- Wcześniejsze doświadczenie z tą technologią
- Chęć poszerzenia wiedzy
- Świetne wsparcie dla PWA
- Dobra integracja z session cookies

### Styling: Tailwind CSS + shadcn/ui

- **Tailwind CSS** - utility-first CSS framework
- **shadcn/ui** - gotowe komponenty React
- Szybki development, spójna estetyka

### State Management

- **TanStack Query** - zarządzanie danymi z API
  - Cache
  - Refetch
  - Loading states
  - Error handling

- **Zustand** - proste stany UI
  - Modalne okna
  - Sidebar state
  - User preferences

### PWA

Progressive Web App zapewnia:
- Instalacja na urządzeniu (opcjonalna)
- Offline support (przyszłość)
- Push notifications (przyszłość)

---

## Struktura katalogów

```
frontend/
├── app/                    # Next.js App Router
│   ├── (auth)/            # Strony logowania/rejestracji
│   ├── (dashboard)/       # Strony zalogowanego użytkownika
│   ├── layout.tsx
│   └── page.tsx
│
├── components/
│   ├── ui/                # shadcn/ui components
│   ├── forms/             # Formularze
│   ├── cards/             # Karty (trip, expense)
│   └── layout/            # Header, footer, sidebar
│
├── lib/
│   ├── api.ts             # API client
│   ├── utils.ts           # Utility functions
│   └── constants.ts
│
├── hooks/
│   ├── useTrips.ts
│   ├── useExpenses.ts
│   └── useAuth.ts
│
├── stores/
│   └── uiStore.ts         # Zustand store
│
├── types/
│   └── index.ts           # TypeScript types
│
└── Dockerfile
```

---

## Wymagania

- Node.js 18+
- npm/yarn/pnpm
- Docker (opcjonalnie)

---

## Powiązane dokumenty

- [UI/UX Design](02-ui-ux.md)
- [Architektura](03-architecture.md)
