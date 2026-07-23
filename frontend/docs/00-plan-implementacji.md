# Plan Implementacji: System Logowania TripPlanner

## Cel
Implementacja systemu autentykacji Next.js 16 (App Router) + Symfony backend API z session-based auth (cookies).

## Kontekst

**Frontend:** Czysta instalacja Next.js 16.1.6 w `{{FRONTEND_ROOT}}`
- Tailwind CSS 4, TypeScript 5
- Brak komponentów UI, brak struktury auth

**Backend:** Symfony API w `{{BACKEND_ROOT}}`
- Endpointy: POST /auth/login, GET /auth/me, POST /auth/logout
- Session-based auth (PLANNER_SESSION cookie)
- **BRAK CORS** - wymaga konfiguracji

**Dokumentacja (nieaktualna):**
- docs/01-tech-stack.md - planuje shadcn/ui, TanStack Query, Zustand
- docs/02-ui-ux.md - wireframes
- docs/03-architecture.md - struktura

---

## Architektura Rozwiązania

### Flow Autentykacji
```
User → /events → Middleware (sprawdza cookie) → Redirect /login (jeśli brak)
     → LoginForm → POST /auth/login → Backend ustawia cookie
     → Redirect /events → Layout fetchuje GET /auth/me → Dashboard
```

### Tech Stack dla Auth
- **Session Storage:** Symfony Session (server-side)
- **Transport:** HTTP Cookie (PLANNER_SESSION)
- **Client State:** TanStack Query (cache user data)
- **UI Components:** shadcn/ui (Button, Input, Card, Toast)
- **Validation:** Zod + React Hook Form
- **Protection:** Next.js Middleware

### Struktura Folderów
```
app/
├── (auth)/              # Public routes (login)
│   ├── login/page.tsx
│   └── layout.tsx
├── (dashboard)/         # Protected routes
│   ├── events/page.tsx
│   └── layout.tsx       # Fetchuje user data
├── layout.tsx           # Root providers
└── middleware.ts        # Route protection

lib/
├── api.ts              # Fetch wrapper z credentials: 'include'
└── queryClient.ts      # TanStack Query config

hooks/
├── useAuth.ts          # Query GET /auth/me
├── useLogin.ts         # Mutation POST /auth/login
└── useLogout.ts        # Mutation POST /auth/logout

components/
├── providers/Providers.tsx
├── forms/LoginForm.tsx
└── layout/
    ├── DashboardHeader.tsx
    └── LogoutButton.tsx

types/
└── auth.ts             # User, LoginCredentials, etc.
```

---

## Decyzje Architektoniczne

1. **Przechowywanie stanu auth:**
   - Server-side session (Symfony) jako single source of truth
   - TanStack Query cache'uje user data dla szybkiego dostępu
   - Nie używamy localStorage (security)

2. **Sprawdzanie auth:**
   - Middleware - pierwsza linia obrony (sprawdza cookie, redirect)
   - Dashboard Layout (Server Component) - fetchuje GET /auth/me
   - Defense in depth approach

3. **Route Groups:**
   - `(auth)` - public layout (minimalistyczny)
   - `(dashboard)` - protected layout (navbar, user info)
   - Route groups nie wpływają na URL

4. **Komunikacja z backend:**
   - Bezpośredni fetch (bez API routes)
   - `credentials: 'include'` wysyła cookie
   - `cache: 'no-store'` dla Server Components

5. **Redirects:**
   - Middleware zapisuje intended URL w `?redirect=/events/123`
   - Po logowaniu redirect na saved URL lub default `/events`

---

## Implementacja: Kolejność Kroków

### KROK 1: Backend CORS (KRYTYCZNE) ⏱️ 15 min

**Problem:** Backend nie ma CORS, frontend nie może wysyłać credentials.

**Działania:**
```bash
cd {{BACKEND_ROOT}}
composer require nelmio/cors-bundle
```

**Plik:** `{{BACKEND_ROOT}}/config/packages/nelmio_cors.yaml`
```yaml
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['http://localhost:3000']
        allow_methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
        allow_headers: ['Content-Type', 'Authorization']
        allow_credentials: true
        max_age: 3600
    paths:
        '^/':
            allow_origin: ['http://localhost:3000']
            allow_credentials: true
```

**WAŻNE:** `allow_credentials: true` + konkretny origin (nie `*`)

**Weryfikacja:**
```bash
symfony server:start
curl -H "Origin: http://localhost:3000" \
     -H "Access-Control-Request-Method: POST" \
     -X OPTIONS http://localhost:8000/auth/login -v
# Oczekiwane: Access-Control-Allow-Credentials: true
```

---

### KROK 2: Frontend Dependencies ⏱️ 10 min

**Instalacja pakietów:**
```bash
cd {{FRONTEND_ROOT}}
npm install @tanstack/react-query
npm install react-hook-form @hookform/resolvers zod
```

**Instalacja shadcn/ui:**
```bash
npx shadcn@latest init
# Wybierz: New York style, Zinc color, CSS variables: yes
npx shadcn@latest add button input card label toast form
```

**Plik:** `.env.local`
```env
NEXT_PUBLIC_API_URL=http://localhost:8000
```

---

### KROK 3: Core Infrastructure ⏱️ 20 min

**Plik 1:** `lib/api.ts`
```typescript
const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

export class ApiError extends Error {
  constructor(
    public status: number,
    public statusText: string,
    public body: unknown,
  ) {
    super(`API Error ${status}: ${statusText}`);
  }
}

export const api = {
  async get<T>(endpoint: string): Promise<T> {
    const res = await fetch(`${API_URL}${endpoint}`, {
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      cache: 'no-store',
    });
    if (!res.ok) {
      throw new ApiError(res.status, res.statusText, await res.json());
    }
    return res.json();
  },

  async post<T>(endpoint: string, data?: unknown): Promise<T> {
    const res = await fetch(`${API_URL}${endpoint}`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: data ? JSON.stringify(data) : undefined,
    });
    if (!res.ok) {
      throw new ApiError(res.status, res.statusText, await res.json());
    }
    return res.json();
  },
};
```

**Kluczowe:**
- `credentials: 'include'` - wysyła cookie
- `cache: 'no-store'` - dla Server Components
- Custom `ApiError` z statusem

**Plik 2:** `types/auth.ts`
```typescript
export interface User {
  id: number;
  email: string;
  roles: string[];
}

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface LoginResponse {
  user: User;
}
```

**Plik 3:** `lib/queryClient.ts`
```typescript
import { QueryClient } from '@tanstack/react-query';

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 60 * 1000, // 1 minute
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});
```

---

### KROK 4: Authentication Hooks ⏱️ 20 min

**Plik 1:** `hooks/useAuth.ts`
```typescript
'use client';

import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { User } from '@/types/auth';

export function useAuth() {
  const { data: user, isLoading, error } = useQuery<User | null>({
    queryKey: ['auth', 'me'],
    queryFn: async () => {
      try {
        return await api.get<User>('/auth/me');
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
          return null;
        }
        throw error;
      }
    },
    retry: false,
  });

  return {
    user,
    isAuthenticated: !!user,
    isLoading,
  };
}
```

**Plik 2:** `hooks/useLogin.ts`
```typescript
'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { LoginCredentials, LoginResponse } from '@/types/auth';

export function useLogin() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (credentials: LoginCredentials) =>
      api.post<LoginResponse>('/auth/login', credentials),

    onSuccess: (data) => {
      queryClient.setQueryData(['auth', 'me'], data.user);
    },
  });
}
```

**Plik 3:** `hooks/useLogout.ts`
```typescript
'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';

export function useLogout() {
  const queryClient = useQueryClient();
  const router = useRouter();

  return useMutation({
    mutationFn: () => api.post('/auth/logout'),
    onSuccess: () => {
      queryClient.clear();
      router.push('/login');
    },
  });
}
```

---

### KROK 5: Providers Setup ⏱️ 10 min

**Plik 1:** `components/providers/Providers.tsx`
```typescript
'use client';

import { QueryClientProvider } from '@tanstack/react-query';
import { queryClient } from '@/lib/queryClient';
import { Toaster } from '@/components/ui/toaster';

export function Providers({ children }: { children: React.ReactNode }) {
  return (
    <QueryClientProvider client={queryClient}>
      {children}
      <Toaster />
    </QueryClientProvider>
  );
}
```

**Plik 2:** Update `app/layout.tsx`
```typescript
import { Providers } from "@/components/providers/Providers";

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="pl">
      <body>
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
```

---

### KROK 6: Middleware Protection ⏱️ 10 min

**Plik:** `middleware.ts`
```typescript
import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

const PUBLIC_ROUTES = ['/login'];

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  const isPublicRoute = PUBLIC_ROUTES.some(route =>
    pathname.startsWith(route)
  );

  if (isPublicRoute) {
    return NextResponse.next();
  }

  const sessionCookie = request.cookies.get('PLANNER_SESSION');

  if (!sessionCookie) {
    const redirectUrl = new URL('/login', request.url);
    redirectUrl.searchParams.set('redirect', pathname);
    return NextResponse.redirect(redirectUrl);
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    '/((?!_next/static|_next/image|favicon.ico|.*\\..*|api).*)',
  ],
};
```

**Kluczowe:**
- Sprawdza tylko obecność cookie (nie zawartość)
- Zapisuje intended URL w `?redirect=`
- Matcher excludes Next.js internals

---

### KROK 7: Login UI ⏱️ 30 min

**Plik 1:** `components/forms/LoginForm.tsx`
```typescript
'use client';

import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useRouter, useSearchParams } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useToast } from '@/hooks/use-toast';
import { useLogin } from '@/hooks/useLogin';

const loginSchema = z.object({
  email: z.string().email('Nieprawidłowy email'),
  password: z.string().min(1, 'Hasło wymagane'),
});

type LoginFormData = z.infer<typeof loginSchema>;

export function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { toast } = useToast();
  const { mutate: login, isPending } = useLogin();

  const { register, handleSubmit, formState: { errors } } =
    useForm<LoginFormData>({
      resolver: zodResolver(loginSchema),
    });

  const onSubmit = (data: LoginFormData) => {
    login(data, {
      onSuccess: () => {
        toast({
          title: 'Zalogowano',
          description: 'Witaj z powrotem!',
        });
        const redirect = searchParams.get('redirect') || '/events';
        router.push(redirect);
      },
      onError: () => {
        toast({
          variant: 'destructive',
          title: 'Błąd logowania',
          description: 'Nieprawidłowy email lub hasło',
        });
      },
    });
  };

  return (
    <Card className="w-full max-w-md">
      <CardHeader>
        <CardTitle>Logowanie</CardTitle>
        <CardDescription>Zaloguj się do TripPlanner</CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="email">Email</Label>
            <Input
              id="email"
              type="email"
              {...register('email')}
              disabled={isPending}
            />
            {errors.email && (
              <p className="text-sm text-red-500">{errors.email.message}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="password">Hasło</Label>
            <Input
              id="password"
              type="password"
              {...register('password')}
              disabled={isPending}
            />
            {errors.password && (
              <p className="text-sm text-red-500">{errors.password.message}</p>
            )}
          </div>

          <Button type="submit" className="w-full" disabled={isPending}>
            {isPending ? 'Logowanie...' : 'Zaloguj się'}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
```

**Plik 2:** `app/(auth)/login/page.tsx`
```typescript
import { LoginForm } from '@/components/forms/LoginForm';

export default function LoginPage() {
  return (
    <div className="flex min-h-screen items-center justify-center">
      <LoginForm />
    </div>
  );
}
```

**Plik 3:** `app/(auth)/layout.tsx`
```typescript
export default function AuthLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
      {children}
    </div>
  );
}
```

---

### KROK 8: Dashboard Layout ⏱️ 20 min

**Plik 1:** `app/(dashboard)/layout.tsx`
```typescript
import { redirect } from 'next/navigation';
import { api } from '@/lib/api';
import { DashboardHeader } from '@/components/layout/DashboardHeader';
import type { User } from '@/types/auth';

async function getUser(): Promise<User | null> {
  try {
    return await api.get<User>('/auth/me');
  } catch (error) {
    return null;
  }
}

export default async function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const user = await getUser();

  if (!user) {
    redirect('/login');
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <DashboardHeader user={user} />
      <main className="container mx-auto px-4 py-8">
        {children}
      </main>
    </div>
  );
}
```

**Kluczowe:**
- Server Component fetchuje user (credentials auto-forwarded)
- Defense in depth (middleware + layout check)

**Plik 2:** `components/layout/DashboardHeader.tsx`
```typescript
import { LogoutButton } from './LogoutButton';
import type { User } from '@/types/auth';

interface DashboardHeaderProps {
  user: User;
}

export function DashboardHeader({ user }: DashboardHeaderProps) {
  return (
    <header className="border-b bg-white">
      <div className="container mx-auto flex h-16 items-center justify-between px-4">
        <h1 className="text-xl font-bold">TripPlanner</h1>
        <div className="flex items-center gap-4">
          <span className="text-sm text-gray-600">{user.email}</span>
          <LogoutButton />
        </div>
      </div>
    </header>
  );
}
```

**Plik 3:** `components/layout/LogoutButton.tsx`
```typescript
'use client';

import { Button } from '@/components/ui/button';
import { useLogout } from '@/hooks/useLogout';

export function LogoutButton() {
  const { mutate: logout, isPending } = useLogout();

  return (
    <Button
      variant="outline"
      size="sm"
      onClick={() => logout()}
      disabled={isPending}
    >
      {isPending ? 'Wylogowywanie...' : 'Wyloguj'}
    </Button>
  );
}
```

---

### KROK 9: Landing & Events Page ⏱️ 10 min

**Plik 1:** `app/page.tsx`
```typescript
import { redirect } from 'next/navigation';

export default function HomePage() {
  redirect('/events');
}
```

**Plik 2:** `app/(dashboard)/trips/page.tsx`
```typescript
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
```

---

### KROK 10: Error Handling ⏱️ 15 min

**Plik 1:** `app/error.tsx`
```typescript
'use client';

import { useEffect } from 'react';
import { Button } from '@/components/ui/button';

export default function Error({
  error,
  reset,
}: {
  error: Error;
  reset: () => void;
}) {
  useEffect(() => {
    console.error('App error:', error);
  }, [error]);

  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-4">
      <h2 className="text-2xl font-bold">Coś poszło nie tak</h2>
      <Button onClick={reset}>Spróbuj ponownie</Button>
    </div>
  );
}
```

**Plik 2:** `app/(dashboard)/loading.tsx`
```typescript
export default function DashboardLoading() {
  return (
    <div className="flex h-screen items-center justify-center">
      <div className="h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-blue-600" />
    </div>
  );
}
```

---

## Kluczowe Pliki do Implementacji

### Backend (CORS)
- `{{BACKEND_ROOT}}/config/packages/nelmio_cors.yaml`

### Frontend Core
- `lib/api.ts` - Fetch wrapper z credentials
- `types/auth.ts` - TypeScript types
- `lib/queryClient.ts` - TanStack Query config

### Hooks
- `hooks/useAuth.ts` - Query GET /auth/me
- `hooks/useLogin.ts` - Mutation POST /auth/login
- `hooks/useLogout.ts` - Mutation POST /auth/logout

### Protection
- `middleware.ts` - Route protection, cookie check

### UI Components
- `components/forms/LoginForm.tsx` - Login form z walidacją
- `components/layout/DashboardHeader.tsx` - Header z user info
- `components/layout/LogoutButton.tsx` - Logout button
- `components/providers/Providers.tsx` - Root providers

### Pages
- `app/layout.tsx` - Root layout z Providers
- `app/(auth)/login/page.tsx` - Login page
- `app/(auth)/layout.tsx` - Auth layout (public)
- `app/(dashboard)/layout.tsx` - Dashboard layout (protected)
- `app/(dashboard)/trips/page.tsx` - Trips list
- `app/page.tsx` - Landing redirect

---

## Weryfikacja End-to-End

### Test 1: Login Flow
```bash
# 1. Start backend
cd {{BACKEND_ROOT}}
symfony server:start

# 2. Start frontend
cd {{FRONTEND_ROOT}}
npm run dev

# 3. Test
# a) http://localhost:3000 → redirect /login
# b) Fill form (use backend user)
# c) Should redirect /trips
# d) DevTools → Cookie PLANNER_SESSION present
```

### Test 2: Protected Routes
```bash
# 1. Clear cookies
# 2. http://localhost:3000/trips
# 3. Should redirect /login?redirect=/trips
# 4. Login → return to /trips
```

### Test 3: Logout
```bash
# 1. Login successfully
# 2. Click "Wyloguj"
# 3. Cookie cleared, redirect /login
```

### Test 4: Session Persistence
```bash
# 1. Login
# 2. Refresh page → still logged in
```

### Test 5: CORS
```bash
curl -H "Origin: http://localhost:3000" \
     -H "Access-Control-Request-Method: POST" \
     -X OPTIONS http://localhost:8000/auth/login -v

# Expected:
# Access-Control-Allow-Origin: http://localhost:3000
# Access-Control-Allow-Credentials: true
```

### Test 6: Error Handling
```bash
# 1. Wrong password → toast error
# 2. No redirect, no cookie
```

---

## Potencjalne Problemy

### Problem 1: CORS Errors
**Symptom:** "CORS policy blocked" w konsoli
**Fix:**
- Sprawdź NelmioCorsBundle zainstalowany
- Sprawdź `allow_credentials: true`
- Sprawdź konkretny origin (nie `*`)
- Restart Symfony server

### Problem 2: Cookie nie wysyłany
**Symptom:** GET /auth/me zwraca 401
**Fix:**
- Sprawdź `credentials: 'include'`
- DevTools → Application → Cookies → PLANNER_SESSION
- Sprawdź SameSite=Strict/Lax (nie None dla localhost)

### Problem 3: Middleware loop
**Symptom:** Infinite redirect /login
**Fix:**
- Sprawdź PUBLIC_ROUTES includes '/login'
- Sprawdź matcher excludes /login

### Problem 4: Server Component fetch fails
**Symptom:** Layout nie widzi usera
**Fix:**
- Sprawdź `cache: 'no-store'`
- Sprawdź async function w layout
- Next.js 16 auto-forwards cookies

---

## Szacowany Czas Implementacji

- KROK 1: Backend CORS - 15 min
- KROK 2: Dependencies - 10 min
- KROK 3: Infrastructure - 20 min
- KROK 4: Hooks - 20 min
- KROK 5: Providers - 10 min
- KROK 6: Middleware - 10 min
- KROK 7: Login UI - 30 min
- KROK 8: Dashboard - 20 min
- KROK 9: Pages - 10 min
- KROK 10: Errors - 15 min
- **Weryfikacja:** 20 min

**RAZEM: ~3 godziny**

---

## Następne Kroki (Post-MVP)

1. **Aktualizacja dokumentacji** - zaktualizować docs/ do Next.js 16
2. **Trips CRUD** - implementacja listy wycieczek z backend
3. **Expenses CRUD** - dodawanie wydatków
4. **Balance calculation** - rozliczenia
5. **Rozszerzenia:**
   - Remember Me
   - Password reset
   - User registration
   - Email verification
