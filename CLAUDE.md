# CLAUDE.md — Plan (root projektu)

Szczegółowe instrukcje dla poszczególnych warstw:
- Backend: `backend/CLAUDE.md`
- Frontend: `frontend/CLAUDE.md`

## Stack

- **Frontend**: Next.js 16.1.6 + React 19, TypeScript 5, TanStack Query 5, TanStack Table 8, Tailwind CSS 4, Radix UI, Zod 4, Sonner, react-hook-form 7, lucide-react, @tabler/icons-react
- **Backend**: PHP 8.4, Symfony 7.4, FrankenPHP, Doctrine ORM 3.6, PostgreSQL 16, Redis 7, Mailpit (dev SMTP)
- **Auth**: sesje HTTP (`credentials: 'include'`), `json_login` Symfony pod `/auth/login`
- **Dev**: osobne docker-compose w `backend/` i `frontend/`; skrypt `herald/herald.sh` łączy oba
- **API client**: Bruno (`bruno/`) — kolekcja requestów do ręcznego testowania API

## Struktura projektu

```
plan/
├── backend/        # Symfony API (FrankenPHP, port 8000)
├── frontend/       # Next.js UI (port 3000)
├── herald/         # herald.sh — dev CLI (up/down/restart/db-reset/status/test)
├── bruno/          # Bruno API kolekcja (ręczne testowanie endpointów)
└── docs/           # dokumentacja codebase
```

## Uruchamianie (dev)

```bash
# All-in-one:
./herald/herald.sh up

# Lub osobno:
cd backend && docker compose up -d
cd frontend && docker compose up -d

# frontend: http://localhost:3000
# backend:  http://localhost:8000
# API docs: http://localhost:8000/api/doc
```

## API URL

- Frontend → `http://localhost:8000` (env: `NEXT_PUBLIC_API_URL`)
- `lib/api.ts` **nie** dodaje prefiksu `/api` — endpointy wywołuje bez prefiksu
- Backend wystawia trasy **bez** `/api/` (np. `/admin/users`, `/events`, `/auth/login`)
- Wyjątek: Swagger pod `/api/doc` (Nelmio)

## Role użytkowników

- `ROLE_USER` — zalogowany użytkownik
- `ROLE_ADMIN` — admin (dostęp do `/admin/*`)

## Rola w grupach

- `owner` — właściciel grupy
- `member` — zwykły członek

## Kluczowe ograniczenia biznesowe

- Nie można usunąć ostatniego ownera z grupy — backend rzuca `CannotRemoveLastOwnerException`
- Nie można usunąć usera z rolą `owner` z poziomu frontu (warunek `role !== 'owner'`)
- Nie można usunąć usera jeśli jest jedynym członkiem grupy (warunek `members.length > 1`)
- Soft delete na encjach `User` i `Group` (pole `deletedAt`)
- Rate limiting logowania: 3 próby / 15 min
- Token zaproszenia: `bin2hex(random_bytes(32))`, ważność 1 dzień

## Wzorzec API

```
# Auth
POST   /auth/login           (json: email, password)
POST   /auth/logout
GET    /auth/me

# Zaproszenia (PUBLIC)
GET    /invitation/verify
POST   /invitation/complete

# Grupy (ROLE_USER)
GET    /groups
GET    /groups/{group}
DELETE /groups/{group}

# Events (ROLE_USER)
GET    /events
GET    /events/{event}
POST   /events
PUT    /events/{event}
DELETE /events/{event}

# User (ROLE_USER)
GET    /user
PUT    /user
DELETE /user/{userId}

# Admin — grupy (ROLE_ADMIN)
GET    /admin/groups
DELETE /admin/groups/{groupId}
GET    /admin/groups/{groupId}/users
POST   /admin/groups/{groupId}/users
DELETE /admin/groups/{groupId}/users/{userId}
PATCH  /admin/groups/{groupId}/users/{userId}/role

# Admin — użytkownicy (ROLE_ADMIN)
GET    /admin/users?page&limit&search&excludeGroupId
POST   /admin/user-invite
POST   /admin/user-invite/resend
```

## Backend src

```
src/
├── Controller/      # HTTP controllers (+ Admin/)
├── DataFixtures/    # Alice fixtures
├── Dto/             # Request DTOs (+ Response/)
├── Entity/          # Doctrine entities (+ Enum/)
├── Exception/       # Domain exceptions
├── Repository/      # Doctrine repositories
├── Security/        # DevHeaderAuthenticator, voters
└── Service/         # Business logic
```

Encje: `User`, `Group`, `UserHasGroup`, `Event`, `UserInvitationToken`, `UserActivityLog`

## Frontend structure

```
app/
├── (auth)/          # login, set-password (publiczne)
└── (dashboard)/     # events, friends, settings (chronione)
components/
├── ui/              # shadcn/Radix prymitywy — NIE MODYFIKUJ
├── users/           # tabele i modale admin
└── ...
hooks/               # wszystkie useQuery + useMutation
lib/
├── api.ts           # klient HTTP
├── queryClient.ts   # staleTime: 60s, retry: 1, 401 → /login
└── utils.ts
types/               # auth, groups, api, invitation
```

## Dev auth (tylko dev/test)

Zamiast logowania: nagłówek `X-Dev-User: email@example.com`.
W testach PHP: `HTTP_X_DEV_USER`. Obsługuje `DevHeaderAuthenticator`.

## Fixtures (grupy)

- `group_1`: admin=owner, user_1=member, user_2=member
- `group_2`: admin=member, user_1=owner, user_3=member
- `group_3`: user_2=owner, user_3=member, user_4=member
- `group_4`: user_4=owner, user_5=member
- `group_5`: user_5=owner
