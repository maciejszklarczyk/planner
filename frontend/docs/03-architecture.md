# Frontend - Architektura

## Diagram architektury

```
┌─────────────────────────────────────────────────────────┐
│                      Prezentacja                        │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐             │
│  │  Pages   │  │Components│  │  Layouts │             │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘             │
│       │             │             │                    │
│       └─────────────┼─────────────┘                    │
│                     │                                  │
│               ┌─────┴─────┐                            │
│               │   Hooks   │    State Management        │
│               └─────┬─────┘                            │
│                     │                                  │
│               ┌─────┴─────┐                            │
│               │ Services  │    API calls               │
│               └───────────┘                            │
└─────────────────────────────────────────────────────────┘
```

---

## Warstwy aplikacji

### Presentation Layer (Pages & Components)
- Komponenty React
- Strony Next.js (App Router)
- Layouty i nawigacja

### State Layer (Hooks & Stores)
- TanStack Query - server state
- Zustand - client state
- Custom hooks

### Service Layer (API Client)
- Fetch wrapper
- Error handling
- Request/response interceptors

---

## Struktura katalogów

```
frontend/
├── app/
│   ├── (auth)/
│   │   ├── login/
│   │   │   └── page.tsx
│   │   └── register/
│   │       └── page.tsx
│   │
│   ├── (dashboard)/
│   │   ├── trips/
│   │   │   ├── page.tsx           # Lista wycieczek
│   │   │   ├── [id]/
│   │   │   │   └── page.tsx       # Szczegoly wycieczki
│   │   │   └── new/
│   │   │       └── page.tsx       # Nowa wycieczka
│   │   └── layout.tsx
│   │
│   ├── layout.tsx
│   ├── page.tsx
│   └── globals.css
│
├── components/
│   ├── ui/                        # shadcn/ui
│   │   ├── button.tsx
│   │   ├── card.tsx
│   │   ├── input.tsx
│   │   └── ...
│   │
│   ├── forms/
│   │   ├── LoginForm.tsx
│   │   ├── TripForm.tsx
│   │   └── ExpenseForm.tsx
│   │
│   ├── cards/
│   │   ├── TripCard.tsx
│   │   └── ExpenseCard.tsx
│   │
│   └── layout/
│       ├── Header.tsx
│       ├── Sidebar.tsx
│       └── MobileNav.tsx
│
├── lib/
│   ├── api.ts                     # API client
│   ├── utils.ts
│   └── constants.ts
│
├── hooks/
│   ├── useTrips.ts
│   ├── useExpenses.ts
│   ├── useAuth.ts
│   └── useBalances.ts
│
├── stores/
│   └── uiStore.ts
│
└── types/
    ├── trip.ts
    ├── expense.ts
    └── user.ts
```

---

## API Client

```typescript
// lib/api.ts
const API_URL = process.env.NEXT_PUBLIC_API_URL;

export const api = {
  get: async <T>(endpoint: string): Promise<T> => {
    const res = await fetch(`${API_URL}${endpoint}`, {
      credentials: 'include', // Wysyla cookies
    });
    if (!res.ok) throw new ApiError(res);
    return res.json();
  },

  post: async <T>(endpoint: string, data: unknown): Promise<T> => {
    const res = await fetch(`${API_URL}${endpoint}`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
    if (!res.ok) throw new ApiError(res);
    return res.json();
  },
  // ... patch, delete
};
```

---

## TanStack Query - przyklad

```typescript
// hooks/useTrips.ts
import { useQuery, useMutation } from '@tanstack/react-query';
import { api } from '@/lib/api';

export function useTrips() {
  return useQuery({
    queryKey: ['trips'],
    queryFn: () => api.get('/trips'),
  });
}

export function useCreateTrip() {
  return useMutation({
    mutationFn: (data: CreateTripDto) => api.post('/trips', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['trips'] });
    },
  });
}
```

---

## Zustand Store - przyklad

```typescript
// stores/uiStore.ts
import { create } from 'zustand';

interface UIStore {
  sidebarOpen: boolean;
  toggleSidebar: () => void;

  modalType: string | null;
  openModal: (type: string) => void;
  closeModal: () => void;
}

export const useUIStore = create<UIStore>((set) => ({
  sidebarOpen: false,
  toggleSidebar: () => set((s) => ({ sidebarOpen: !s.sidebarOpen })),

  modalType: null,
  openModal: (type) => set({ modalType: type }),
  closeModal: () => set({ modalType: null }),
}));
```

---

## Responsywnosc

- Mobile-first approach
- Breakpoints Tailwind:
  - `sm`: 640px
  - `md`: 768px
  - `lg`: 1024px
  - `xl`: 1280px

---

## Powiazane dokumenty

- [Tech Stack](01-tech-stack.md)
- [UI/UX Design](02-ui-ux.md)
