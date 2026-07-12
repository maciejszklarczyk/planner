# Repository Guidelines

Planner API — a Symfony 7.4 / PHP 8.4 backend (FrankenPHP, Doctrine ORM 3.6, PostgreSQL 16, Redis 7) serving a separate Next.js frontend at `../frontend`. No `/api/` prefix on routes.

## Hard rules

- Routes carry no `/api/` segment (`/user/activity-logs`, not `/api/user/activity-logs`); Swagger at `/api/doc` is the sole exception.
- Functional tests need `.env.test` loaded or `createClient()` throws `LogicException` — see `@CLAUDE.md` for the CLI invocation.
- `phpunit.dist.xml` is live config — `phpunit.xml.dist` is a stale leftover, don't edit it.
- Tests authenticate via `X-Dev-User: email@example.com` (`HTTP_X_DEV_USER`), not `/auth/login`.
- `User`/`Group` are Gedmo `SoftDeleteable` — never hard-delete these rows.
- `composer-audit` fails CI on any unpatched advisory; add unfixable ones to `composer.json`'s `config.audit.ignore` with a tracking-issue link rather than disabling the job.

## Project Structure & Module Organization

`src/Controller` (HTTP, + `Admin/`), `src/Dto` (request DTOs, + `Response/`), `src/Entity` (Doctrine, + `Enum/`), `src/Exception`, `src/Repository`, `src/Security` (`DevHeaderAuthenticator`, voters), `src/Service` (business logic). Tests mirror this under `tests/{Functional,Unit}`. Full architecture: `@CLAUDE.md` (this dir), `@../CLAUDE.md` (monorepo root, covers the frontend contract).

## Build, Test, and Development Commands

- `docker compose up` — start the dev stack (FrankenPHP on :8000).
- `composer run-tests` — PHPUnit against `tests/`, requires `APP_ENV=test`.
- `composer cs-fix-analyse` / `composer cs-fix` — PHP CS Fixer dry-run / apply.
- `composer phpstan` — static analysis on `src` + `tests`.
- `composer run-all` — cs-fix-analyse + phpstan + run-tests, the local pre-push gate.

## Coding Style & Naming Conventions

PSR-12 + `@Symfony` ruleset via PHP CS Fixer (`.php-cs-fixer.dist.php`), `declare_strict_types` enforced. Routing, enum, and Doctrine naming conventions: `@CLAUDE.md`.

## Testing Guidelines

PHPUnit 12, tests under `tests/Functional` and `tests/Unit`. Config, CLI invocation, and PhpStorm setup: `@CLAUDE.md`. Fixtures are deterministic — prefer data providers over repeating near-identical test cases.

## Commit & Pull Request Guidelines

Conventional Commits, scoped to the change-id when one exists: `feat(events): add endDate/location fields`, `fix(cicd-rework): install curl in deploy-production image`, `ci: refine coverage regex`. Merges land via `Merge branch '<slug>' into 'main'`. GitLab CI gate (`.gitlab-ci.yml`) runs `phpunit`, `php-cs-fixer`, `composer-audit`, `lint` on every branch and `main` push; production deploy only fires on a `vX.Y.Z` tag push.
