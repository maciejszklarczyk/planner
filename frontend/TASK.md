# shadcn/ui — Audit & Improvement Tasks

## Critical Rule Violations

### 1. `space-y-*` → `flex flex-col gap-*`
- `components/forms/LoginForm.tsx:55` — `className="space-y-4"` on `<form>`
- `components/forms/LoginForm.tsx:56,69` — `className="space-y-2"` on field wrappers
- `components/forms/SetPasswordForm.tsx:88,89,99` — same pattern
- `components/users/GroupsTableColumn.tsx:107` — `className="mt-2 space-y-4"` in dialog body

### 2. Hardcoded error colors → `<FieldError>`
- `components/forms/LoginForm.tsx:65,78` — `<p className="text-sm text-red-500">`
- `components/forms/SetPasswordForm.tsx:108` — same
- `components/users/InviteUserDialog.tsx:75` — same (`FieldError` is already installed and used in `CurrentUserEditForm`)

### 3. Button loading states — inline text → `Spinner` + `disabled`
Every form button does `{isPending ? 'Logowanie...' : 'Zaloguj się'}` instead of composing with `Spinner`.
- `components/forms/LoginForm.tsx:83`
- `components/forms/SetPasswordForm.tsx:113`
- `components/forms/CurrentUserEditForm.tsx:118,128,184`
- `components/layout/LogoutButton.tsx:16`
- `components/sidebar/NavUser.tsx:109`
- `components/users/UsersTableColumn.tsx:98,163`
- `components/users/GroupsTableColumn.tsx:206`

### 4. Icon sizing — `w-N h-N` → `size-N`
- `components/users/GroupsTableColumn.tsx:82` — `<UserPlus className="h-3.5 w-3.5 shrink-0 ..."/>`
- `components/users/GroupsTableColumn.tsx:150` — `<Trash2 className="h-3.5 w-3.5 text-destructive"/>`
- `components/users/GroupsTableColumn.tsx:148` — icon button `className="h-7 w-7"` → `size-7`
- `components/users/GroupsTableColumn.tsx:175` — `<Pencil className="h-4 w-4"/>`
- `components/users/GroupsTableColumn.tsx:190` — `<Trash2 className="h-4 w-4 text-destructive"/>`

### 5. Missing `data-icon` on icons inside `<Button>`
- `components/users/InviteUserDialog.tsx:52` — `<IconPlus/>` missing `data-icon="inline-start"`
- `components/users/GroupsTableColumn.tsx` — `<Pencil>`, `<Trash2>` in icon buttons

### 6. Icon library inconsistency — project uses `lucide`, one file uses `@tabler`
- `components/users/InviteUserDialog.tsx:20` — imports `IconPlus` from `@tabler/icons-react` → replace with `Plus` from `lucide-react`

---

## Form Pattern Issues

### 7. `LoginForm` and `SetPasswordForm` — not using `FieldGroup` + `Field` + `FieldLabel`
Using raw `<div className="space-y-2">` + `<Label>` instead of installed Field components.
- `components/forms/LoginForm.tsx:55–80`
- `components/forms/SetPasswordForm.tsx:88–110`

### 8. `InviteUserDialog` uses `<Label>` instead of `<FieldLabel>` inside `<Field>`
- `components/users/InviteUserDialog.tsx:69` — `data-slot="field-label"` won't be applied, breaks Field slot system

---

## Custom Markup Where Components Should Be Used

### 9. Custom spinner divs with hardcoded raw colors
```tsx
// Should be replaced with <Spinner> component once installed
<div className="h-4 w-4 animate-spin rounded-full border-2 border-gray-200 border-t-orange-600"/>
```
- `components/users/GroupsTableColumn.tsx:68` (search loading)
- `components/users/GroupsTableColumn.tsx:118` (members loading)

### 10. Custom combobox in `AddMemberDropdown`
- `components/users/GroupsTableColumn.tsx:49–91` — custom `<ul>/<li>/<button>` dropdown with raw colors
- Should be replaced with `Command` + `Popover` (neither installed yet)

### 11. Raw loading/error states in `SetPasswordForm`
- `components/forms/SetPasswordForm.tsx:56` — `<div>Verifying token...</div>` → `<Skeleton>`
- `components/forms/SetPasswordForm.tsx:60` — `<div>Token is invalid or expired</div>` → `<Alert>`

---

## Missing Components to Install

```bash
npx shadcn@latest add spinner alert select command popover
```

| Component | Why needed |
|---|---|
| `spinner` | Loading states on all buttons (currently inlined text or custom divs) |
| `alert` | Error/info callouts (token invalid state, empty states) |
| `select` | Not installed yet — likely needed for future forms |
| `command` + `popover` | Proper replacement for `AddMemberDropdown` custom combobox |

---

## Priority Summary

| Priority | Issue | Files affected |
|---|---|---|
| High | `space-y-*` → `gap-*` | LoginForm, SetPasswordForm, GroupsTableColumn |
| High | `text-red-500` → `<FieldError>` | LoginForm, SetPasswordForm, InviteUserDialog |
| High | Button loading text → Spinner | 8+ components |
| High | `w-N h-N` → `size-N` on icons | GroupsTableColumn, UsersTableColumn |
| High | Missing `data-icon` on Button icons | InviteUserDialog, GroupsTableColumn |
| Medium | `<Label>` → `<FieldLabel>` inside `<Field>` | InviteUserDialog |
| Medium | `FieldGroup`+`Field` in login/set-password forms | LoginForm, SetPasswordForm |
| Medium | Icon library mismatch (@tabler vs lucide) | InviteUserDialog |
| Medium | Custom spinner → `<Spinner>` | GroupsTableColumn |
| Low | Custom combobox → Command+Popover | GroupsTableColumn |
| Low | Add missing components (spinner, alert, select, command, popover) | — |
