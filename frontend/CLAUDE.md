# CLAUDE.md — Frontend

## Stack

- **Next.js 16.1.6** z App Router (`output: 'standalone'`)
- **React 19.2.3**
- **TypeScript 5**
- **TanStack React Query 5.90.20** — server state, mutacje, invalidacja
- **react-hook-form 7** + **Zod 4** — formularze i walidacja
- **Radix UI** — prymitywy UI (dialog, alert-dialog, dropdown, tooltip...)
- **Tailwind CSS 4**
- **Sonner** — toasty (`toast.success`, `toast.error`)
- **lucide-react** — ikony (Trash2, Pencil, itp.)

## Struktura

```
frontend/
├── app/
│   ├── (auth)/           # login, set-password (publiczne)
│   └── (dashboard)/      # events, settings (chronione)
├── components/
│   ├── ui/               # prymitywy Radix (Button, Dialog, AlertDialog...)
│   ├── users/            # tabele i modale admin (UsersTable, GroupsTable, GroupsTableColumn...)
│   ├── forms/            # formularze
│   ├── sidebar/          # nawigacja
│   └── providers/        # QueryClientProvider, ThemeProvider
├── hooks/                # useAuth, useGroupMembers, useLogin, useInvite...
├── lib/
│   ├── api.ts            # klient HTTP
│   ├── queryClient.ts    # konfiguracja React Query
│   └── utils.ts
└── types/
    ├── auth.ts           # User, UserStatus, LoginCredentials
    ├── groups.ts         # Group, GroupMembership, GroupMembersResponse
    ├── api.ts            # ApiResponse, UsersResponse
    └── invitation.ts
```

## Klient API (`lib/api.ts`)

```typescript
import { api } from '@/lib/api';

api.get<T>(endpoint)
api.post<T>(endpoint, data)
api.put<T>(endpoint, data)
api.delete<T>(endpoint)
```

- Base URL: `process.env.NEXT_PUBLIC_API_URL` (domyślnie `http://localhost:8000`)
- Prefix `/api` dodawany automatycznie wewnątrz klienta
- `credentials: 'include'` — sesja przez cookie
- `cache: 'no-store'` dla GET
- Błędy rzucają `ApiError(status, statusText, body)`
- DELETE zwraca `204 No Content` (puste body) — `api.delete` obsługuje to przez wczesny return `undefined`

## React Query — wzorce

**useQuery:**
```typescript
const { data, isLoading } = useQuery<GroupMembersResponse>({
    queryKey: ['admin', 'groups', groupId, 'members'],
    queryFn: () => api.get(`/admin/groups/${groupId}/users`),
    enabled: open,   // warunek aktywacji
});
```

**useMutation:**
```typescript
const mutation = useMutation({
    mutationFn: ({ groupId, userId }) => api.delete(`/admin/groups/${groupId}/users/${userId}`),
    onSuccess: () => {
        toast.success('...');
        queryClient.invalidateQueries({ queryKey: ['admin', 'groups'] });
    },
    onError: () => toast.error('Błąd', { description: '...' }),
});
mutation.mutate({ groupId, userId });
```

**Query keys — konwencja:**
- `['admin', 'users']` — lista użytkowników
- `['admin', 'groups']` — lista grup
- `['admin', 'groups', groupId, 'members']` — członkowie grupy

## QueryClient (`lib/queryClient.ts`)

- `staleTime: 60_000`
- `retry: 1`
- `refetchOnWindowFocus: false`
- Globalny handler 401 → redirect do `/login`

## Typy grup

```typescript
interface Group { id: number; name: string; description: string; membersCount: number; }
interface GroupMembership {
    id: number;
    user: { id: number; email: string; name: string };
    groupId: number; groupName: string;
    role: 'member' | 'owner';
    addedBy: { id: number; email: string; name: string } | null;
}
```

## Wzorzec modalny (EditGroupModal)

- Modal otwierany przez osobny komponent-przycisk z lokalnym `useState(open)`
- `useGroupMembers(groupId, open)` — enabled jest przekazywane jako drugi arg
- Warunek usuwania membera: `role !== 'owner' && members.length > 1`

## Konwencje

- `'use client'` na komponentach interaktywnych
- Formularze: `useForm` + `zodResolver` + `handleSubmit`
- Komunikaty po polsku (Sonner toasty)
- Ikony z `lucide-react`, rozmiar `h-4 w-4` (lub `h-3.5 w-3.5` w kompaktowych listach)
- Przyciski destructive: `<Trash2 className="text-destructive"/>`
- **Wszystkie `useMutation` żyją w hookach** w `hooks/` — nigdy inline w komponentach

## Hooki mutacji

| Hook | Endpoint | Invaliduje |
|---|---|---|
| `useDeleteGroup` | `DELETE /admin/groups/{id}` | `['admin', 'groups']` |
| `useRemoveGroupMember(groupId)` | `DELETE /admin/groups/{id}/users/{id}` | `['admin', 'groups', groupId, 'members']`, `['admin', 'groups']` |
| `useDeleteUser` | `DELETE /user/{id}` | `['admin', 'users']` |
| `useUpdateUser({ onSuccess?, invalidateKeys? })` | `PUT /user` | domyślnie `['admin', 'users']`, dla current user przekaż `invalidateKeys: [['auth', 'me']]` |
| `useInvite` | `POST /admin/user-invite` | `['admin', 'users']` |
| `useResendInvite` | `POST /admin/user-invite/resend` | `['admin', 'users']` |
| `useLogin` | `POST /auth/login` | ustawia `['auth', 'me']` |
| `useLogout` | `POST /auth/logout` | czyści cały queryClient |
