# CLAUDE.md — Planner (project root)

Project structure, layers, dev commands: `@AGENTS.md`. Layer-specific instructions: `backend/CLAUDE.md`, `frontend/CLAUDE.md`.

## Key business constraints

- Cannot remove the last owner of a group — backend throws `CannotRemoveLastOwnerException`
- Cannot remove a user with the `owner` role from the frontend (condition: `role !== 'owner'`)
- Cannot remove a user if they're the sole member of a group (condition: `members.length > 1`)
- Soft delete on `User` and `Group` entities (`deletedAt` field)
- Login rate limiting: 3 attempts / 15 min
- Invitation token: `bin2hex(random_bytes(32))`, valid for 1 day

## Dev auth (dev/test only)

Instead of logging in: header `X-Dev-User: email@example.com`.
In PHP tests: `HTTP_X_DEV_USER`. Handled by `DevHeaderAuthenticator`.

## API URL

- Frontend → `http://localhost:8000` (env: `NEXT_PUBLIC_API_URL`)
- `lib/api.ts` does **not** add an `/api` prefix — endpoints are called without it
- Backend exposes routes **without** `/api/` (e.g. `/admin/users`, `/events`, `/auth/login`)
- Exception: Swagger at `/api/doc` (Nelmio)

## Running (dev)

```bash
# All-in-one:
./herald/herald.sh up

# frontend: http://localhost:3000
# backend:  http://localhost:8000
# API docs: http://localhost:8000/api/doc
```

## Stack

Versions and exact dependency list: `@frontend/package.json`, `@backend/composer.json`. API pattern (full endpoint list): Swagger at `/api/doc`. Auth: HTTP sessions (`credentials: 'include'`), Symfony `json_login` at `/auth/login`.
