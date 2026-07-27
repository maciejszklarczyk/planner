# Planner (EventPlanner4000)

Monorepo: Symfony API backend + Next.js frontend for group event planning.

## Structure

| Path | What | Docs |
|------|------|------|
| `backend/` | Symfony API (FrankenPHP, port 8000) | [backend/README.md](backend/README.md), [backend/CLAUDE.md](backend/CLAUDE.md) |
| `frontend/` | Next.js UI (port 3000) | [frontend/README.md](frontend/README.md), [frontend/CLAUDE.md](frontend/CLAUDE.md) |
| `herald/` | Dev CLI wrapping both Docker Compose stacks | [herald/README.md](herald/README.md) |
| `bruno/` | Bruno API collection for manual endpoint testing | — |
| `docs/` | Product docs (vision, features, user stories) + `docs/codebase/` deep-dive | — |
| `context/` | Change-tracking (`changes/`, `archive/`, `foundation/`) for AI-assisted work | — |

Full stack, API URL conventions, roles, and business rules: see root [CLAUDE.md](CLAUDE.md).

## Quick start (dev)

```bash
./herald/herald.sh up
```

Starts both stacks. See [herald/README.md](herald/README.md) for the full command list (`status`, `db-reset`, `test`, `health`, ...), or `backend/README.md` / `frontend/README.md` to run either stack standalone.

- Frontend: http://localhost:3000
- Backend: http://localhost:8000
- API docs (Swagger): http://localhost:8000/api/doc

## License

Proprietary
