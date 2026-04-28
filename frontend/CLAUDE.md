# CLAUDE.md — Frontend

Stack, struktura, role i ograniczenia biznesowe → root `CLAUDE.md`.

## Klient API (`lib/api.ts`)

```typescript
import { api } from '@/lib/api';

api.get<T>(endpoint)
api.post<T>(endpoint, data)
api.put<T>(endpoint, data)
api.patch<T>(endpoint, data)
api.delete<T>(endpoint)
```

- `credentials: 'include'` — sesja przez cookie
- `cache: 'no-store'` dla GET
- Błędy rzucają `ApiError(status, statusText, body)`
- DELETE zwraca `204 No Content` — `api.delete` obsługuje przez wczesny return `undefined`

## React Query — wzorce

**useQuery:**
```typescript
const { data, isLoading } = useQuery<GroupMembersResponse>({
    queryKey: ['admin', 'groups', groupId, 'members'],
    queryFn: () => api.get(`/admin/groups/${groupId}/users`),
    enabled: open,
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
- `['auth', 'me']` — zalogowany użytkownik
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

## Konwencje

- `'use client'` na komponentach interaktywnych
- Formularze: `useForm` + `zodResolver` + `handleSubmit`
- Komunikaty po polsku (Sonner toasty)
- Ikony z `lucide-react`, rozmiar `h-4 w-4` (lub `h-3.5 w-3.5` w kompaktowych listach)
- Przyciski destructive: `<Trash2 className="text-destructive"/>`
- **Wszystkie `useMutation` żyją w hookach w `hooks/`** — nigdy inline w komponentach
- **Nie modyfikuj `components/ui/`** — shadcn/ui prymitywy, zmiany zostaną nadpisane
- Używaj skilla `shadcn` do weryfikacji zmian na stronach
- Używaj skilla `tailwind-design-system` przy zmianach w layoucie

## Hooki mutacji

| Hook | Endpoint | Invaliduje |
|---|---|---|
| `useDeleteGroup` | `DELETE /admin/groups/{id}` | `['admin', 'groups']` |
| `useRemoveGroupMember(groupId)` | `DELETE /admin/groups/{id}/users/{id}` | `['admin', 'groups', groupId, 'members']`, `['admin', 'groups']` |
| `useDeleteUser` | `DELETE /user/{id}` | `['admin', 'users']` |
| `useUpdateUser({ onSuccess?, invalidateKeys? })` | `PUT /user` | domyślnie `['admin', 'users']`; dla current user: `invalidateKeys: [['auth', 'me']]` |
| `useInvite` | `POST /admin/user-invite` | `['admin', 'users']` |
| `useResendInvite` | `POST /admin/user-invite/resend` | `['admin', 'users']` |
| `useLogin` | `POST /auth/login` | ustawia `['auth', 'me']` |
| `useLogout` | `POST /auth/logout` | czyści cały queryClient |
