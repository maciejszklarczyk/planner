<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Global API Exception-Handling Infrastructure

- **Plan**: context/changes/api-exception-handling/plan.md
- **Scope**: Full plan (Phase 1 + Phase 2)
- **Date**: 2026-07-23
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Findings

### F1 — PHPStan will fail CI: missing iterable value types on ApiErrorEnvelopeFactory::build()

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: src/Service/ApiErrorEnvelopeFactory.php:11
- **Detail**: Verified by running `vendor/bin/phpstan analyse` (level 6, per `phpstan.dist.neon`) against the changed files: `build(string $errorCode, string $message, Request $request, ?array $violations = null): array` reports 2 `missingType.iterableValue` errors — the `$violations` param and the `array` return type both need value-type PHPDoc. This repo's CI has a dedicated PHPStan job (added in commit `949c0f8`, "wire PHPStan into CI"), so this is a genuine CI-breaker, not a hypothetical.
- **Fix**: Add PHPDoc to the method: `@param list<array{field: string, message: string}>|null $violations` and `@return array{error: string, message: string, timestamp: string, path: string, violations?: list<array{field: string, message: string}>}`.
- **Decision**: FIXED — 3aa35a6

### F2 — Pre-existing staged deletion of trip-domain-model files swept into Phase 1 commit

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: commit 1d02374 (context/changes/trip-domain-model/{change.md,frame.md})
- **Detail**: Before this autonomous run started, `context/changes/trip-domain-model/change.md` and `frame.md` were already staged for deletion (visible in the session's initial `git status`) — leftover from the prior `/10x-plan` session that split `trip-domain-model` into `friendship-requests` + `api-exception-handling` (per this change's own `change.md` notes). The Phase 1 commit's `git commit` picked up everything staged, not just the phase's touched-file set, so this unrelated deletion landed inside `feat(...): global API exception infrastructure (p1)` instead of its own commit. The content itself is correct and intentional — it just landed in the wrong commit boundary.
- **Fix**: No code change needed. Optionally note in `context/changes/api-exception-handling/change.md` that commit 1d02374 also carries the trip-domain-model→friendship-requests split cleanup, for anyone reading `git log` later.
- **Decision**: FIXED — 3aa35a6 (documented in change.md)

### F3 — Two coexisting conventions for exception message ownership

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: src/Exception/UserAlreadyExistsException.php (and 9 other Phase 2 exception classes) vs. src/Exception/CannotRemoveLastOwnerException.php
- **Detail**: The three Phase-1-migrated exceptions (`CannotRemoveLastOwnerException`, `GroupAlreadyHasOwnerException`, `UserAlreadyInGroupException`) own their message via a custom constructor that interpolates arguments. The ten Phase-2 exceptions have no constructor at all — they rely on `\RuntimeException`'s default constructor, so the message is whatever the call site happens to pass positionally (e.g. `new UserAlreadyExistsException('User already exists.')`). Every call site today passes a correct message, so there's no functional bug, but the plan's own Phase 2 contract ("following the exact pattern from Phase 1's migrated exceptions") is satisfied for the interface/status-code parts but not for message ownership, and there's no documented rule for which convention new exceptions should follow going forward.
- **Fix**: No code change required now (all call sites are correct). If this bothers you, document the convention (e.g. "message is caller-supplied unless the exception needs to format an ID into it") as a short comment on `ApiExceptionInterface` or in `backend/CLAUDE.md`.
- **Decision**: FIXED — 3aa35a6 (unified on caller-supplied message; dropped the 3 custom constructors, updated 5 call sites in GroupMembershipService.php)

### F4 — Generic HttpExceptionInterface branch has no message-sanitization boundary

- **Severity**: ⚪ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: src/EventListener/ApiExceptionListener.php:74-87
- **Detail**: This branch calls `$exception->getMessage()` verbatim for any `HttpExceptionInterface` not already handled by the two branches above it (framework 404s, 405s, and any future `throw new BadRequestHttpException($rawInput)` elsewhere in the codebase). Today every message reaching this branch is safe and intentional — confirmed by the plan-drift agent's full-repo scan — so this is not a current leak, just a boundary worth remembering if a future contributor throws a built-in `HttpExceptionInterface` with user-controlled content.
- **Fix**: No action needed now. Worth a one-line comment on that branch if you want future contributors to notice the implicit trust boundary.
- **Decision**: FIXED — 3aa35a6

### F5 — Stale fixture comment in GroupMembershipControllerTest.php

- **Severity**: ⚪ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Functional/Controller/Admin/GroupMembershipControllerTest.php:24
- **Detail**: Comment says `group_3: user_2=owner, user_3=owner, user_4=member`, but the authoritative fixture map in `backend/CLAUDE.md` (and `/backend` project instructions) says `group_3: user_2=owner, user_3=member, user_4=member`. Not functionally used by this test file (pre-existing, not introduced by this change), purely a stale comment.
- **Fix**: Correct the comment to `user_3=member`.
- **Decision**: FIXED — 3aa35a6

## Success Criteria Verification

**Automated:**
- `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit --filter=GroupMembershipControllerTest` → **PASS** (23 tests, 67 assertions)
- `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit` (full suite) → **PASS** (154 tests, 309 assertions)
- `grep -rn "'status' *=> *'error'\|'valid' *=> *false" src/Controller/` → **PASS** (no matches)

**Manual** (Progress section):
- `1.4` (Bruno spot-check on GroupMembershipController) — `[ ]` pending, correctly left unchecked
- `2.3` (Bruno spot-check on /auth/login, /invitation/verify, /admin/user-invite) — `[ ]` pending, correctly left unchecked

No rubber-stamping detected — both Manual items are honestly marked pending.

---

## Addendum: 2026-08-01 — Manual verification + json_login failure fix

- **Scope**: Manual Progress items 1.4/2.3 confirmed (via curl in lieu of Bruno) + new `JsonAuthenticationFailureHandler` fix discovered during 2.3
- **Verdict**: APPROVED (after triage fixes below)
- **Findings**: 0 critical, 1 warning, 1 observation

### Addendum Findings

#### F1 — No automated regression test for JsonAuthenticationFailureHandler

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Functional/Controller/AuthControllerTest.php:42-52
- **Detail**: `testLoginWithInvalidPassword()` asserted only HTTP 401, not the new envelope shape (`error`/`message`/`timestamp`/`path`). Coverage existed only via manual curl this session.
- **Fix**: Extend the test to assert `error === 'AUTHENTICATION_FAILED'` and presence of `message`/`timestamp`/`path`.
- **Decision**: FIXED

#### F2 — New error code AUTHENTICATION_FAILED not in plan's status→code map

- **Severity**: ⚪ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: src/Security/JsonAuthenticationFailureHandler.php:24
- **Detail**: Introduces a third 401 code (`AUTHENTICATION_FAILED`) distinct from `AUTHENTICATION_REQUIRED`, distinguishing "wrong password" from "not logged in" — deliberate and reasonable, but undocumented as a convention.
- **Fix**: Documented in `backend/CLAUDE.md`.
- **Decision**: FIXED

### Addendum Success Criteria Verification

**Automated:**
- `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit --filter=AuthControllerTest` → **PASS** (10 tests, 35 assertions)
- `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit` (full suite) → **PASS** (156 tests, 320 assertions)
- `vendor/bin/phpstan analyse src/Security/JsonAuthenticationFailureHandler.php` → **PASS** (no errors)
- `vendor/bin/php-cs-fixer fix --dry-run --diff src/Security/JsonAuthenticationFailureHandler.php` → **PASS** (0 files need fixing)

**Manual** (Progress section):
- `1.4` (Bruno spot-check on GroupMembershipController) — confirmed via curl: 404/403/422 all return correct envelope
- `2.3` (Bruno spot-check on /auth/login, /invitation/verify, /admin/user-invite) — confirmed via curl: all three return consistent envelope; surfaced and fixed the `/auth/login` gap (see F1/F2 above)
