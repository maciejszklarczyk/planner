# Repository Guidelines

Planner (EventPlanner4000) is a monorepo: a Symfony 7.4 / PHP 8.4 API (`backend/`, FrankenPHP, Doctrine ORM, PostgreSQL, Redis) and a Next.js 16 / React 19 / TypeScript frontend (`frontend/`), talking over plain HTTP with session-cookie auth.

## Roles

- User roles: `ROLE_USER` (any logged-in user), `ROLE_ADMIN` (access to `/admin/*`).
- Group roles: `owner`, `member` — see the "last owner" and "sole member" rules below.

## Hard rules

- Backend routes never carry an `/api/` prefix (`/user`, `/events`, `/admin/groups/{id}`); Swagger at `/api/doc` is the sole exception.
- Never hard-delete `User` or `Group` rows — both are soft-deleted (`deletedAt`), enforced by Gedmo `SoftDeleteable`.
- The last `owner` of a group can never be removed (`CannotRemoveLastOwnerException`); the frontend additionally blocks removing any `owner` or the last remaining member of a group.
- In dev/test, authenticate via the `X-Dev-User: email@example.com` header (`DevHeaderAuthenticator`), not `/auth/login`.
- Repo is public with a self-hosted GitHub Actions runner for deploys — only add `workflow_dispatch`/`release`-triggered jobs to `runs-on: [self-hosted, linux]`, never `pull_request`/`pull_request_target`.

## Project Structure & Module Organization

`backend/` (Symfony API, see `@backend/AGENTS.md` and `@backend/CLAUDE.md`), `frontend/` (Next.js UI, see `@frontend/CLAUDE.md`), `herald/` (dev CLI wrapping both Docker Compose stacks, `@herald/README.md`), `bruno/` (manual API-testing collection), `docs/codebase/` (deep-dive architecture docs), `context/` (change-tracking for agent-assisted work). Business rules and dev-auth convention: `@CLAUDE.md`. Full API surface: Swagger at `/api/doc`.

## Build, Test, and Development Commands

- `./herald/herald.sh up` — start both stacks (backend on :8000, frontend on :3000).
- `herald test` / `herald db-reset` / `herald status` — run backend tests, reload fixtures, check container status.
- Backend: `composer run-all` (CS Fixer + PHPStan + PHPUnit, the local pre-push gate).
- Frontend: `npm run lint`, `npx tsc --noEmit`, `npm run test`, `npm run build`.

## Coding Style & Naming Conventions

Backend: PSR-12 + Symfony ruleset via PHP CS Fixer, PHPStan level 6, `#[Route(...)]` attribute routing (not YAML). Frontend: TypeScript `strict: true`, ESLint 9 + Prettier, all `useMutation`/`useQuery` calls live in `hooks/` — never inline in components.

## Testing Guidelines

Backend: PHPUnit 12 under `tests/{Functional,Unit}`, requires `.env.test` (`composer run-tests`). Frontend: Vitest + Testing Library, `*.test.ts(x)` colocated with the unit under test.

## Commit & Pull Request Guidelines

Conventional Commits, scoped to the change-id when one exists (`fix(deploy-migration): ...`, `feat(cicd-migration): ...`). `main` requires all of `php-cs-fixer`, `phpstan`, `phpunit`, `composer-audit`, `lint`, `secret-scan` (backend) and `quality-checks` (frontend) to pass before merge — branch protection is enforced but repo admins are exempt.
