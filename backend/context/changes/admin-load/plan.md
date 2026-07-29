# Admin Bootstrap Console Command Implementation Plan

## Overview

Add a console command, `app:admin:bootstrap`, that creates a `ROLE_ADMIN` user and prints a working `/set-password?token=...` invitation link. This gives the operator a way back into a freshly-wiped live database without needing an existing admin to send an invitation — today the invitation flow can only grant `ROLE_USER`, and the only `ROLE_ADMIN` account in the system comes from a dev/test-only fixture.

## Current State Analysis

- **No custom console command exists anywhere in this codebase** — `src/Command/` doesn't exist, no `#[AsCommand]` anywhere (`context/changes/admin-load/research.md`, "Console commands"). `config/services.yaml:16-17` has `autowire: true` / `autoconfigure: true`, so a new `#[AsCommand]` class needs no manual service registration.
- **`ROLE_ADMIN` is never assignable through the invitation flow today.** `Admin\UserController::sendUserInvite()` hard-codes `$newUser->setRoles(['ROLE_USER'])` (`src/Controller/Admin/UserController.php:87`). The only admin account (`user_admin`) comes from `fixtures/users.yaml:1-7`, an Alice fixture with a plaintext password and no invitation token — dev/test only.
- **Token-creation logic is private to the controller.** `Admin\UserController::createToken()` (`src/Controller/Admin/UserController.php:131-141`) is the only place `UserInvitationToken` is constructed:
  ```php
  $rawToken = bin2hex(random_bytes(32));
  $token = new UserInvitationToken(token: hash('sha256', $rawToken), email: $email);
  $this->entityManager->persist($token);
  return [$rawToken, $token];
  ```
  It is called from both `sendUserInvite()` and `resendUserInvite()`. A second real call site (the new command) crosses the DRY threshold — extracting it into a small service is worth the one-time refactor cost.
- **`UserInvitationToken`** (`src/Entity/UserInvitationToken.php`) has no FK to `User` — it's resolved by matching the `email` string at completion time. Its constructor hard-sets `expiresAt = new \DateTimeImmutable('+1 day')` (line 34).
- **`UserAlreadyExistsException`** (`src/Exception/UserAlreadyExistsException.php`) already implements `ApiExceptionInterface` (400, `USER_ALREADY_EXISTS`) and extends `\RuntimeException` — safe to reuse in a CLI context; the Console component will catch it, print its message, and exit non-zero.
- **`InvitationMailer::sendInvitation()`** (`src/Service/InvitationMailer.php:22-33`) builds the link as `{FRONTEND_URL}/set-password?token={rawToken}` (`FRONTEND_URL` env var, `#[Autowire(env: 'FRONTEND_URL')]`) — this is the exact link format the command must print.
- **`User::setRoles()`** takes a plain `array` (`src/Entity/User.php:116-121`); `getRoles()` always appends `ROLE_USER` on top, so `setRoles(['ROLE_ADMIN'])` is sufficient. `status` defaults to `UserStatusEnum::NEW` (`src/Entity/User.php:58`), which is exactly the state `sendUserInvite()` leaves a new user in — no explicit `setStatus()` call needed. `password` stays `null` until the invitation is completed via `InvitationController::complete()`.
- **Existing test infrastructure supports command tests directly.** `tests/DatabaseTestCase.php` already drives a Symfony `Application` against the test kernel/database (`Symfony\Bundle\FrameworkBundle\Console\Application` + `StringInput`), and other functional tests extend it (`tests/Functional/Repository/UserInvitationTokenRepositoryTest.php`). The new command's test will follow the same base class and use `Symfony\Component\Console\Tester\CommandTester` to invoke the command directly.

## Desired End State

Running `bin/console app:admin:bootstrap <email>` against an empty (freshly migrated) database creates a `User` with `roles: ['ROLE_ADMIN']`, `status: NEW`, no password, persists a valid `UserInvitationToken` for that email, and prints the email plus a `{FRONTEND_URL}/set-password?token=...` link the operator can open to set a password and log in. Running it again with an email that already has a `User` row fails loudly (non-zero exit, `UserAlreadyExistsException` message) and creates nothing.

**Verification**: a new functional test exercises both the success path (user created, token persisted, link printed) and the failure path (existing email rejected); `Admin\UserControllerTest`'s existing invitation-flow assertions still pass unchanged after the `InvitationTokenService` extraction.

### Key Discoveries:

- `Admin\UserController::createToken()` (`src/Controller/Admin/UserController.php:131-141`) is the complete, tested reference implementation for token creation — the new service is a straight extraction, not a redesign.
- `DatabaseTestCase` (`tests/DatabaseTestCase.php`) already wires up a `Symfony\Bundle\FrameworkBundle\Console\Application` against the test kernel — the new command test reuses this pattern instead of inventing a new one.
- `config/services.yaml`'s `autowire: true, autoconfigure: true` means both the new service and the new command need no manual `services.yaml` entries.

## What We're NOT Doing

- **Not automating the database wipe itself** — this plan only covers what happens *after* a wipe (creating an admin), not the wipe/reset step.
- **Not wiring this into the deploy workflow** (`.github/workflows/backend-deploy.yml`) — per the change's mechanism decision, this is a manually-run recovery command, not an auto-seed-on-deploy step.
- **Not adding a `--force` flag or interactive confirmation** — the email argument is the only input; the existing-user check (`UserAlreadyExistsException`) is the guardrail against accidental re-use on a non-empty database.
- **Not sending an email** — the command prints the link to the console only, so it has no dependency on a working mail transport during recovery.
- **Not supporting upsert/reset of an existing user** — if the email already exists, the command fails; it never mutates an existing account's roles or re-issues a token for it.
- **Not changing `sendUserInvite()`/`resendUserInvite()`'s behavior or response shape** — the controller refactor in Phase 1 is a pure extraction; both endpoints must behave identically before and after.

## Implementation Approach

Extract the existing, working token-creation logic into a small service first (Phase 1), proving it against the untouched controller behavior via the existing test suite. Then add the new console command on top of that service (Phase 2), so the command and the two existing invitation endpoints share one code path for token generation instead of duplicating security-sensitive logic (raw-token length, hash algorithm, expiry).

## Phase 1: Extract InvitationTokenService

### Overview

Move `Admin\UserController::createToken()` into a new injectable service with no behavior change, then update the controller to call it. This is a pure refactor — existing functional tests must pass unchanged.

### Changes Required:

#### 1. New service

**File**: `src/Service/InvitationTokenService.php`

**Intent**: Give both the existing invitation endpoints and the new console command (Phase 2) a single, shared place that creates a `UserInvitationToken` for an email, so the raw-token/hash/expiry logic can't drift between the two call sites.

**Contract**: `createToken(string $email): array{string, UserInvitationToken}` — identical body to today's `Admin\UserController::createToken()` (`src/Controller/Admin/UserController.php:131-141`): generates `bin2hex(random_bytes(32))`, persists a `UserInvitationToken` with `hash('sha256', $rawToken)`, returns `[$rawToken, $token]`. Constructor takes `EntityManagerInterface`. Method still throws `\Random\RandomException` (propagated, not caught).

#### 2. Controller uses the service

**File**: `src/Controller/Admin/UserController.php`

**Intent**: Replace the private `createToken()` method with an injected `InvitationTokenService`, so `sendUserInvite()` and `resendUserInvite()` call `$this->invitationTokenService->createToken($email)` instead of `$this->createToken($email)`. Remove the now-unused private method.

**Contract**: Constructor gains `private readonly InvitationTokenService $invitationTokenService`. Both call sites (`src/Controller/Admin/UserController.php:91`, `:118`) change from `$this->createToken(...)` to `$this->invitationTokenService->createToken(...)`. No route, request/response shape, or status code changes.

### Success Criteria:

#### Automated Verification:

- Full test suite passes unchanged: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit`
- `Admin\UserControllerTest`'s existing `sendUserInvite`/`resendUserInvite` assertions pass with no changes to the test file itself

---

## Phase 2: Add `app:admin:bootstrap` console command

### Overview

Add the new command that creates a `ROLE_ADMIN` user and prints an invitation link, using `InvitationTokenService` from Phase 1.

### Changes Required:

#### 1. The command

**File**: `src/Command/AdminBootstrapCommand.php`

**Intent**: A one-shot recovery command: given an email, create a `ROLE_ADMIN` user in `NEW` status with no password, generate a real invitation token for it via `InvitationTokenService`, and print the resulting `/set-password?token=...` link so the operator can complete registration through the existing frontend flow.

**Contract**: `#[AsCommand(name: 'app:admin:bootstrap', description: 'Create a ROLE_ADMIN user and print an invitation link (recovery tool for a wiped database).')]`, extends `Symfony\Component\Console\Command\Command`. One required argument: `email`. Constructor-injects `UserRepository`, `EntityManagerInterface`, `InvitationTokenService`, and `#[Autowire(env: 'FRONTEND_URL')] string $frontendUrl` (same env var `InvitationMailer` uses).

Execution logic:
- Validate `email` with `filter_var($email, FILTER_VALIDATE_EMAIL)`; on failure, write an error and return `Command::FAILURE`.
- If `UserRepository::findOneBy(['email' => $email])` returns an existing user, throw `UserAlreadyExistsException` (reused as-is — the Console component prints its message and exits non-zero).
- Otherwise: `new User()`, `setEmail($email)`, `setRoles(['ROLE_ADMIN'])`, `persist()`; call `InvitationTokenService::createToken($email)`; `flush()`.
- Print the email and the link `{$frontendUrl}/set-password?token={$rawToken}` (mirroring `InvitationMailer::sendInvitation()`'s URL construction, `src/Service/InvitationMailer.php:24`); return `Command::SUCCESS`.

#### 2. Functional test

**File**: `tests/Functional/Command/AdminBootstrapCommandTest.php`

**Intent**: Cover both the success path and the existing-user rejection path end-to-end against the real test database, following the existing `DatabaseTestCase` pattern used by other functional tests.

**Contract**: Extends `App\Tests\DatabaseTestCase`; uses `Symfony\Component\Console\Tester\CommandTester` against the command fetched from the booted kernel's `Application` (same `Application` construction pattern as `tests/DatabaseTestCase.php:31`). Assertions:
- Running with a new email creates a `User` with `roles === ['ROLE_ADMIN']` and `status === UserStatusEnum::NEW`, persists a matching `UserInvitationToken` (`expiresAt` roughly `+1 day` from now, `usedAt === null`), and the command's output contains `/set-password?token=`.
- Running a second time with the same email returns a non-zero exit code and does not create a second `User` row.

### Success Criteria:

#### Automated Verification:

- Full test suite passes: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit`
- New `AdminBootstrapCommandTest` assertions pass (success path creates `ROLE_ADMIN` user + valid token + printed link; re-run on same email fails with no duplicate row)
- Only one place in `src/` constructs a raw invitation token: `grep -rn "bin2hex(random_bytes(32))" src/` returns exactly `src/Service/InvitationTokenService.php`

#### Manual Verification:

- Run `docker compose run --rm php bin/console app:admin:bootstrap you@example.com` against a freshly migrated (empty) local database, open the printed link in the frontend, set a password, and confirm you can log in and reach an admin-only endpoint (e.g. `GET /admin/users`)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding.

---

## Testing Strategy

### Unit Tests:

- Not applicable — `InvitationTokenService` and `AdminBootstrapCommand` are both thin, DB-touching classes better covered by functional tests against the real (test) database, consistent with how `UserInvitationTokenRepositoryTest` is tested today.

### Integration Tests:

- Phase 1: full functional suite re-run proves the extraction didn't change `sendUserInvite`/`resendUserInvite` behavior.
- Phase 2: new `AdminBootstrapCommandTest` covers success + duplicate-email failure against the real test database via `DatabaseTestCase`.

### Manual Testing Steps:

1. After Phase 2: run the command against a freshly wiped/migrated local database, follow the printed link, set a password, log in, and confirm admin access.

## Migration Notes

Purely additive — no schema or migration changes. `UserInvitationToken` and `User` are used exactly as they exist today.

## References

- Related research: `context/changes/admin-load/research.md`
- Reference implementation for extraction: `src/Controller/Admin/UserController.php:77-141`
- Link format to mirror: `src/Service/InvitationMailer.php:22-33`
- Test pattern to follow: `tests/DatabaseTestCase.php`, `tests/Functional/Repository/UserInvitationTokenRepositoryTest.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Extract InvitationTokenService

#### Automated

- [x] 1.1 Full test suite passes unchanged
- [x] 1.2 Admin\UserControllerTest's existing sendUserInvite/resendUserInvite assertions pass with no changes to the test file itself

### Phase 2: Add app:admin:bootstrap console command

#### Automated

- [ ] 2.1 Full test suite passes
- [ ] 2.2 New AdminBootstrapCommandTest assertions pass (success path + duplicate-email failure)
- [ ] 2.3 Only one place in src/ constructs a raw invitation token (grep check)

#### Manual

- [ ] 2.4 Run app:admin:bootstrap against a freshly migrated local database, follow the printed link, set a password, log in, and confirm admin access
