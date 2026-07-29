---
date: 2026-07-29T19:05:54+02:00
researcher: Claude
git_commit: a466b441dfe449ae64487cb6ba37888dc0455607
branch: ci-drop-push-trigger
repository: planner
topic: "Bootstrap an admin account on the live server after a database wipe"
tags: [research, codebase, admin, invitation, console-command, deploy]
status: complete
last_updated: 2026-07-29
last_updated_by: Claude
---

# Research: Bootstrap an admin account on the live server after a database wipe

**Date**: 2026-07-29T19:05:54+02:00
**Researcher**: Claude
**Git Commit**: a466b441dfe449ae64487cb6ba37888dc0455607
**Branch**: ci-drop-push-trigger
**Repository**: planner

## Research Question

The user wipes the live-server database fairly often (project is still test-status) and then has no way to log back in, since the app is invitation-based and there's no admin account left to send invitations. They want a way to bootstrap an admin account after a wipe. Scoping answers from the user: mechanism = a CLI console command; auth = reuse the existing invitation flow rather than setting a password directly.

## Summary

- **No custom Symfony console command exists in this codebase at all** — `src/Command/` doesn't exist, no `#[AsCommand]` anywhere. A new admin-bootstrap command would be the first of its kind.
- **`ROLE_ADMIN` is never assignable through the existing invitation flow.** `Admin\UserController::sendUserInvite()` hard-codes `setRoles(['ROLE_USER'])`. Today the only admin account in the system (`user_admin`) is created by an Alice fixture (`fixtures/users.yaml`) with a plaintext password, entirely bypassing invitations.
- **Invitation-token creation logic is not extracted into a service** — it's a private method (`createToken()`) on `Admin\UserController`. To "reuse the invitation flow" from a console command, that logic needs to be duplicated or (cleaner) extracted into a small injectable service so both the controller and the new command call the same code.
- **There is a real, working prod deploy pipeline** (self-hosted GitHub Actions runner → `docker compose -f docker-compose.prod.yaml run/exec php bin/console ...`) with a clear precedent for running one-off `bin/console` commands against the live container (migrations step). Running a new admin-bootstrap command manually on the live server is well supported.
- **No database-wipe automation exists anywhere** (dev or prod) — only migrations (prod) and fixtures-load (dev, via `herald db-reset`). The wipe itself is presumably done manually by the user against the Postgres volume; this research doesn't change that, it only covers what happens *after* a wipe.
- **No auto-seed/post-deploy hook infrastructure exists.** "Auto-seed on deploy" (the option not chosen) would require adding an explicit, idempotent step to `.github/workflows/backend-deploy.yml` — there's no hook mechanism that would pick this up automatically.

Given the user's answers (CLI command + reuse invitation flow), the shape that fits existing conventions is: a new `#[AsCommand]` class under `src/Command/` that creates a `User` with `roles: ['ROLE_ADMIN']` and `status: NEW`, then generates a `UserInvitationToken` the same way `Admin\UserController::createToken()` does, and prints the resulting `{FRONTEND_URL}/set-password?token=...` link to the console (rather than emailing it, since `InvitationMailer` requires a working SMTP config not guaranteed to be desirable to trigger from an ad-hoc bootstrap). Run on the live server via `docker compose -f docker-compose.prod.yaml exec -T php php bin/console app:admin:bootstrap <email>`, following the exact pattern already used for the migrations step in the deploy workflow.

## Detailed Findings

### Console commands

- **No `src/Command/` directory exists**; no `#[AsCommand]` anywhere in `src/`. Only framework/vendor commands are registered (`doctrine:fixtures:load`, `doctrine:migrations:migrate`, Alice's fixture loader, etc.). A new admin-bootstrap command is the first custom command in this app.

### `User` entity — `src/Entity/User.php`

- Implements `UserInterface`, `PasswordAuthenticatedUserInterface`; uses Gedmo `SoftDeleteableEntity` trait (`deletedAt` comes from the trait, not an explicit property).
- Relevant fields: `email` (unique), `roles` (plain `array` column — `setRoles(['ROLE_ADMIN'])` is exactly how the fixture does it; `getRoles()` always appends `ROLE_USER` and dedupes, `src/Entity/User.php:99-106`), `password` (nullable hashed string), `status` (`UserStatusEnum`: `NEW | ACTIVE | INACTIVE | BLOCKED | DELETED`), `name` (nullable), `addedBy` (self-referencing, nullable).
- Constructor only initializes `userHasGroups = new ArrayCollection()` — no required args; `new User()` + setters is the pattern.
- `ROLE_ADMIN` (global Symfony security role via `User::$roles`) is a **completely separate axis** from `UserGroupRoleEnum::OWNER` (per-group membership role on `UserHasGroup`). Group ownership grants no system-wide privilege; only `roles` matters for a "system admin" bootstrap.

### Password hashing pattern

Two existing call sites of `UserPasswordHasherInterface`, both usable as templates:

- `src/Controller/InvitationController.php:74-77` — the flow this command should mirror: `$user->setPassword($this->passwordHasher->hashPassword($user, $dto->password)); $user->setStatus(UserStatusEnum::ACTIVE);` — but since we're reusing the *invitation* flow (user picks their own password via the frontend `/set-password` page), the new admin-bootstrap command does **not** hash a password itself — it leaves `password` null and `status: NEW`, exactly like `sendUserInvite()` does, and lets the existing `InvitationController::complete()` action fill in the password later when the operator completes the invitation link.
- `src/DataFixtures/Processor/UserPasswordProcessor.php:18-29` — Alice fixture processor pattern (not directly relevant to a console command, but confirms the hashing call shape).

### Invitation flow — token generation, storage, completion

- `Admin\UserController::createToken(string $email): array` (private, `src/Controller/Admin/UserController.php:131-141`) is the exact logic to reuse:
  ```php
  $rawToken = bin2hex(random_bytes(32));
  $token = new UserInvitationToken(token: hash('sha256', $rawToken), email: $email);
  $this->entityManager->persist($token);
  return [$rawToken, $token];
  ```
  Caller must still call `flush()` afterward — not done inside `createToken()`.
- `UserInvitationToken` entity (`src/Entity/UserInvitationToken.php`): fields `token` (unique, sha256 hash stored), `email` (plain string — **no FK to `User`**, resolved by matching email string at completion time), `expiresAt` (hard-set to `new \DateTimeImmutable('+1 day')` in the constructor), `usedAt` (nullable). Repository has `findActiveByEmail()` (`usedAt IS NULL AND expiresAt > now`).
- `Admin\UserController::sendUserInvite()` (`src/Controller/Admin/UserController.php:77-97`) is the full reference flow to mirror for the new command:
  1. Reject if a `User` with that email already exists (`UserAlreadyExistsException`).
  2. `new User()`, `setEmail`, **`setRoles(['ROLE_USER'])`** ← this is the one line to change to `['ROLE_ADMIN']` for the bootstrap command, `setAddedBy($currentUser)` (bootstrap command has no "current user" — likely omit or leave null), `persist`.
  3. `createToken($email)`, `flush()`.
  4. `invitationMailer->sendInvitation($email, $rawToken)` — **optional** for a CLI bootstrap; printing the link directly avoids depending on a working mail transport during a disaster-recovery scenario.
- **No reusable service exists today** — `createToken()` is private to the controller. Either duplicate the ~10 lines in the new command, or extract a small `InvitationService`/`AdminBootstrapService` both can call. Given this is a small, contained bit of logic and the project's existing pattern keeps invitation logic in the controller, duplicating a few lines in the command is consistent with current conventions; extracting a service is the more "correct" DRY option and worth deciding at plan time.
- Frontend link format (from `InvitationMailer::sendInvitation`, `src/Service/InvitationMailer.php:24`): `{FRONTEND_URL}/set-password?token={rawToken}` (`FRONTEND_URL` env var). Link is documented as valid 24 hours in the email copy.
- **Confirmed**: `ROLE_ADMIN` cannot be granted anywhere in the existing invitation flow — `sendUserInvite()` hard-codes `ROLE_USER`. The single existing admin account is only ever created via the `fixtures/users.yaml` Alice fixture, with a plaintext password and no invitation token at all.

### Production deploy & how to run a one-off command on the live server

- Deploy is via a self-hosted GitHub Actions runner (`.github/workflows/backend-deploy.yml`), triggered on `release: published` or manual `workflow_dispatch`. The `deploy` job runs from `/home/maciej/docker/stacks/planner/backend` and, notably, already runs a one-off `bin/console` invocation for migrations:
  ```
  docker compose -f docker-compose.prod.yaml run --rm php php bin/console doctrine:migrations:migrate --no-interaction
  ```
  and cache-clear against the running container:
  ```
  docker compose -f docker-compose.prod.yaml exec -T php php bin/console cache:clear --env=prod
  ```
  This is the exact pattern to reuse for the new command — either as a manual, ad-hoc step run by the user over SSH after a wipe:
  ```
  docker compose -f docker-compose.prod.yaml exec -T php php bin/console app:admin:bootstrap <email>
  ```
  or (if the user later wants it automatic) as a new idempotent step added to `backend-deploy.yml` right after the migrate step.
- Container naming differs between dev and prod: dev is `planner-php` (`herald/herald.sh` uses this for `db-reset`), prod is `plan-php` (`docker-compose.prod.yaml` container name, default `PROJECT_NAME=plan`).
- Prod `php` service runs with `APP_ENV=prod`, `APP_DEBUG=0`, and an untracked `.env` file in the deploy dir supplies `DATABASE_URL`/`FRONTEND_URL`/etc. — a command run via `exec`/`run` automatically inherits this, connecting to the live Postgres without extra config.
- No database-wipe automation exists anywhere in the repo today (dev fixtures-load via `herald db-reset` is the closest analog, but that's dev-only and doesn't wipe — it loads fixtures on top). The user's wipe is presumably manual (e.g. dropping the `database_data` volume or running SQL directly) — out of scope for this research, but worth noting the new command has to be safe to run **after an empty database with fresh migrations applied**, i.e. it can assume zero existing users.
- **AGENTS.md constraint** (`AGENTS.md:16`): any new GitHub Actions job/step must stay on `workflow_dispatch`/`release` triggers with `runs-on: [self-hosted, linux]` — relevant only if the command is later wired into the deploy workflow automatically rather than run manually.
- **Documentation drift noted**: `backend/AGENTS.md:24` references `.gitlab-ci.yml`, which doesn't exist — actual CI/CD is GitHub Actions. Not related to this change, but worth a future cleanup.

## Code References

- `src/Controller/Admin/UserController.php:77-97` — `sendUserInvite()`, the primary template to adapt (change `ROLE_USER` → `ROLE_ADMIN`, drop the "already exists" mail-send assumption as needed)
- `src/Controller/Admin/UserController.php:131-141` — `createToken()`, exact token-generation logic to reuse/duplicate
- `src/Controller/InvitationController.php:34-80` — `verify()`/`complete()`, confirms the token lookup/expiry/used checks and the password-hash-on-completion pattern
- `src/Entity/UserInvitationToken.php:13-35` — token entity, `+1 day` expiry set in constructor
- `src/Entity/User.php:34-38,64-67,99-121` — `roles` field, constructor, `getRoles()`/`setRoles()`
- `src/Service/InvitationMailer.php:22-33` — link format `{FRONTEND_URL}/set-password?token=...`
- `fixtures/users.yaml:1-7` — only existing example of an admin user's field values (`roles: ['ROLE_ADMIN']`, `status: active`)
- `.github/workflows/backend-deploy.yml:44-78` — deploy job; line 73 (migrate) and line 76 (cache:clear) are the precedent for running `bin/console` against the live container
- `backend/docker-compose.prod.yaml:1-77` — prod service definitions, container naming (`plan-php`), env file wiring
- `herald/herald.sh:24-26` — dev-only `db-reset`, for contrast with the (nonexistent) prod wipe/reset flow

## Architecture Insights

- The codebase deliberately keeps invitation logic inside `Admin\UserController` rather than a service — consistent with the project's generally thin-service/fat-controller-for-simple-CRUD style seen elsewhere, though `backend/CLAUDE.md` also documents a "Domain exceptions... thrown from services" convention, suggesting service extraction is the more idiomatic direction for anything reused outside a single controller (like a new console command).
- `ROLE_ADMIN` (system-wide) and `UserGroupRoleEnum::OWNER` (per-group) are cleanly separated concerns — no risk of conflating them when designing the bootstrap command.
- The prod deploy pipeline already treats `bin/console` one-offs as a first-class pattern (migrations, cache-clear), so adding one more command — whether run manually or wired into the workflow — fits existing conventions well.

## Historical Context (from prior changes)

- `context/archive/2026-07-22-api-exception-handling/` — recently landed global exception infrastructure (`ApiExceptionInterface`, `kernel.exception` listener). A new command should throw/report errors consistent with that pattern where applicable (e.g. reuse `UserAlreadyExistsException` if bootstrapping onto an email that already exists), though console commands render errors differently from HTTP responses so this mostly matters if the command shares code paths with the HTTP controller.
- `context/changes/friendship-requests/` — unrelated domain work, no overlap with admin bootstrapping.
- No prior change in `context/changes/**` or `context/archive/**` addresses admin bootstrapping, database wipes, or console commands — this is new territory for the project.

## Related Research

- None — first research artifact touching console commands / prod admin bootstrap.

## Open Questions

1. **Idempotency / re-run safety**: if the command is ever run against a non-empty database (e.g. accidentally run twice, or run without a wipe), should it fail cleanly (email already exists → reuse `UserAlreadyExistsException`) or upsert? Fixed at plan time.
2. **Extract `createToken()` into a shared service, or duplicate the ~10 lines in the command?** Duplication matches current controller-centric style; extraction is more idiomatic per `backend/CLAUDE.md`'s stated service conventions. Worth deciding explicitly in the plan rather than defaulting silently.
3. **Print the link vs. send the email**: printing avoids a mail-transport dependency during disaster recovery (the whole point of this command is "things are broken, I need back in"), but means the operator must copy/paste it manually. Should the command support both (e.g. a `--mail` flag), or print-only is fine for now?
4. **Command name/namespace**: no existing `app:*` command convention to follow (this is the first). Suggest `app:admin:bootstrap` or `app:user:create-admin` — decide at plan time.
5. **Should the command require confirmation or a `--force` flag** given it grants `ROLE_ADMIN`, to avoid accidental misuse if ever run against a live, non-wiped database?
