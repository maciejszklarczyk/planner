# Global API Exception-Handling Infrastructure Implementation Plan

## Overview

The codebase has no `kernel.exception` listener and four incompatible JSON error shapes spread across six controllers plus two independent Security firewall handlers. This plan introduces a single `ApiExceptionInterface` + `kernel.exception` listener producing one consistent envelope (`{error, message, timestamp, path, violations?}`), and migrates every existing controller and the two Security handlers onto it.

This was split out of `friendship-requests` during its planning session: while designing the Friendship domain's own error handling, the user decided the new exception system should be adopted project-wide rather than left to coexist with the old ad-hoc patterns. Since that decision touches six controllers with no relation to Friendship, it was promoted to roadmap Foundation `F-01` and separated into this change. `friendship-requests` depends on this change landing first.

## Current State Analysis

- **No `kernel.exception` listener or `ExceptionListener`/`ExceptionSubscriber` exists anywhere in `src/`** — confirmed via research (zero matches).
- **Four incompatible JSON error shapes are in use today**: `{error, message}` (`Admin\GroupMembershipController`, via try/catch), `{status, message}` (`Admin\UserController`, manual `if`), `{valid, message}` (`InvitationController`, manual `if`), and bare `{message}` (`UserAvatarController`, `UserController`, manual `if`). `AuthController::me()` uses yet another variant (`{error: <message>}` with no separate `message` key).
- **Two dedicated Security handlers already exist and already use the `{error, message}` shape**: `src/Security/JsonAccessDeniedHandler.php` (403, code `ACCESS_DENIED`) and `src/Security/JsonAuthenticationEntryPoint.php` (401, code `AUTHENTICATION_REQUIRED`). These run at the Symfony Security firewall level, outside the controller layer — a third error path alongside try/catch and manual-`if` that a `kernel.exception` listener does not automatically cover.
- **Only 3 custom domain exceptions exist** (`CannotRemoveLastOwnerException`, `GroupAlreadyHasOwnerException`, `UserAlreadyInGroupException`), all `extends \RuntimeException` with no interface — the error code/status mapping lives entirely in the controller's catch block, not on the exception.
- **`NotFoundHttpException` (Symfony built-in) is the only "not found" signal in use**, thrown 6× directly in `GroupMembershipService` and implicitly by `#[MapEntity]` param converters in `EventController`/`GroupController`. Always caught explicitly in `GroupMembershipController`; from `EventController`/`GroupController` it currently falls through to Symfony's uncustomized default error controller.
- **8 functional-test assertions in `GroupMembershipControllerTest`** lock in exact `error` code strings (`NOT_FOUND`, `CANNOT_REMOVE_LAST_OWNER`, `GROUP_ALREADY_HAS_OWNER`) — these already match the target design and must keep passing unchanged. **3 assertions in `UserControllerTest`** lock in the old `status === 'error'` shape — these must be updated, since that shape goes away. **1 assertion in `AuthControllerTest`** already loosely tolerates either the old bare message or a future code string — this gets tightened.

## Desired End State

Every API error response — old controllers, new Friendship code (a future dependent change), and the two Security handlers alike — returns the same JSON shape: `{"error": "CODE", "message": "...", "timestamp": "...", "path": "..."}`, with an additional `"violations"` array present only for request-validation failures.

**Verification**: `GroupMembershipControllerTest`'s existing 8 error-code assertions still pass; `UserControllerTest` and `AuthControllerTest` are updated and pass against the new shape; no controller in `src/Controller/` contains a manual `{'status'|'valid': ...}` error array anymore.

### Key Discoveries:

- `config/services.yaml`'s `App\: resource: '../src/'` with `autowire: true, autoconfigure: true` means a new listener class needs no manual service wiring — just the right attribute.
- `JsonAccessDeniedHandler`/`JsonAuthenticationEntryPoint` already use the target `error`/`message` values (`ACCESS_DENIED`/`AUTHENTICATION_REQUIRED`) — they only need the new envelope's additional `timestamp`/`path` fields, not a code rename.
- `GroupMembershipControllerTest`'s existing codes should be treated as the established naming convention for the new system, not renamed.

## What We're NOT Doing

- **Changing any existing HTTP status code** — this migration standardizes the *shape* and adds machine-readable *codes*; it does not change any existing status code (e.g. `USER_ALREADY_IN_GROUP` stays 400, `CANNOT_REMOVE_LAST_OWNER` stays 422), to keep the blast radius bounded to shape/code changes only.
- **Redesigning the two Security handlers' codes** — `ACCESS_DENIED`/`AUTHENTICATION_REQUIRED` are already correct; only the envelope's additional fields are added to them.
- **The Friendship domain itself** — that's `context/changes/friendship-requests/`, which depends on this change but is out of scope here.
- **Adding new business-rule exceptions beyond what today's manual checks already express** — this plan translates existing conditions into typed exceptions; it does not add new validation rules.

## Implementation Approach

Build the shared infrastructure first (interface + listener + envelope factory), prove it against the one controller with the strictest existing test coverage (`GroupMembershipController`), then migrate the remaining five controllers plus the two Security handlers onto the same pattern. This ordering means the codebase is never left half-migrated with two competing systems.

## Phase 1: Global API Exception Infrastructure

### Overview

Introduce the interface, the shared envelope-building logic, and the `kernel.exception` listener, then migrate the codebase's only real try/catch consumer (`Admin\GroupMembershipController`) and the two Security handlers onto it.

### Changes Required:

#### 1. Exception contract

**File**: `src/Exception/ApiExceptionInterface.php`

**Intent**: Give every domain exception a self-describing error code and HTTP status, so the listener never needs a hardcoded per-exception-class map.

**Contract**: Interface with `getErrorCode(): string` and `getStatusCode(): int`.

#### 2. Shared envelope builder

**File**: `src/Service/ApiErrorEnvelopeFactory.php`

**Intent**: One place that builds the `{error, message, timestamp, path, violations?}` array, reused by the new listener *and* the two existing Security handlers so there's a single source of truth for the envelope shape instead of three copies.

**Contract**: `build(string $errorCode, string $message, Request $request, ?array $violations = null): array`. `timestamp` is `(new \DateTimeImmutable())->format(DATE_ATOM)`; `path` is `$request->getPathInfo()`.

#### 3. The listener

**File**: `src/EventListener/ApiExceptionListener.php`

**Intent**: Single `kernel.exception` hook that replaces every controller's manual try/catch and `if`-based error JSON. Resolves the thrown `\Throwable` into an envelope via `ApiErrorEnvelopeFactory` and calls `$event->setResponse(...)`.

**Contract**: `#[AsEventListener(event: KernelEvents::EXCEPTION)]`. Resolution order:
- Implements `ApiExceptionInterface` → use its `getErrorCode()`/`getStatusCode()` directly.
- Implements `HttpExceptionInterface` **and** `$exception->getPrevious() instanceof \Symfony\Component\Validator\Exception\ValidationFailedException` (the `#[MapRequestPayload]` failure case — Symfony's `RequestPayloadValueResolver` throws `UnprocessableEntityHttpException` with the `ValidationFailedException` as `$previous`, not as the thrown exception itself, so this branch must unwrap `getPrevious()`, not `instanceof` the outer exception) → status 422, code `VALIDATION_ERROR`, `violations` populated as `[{field, message}, ...]` from `$exception->getPrevious()->getViolations()`. **This branch must be checked before the plain `HttpExceptionInterface` branch below**, since `UnprocessableEntityHttpException` also satisfies that check and would otherwise swallow it with no `violations`.
- Implements `HttpExceptionInterface` (covers `NotFoundHttpException` from `#[MapEntity]`/service throws, and any other built-in HTTP exception) → status from `getStatusCode()`, code from a small status→code map: `400→BAD_REQUEST, 401→AUTHENTICATION_REQUIRED, 403→FORBIDDEN, 404→NOT_FOUND, 405→METHOD_NOT_ALLOWED, 409→CONFLICT, 422→VALIDATION_ERROR, 429→TOO_MANY_REQUESTS`.
- Anything else → 500, code `INTERNAL_ERROR`, with a fixed generic message (`"An unexpected error occurred."`) — **never** pass an uncontrolled exception's own message through at this branch, to avoid leaking internals.

#### 4. Migrate existing exceptions

**Files**: `src/Exception/CannotRemoveLastOwnerException.php`, `src/Exception/GroupAlreadyHasOwnerException.php`, `src/Exception/UserAlreadyInGroupException.php`

**Intent**: Implement the new interface with the exact codes/statuses the current controller catch-blocks already use, so existing tests keep passing unchanged.

**Contract**: Each `implements ApiExceptionInterface`; `getErrorCode()` returns the literal string used today (`CANNOT_REMOVE_LAST_OWNER`, `GROUP_ALREADY_HAS_OWNER`, `USER_ALREADY_IN_GROUP`); `getStatusCode()` returns the literal status used today (422, 422, 400 respectively).

#### 5. Migrate the first controller

**File**: `src/Controller/Admin/GroupMembershipController.php`

**Intent**: Remove all try/catch blocks — exceptions now propagate to the listener. Replace `listUsers()`'s manual `if (!$group)` branch with a thrown `NotFoundHttpException` so it goes through the same path as everything else.

**Contract**: Each action method shrinks to a direct service call plus the success-path response.

#### 6. Security handlers pick up the shared envelope

**Files**: `src/Security/JsonAccessDeniedHandler.php`, `src/Security/JsonAuthenticationEntryPoint.php`

**Intent**: Use `ApiErrorEnvelopeFactory` instead of a hand-built array, so their responses also carry `timestamp`/`path`. Their `error`/`message` values (`ACCESS_DENIED`/`AUTHENTICATION_REQUIRED`) are unchanged.

**Contract**: Constructor-inject `ApiErrorEnvelopeFactory`; call `build('ACCESS_DENIED', '...', $request)` / `build('AUTHENTICATION_REQUIRED', '...', $request)`.

### Success Criteria:

#### Automated Verification:

- `GroupMembershipControllerTest`'s existing 8 error-code assertions still pass unchanged: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit --filter=GroupMembershipControllerTest`
- New assertions confirming `timestamp`/`path` keys are present on at least one error response
- New assertion hitting a `#[MapRequestPayload]` endpoint with an invalid payload, confirming `422` + `VALIDATION_ERROR` + a non-empty `violations` array with `{field, message}` entries

#### Manual Verification:

- Hitting a known 404/403/422 case against `Admin\GroupMembershipController` via Bruno returns the new envelope shape with correct `error` code

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Migrate Remaining Controllers

### Overview

With the listener proven against `GroupMembershipController`, migrate the remaining five controllers — each currently using a different, incompatible manual error shape — onto the same throw-based pattern.

### Changes Required:

#### 1. New exception classes for controller-specific business rules

**Files**: `src/Exception/UserAlreadyExistsException.php` (400, `USER_ALREADY_EXISTS`), `src/Exception/UserAlreadyCompletedRegistrationException.php` (400, `USER_ALREADY_COMPLETED_REGISTRATION`), `src/Exception/AuthenticationRequiredException.php` (401, `AUTHENTICATION_REQUIRED`), `src/Exception/InvitationTokenInvalidException.php` (400, `INVITATION_TOKEN_INVALID`), `src/Exception/InvitationTokenAlreadyUsedException.php` (400, `INVITATION_TOKEN_ALREADY_USED`), `src/Exception/InvitationTokenExpiredException.php` (400, `INVITATION_TOKEN_EXPIRED`), `src/Exception/AvatarFileMissingException.php` (422, `AVATAR_FILE_MISSING`), `src/Exception/AvatarFileTooLargeException.php` (422, `AVATAR_FILE_TOO_LARGE`), `src/Exception/AvatarFileTypeInvalidException.php` (422, `AVATAR_FILE_TYPE_INVALID`), `src/Exception/InsufficientPermissionException.php` (403, `INSUFFICIENT_PERMISSION`)

**Intent**: One exception per distinct business-rule violation currently expressed as a manual `if` branch, each carrying the status code that branch uses today (status codes are preserved — see `## What We're NOT Doing`).

**Contract**: Each `extends \RuntimeException implements ApiExceptionInterface`, following the exact pattern from Phase 1's migrated exceptions.

#### 2. `Admin\UserController`

**File**: `src/Controller/Admin/UserController.php`

**Intent**: `sendUserInvite()`/`resendUserInvite()` throw `UserAlreadyExistsException`/`NotFoundHttpException`/`UserAlreadyCompletedRegistrationException` instead of returning manual `{'status': 'error', ...}` arrays.

**Contract**: Same status codes as today (400/404/400).

#### 3. `AuthController`

**File**: `src/Controller/AuthController.php`

**Intent**: `login()`'s "Missing credentials" and `me()`'s "Not authenticated" both throw `AuthenticationRequiredException` instead of manual 401 arrays.

**Contract**: Both now produce `{"error": "AUTHENTICATION_REQUIRED", ...}` — this finally makes `AuthControllerTest`'s existing loose either/or assertion resolve to a single, predictable value.

#### 4. `InvitationController`

**File**: `src/Controller/InvitationController.php`

**Intent**: The four manual `{'valid': false, ...}` branches in `verify()`/`complete()` throw the three new `InvitationToken*` exceptions plus `NotFoundHttpException` (for "User not found" in `complete()`) instead. The `valid` boolean is dropped — the project's PRD explicitly allows response-shape changes at this stage, and clients now distinguish success/failure by HTTP status + `error` code instead of a body flag.

**Contract**: Status codes unchanged (400/400/400/404).

#### 5. `UserAvatarController`

**File**: `src/Controller/UserAvatarController.php`

**Intent**: `upload()`/`delete()`'s manual `{'message': ...}` branches throw `AuthenticationRequiredException` (reused from AuthController) or the three new `Avatar*` exceptions instead.

**Contract**: Status codes unchanged (401/422/422/422).

#### 6. `UserController`

**File**: `src/Controller/UserController.php`

**Intent**: `editUser()`/`deleteUser()`'s manual `{'message': ...}` branches throw `AuthenticationRequiredException`, `NotFoundHttpException`, or the new `InsufficientPermissionException` instead.

**Contract**: Status codes unchanged (401/404/403).

#### 7. Update tests for the new shape

**Files**: `tests/Functional/Controller/Admin/UserControllerTest.php`, `tests/Functional/Controller/AuthControllerTest.php`

**Intent**: Replace the 3 `assertSame('error', $data['status'])` assertions with assertions on the new `error` code strings (`USER_ALREADY_EXISTS`, `NOT_FOUND`, `USER_ALREADY_COMPLETED_REGISTRATION`); tighten `AuthControllerTest`'s loose either/or assertion (line 117-125) to assert exactly `AUTHENTICATION_REQUIRED`.

**Contract**: Before writing new assertions, grep `tests/` for any existing test on `InvitationController`, `UserAvatarController`, or `UserController` that asserts on the old `valid`/bare-`message` shapes — research did not find any, but this must be re-confirmed at implementation time since a false negative here would silently break an untouched test.

### Success Criteria:

#### Automated Verification:

- Full test suite passes: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit`
- No remaining manual `{'status'|'valid': ...}` error arrays in any controller: `grep -rn "'status' *=> *'error'\|'valid' *=> *false" src/Controller/`

#### Manual Verification:

- Bruno collection requests against `/auth/login` (bad credentials), `/invitation/verify` (bad token), and `/admin/user-invite` (duplicate email) all return the new consistent envelope

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- Not applicable — this change is entirely about HTTP-layer error shaping, covered by functional tests

### Integration Tests:

- Full functional suite per phase (Phase 1 proves the mechanism against `GroupMembershipControllerTest`; Phase 2 updates `UserControllerTest`/`AuthControllerTest` and re-runs the full suite)

### Manual Testing Steps:

1. After Phase 1: confirm `Admin\GroupMembershipController` error responses carry the new envelope via Bruno.
2. After Phase 2: confirm no controller in the app still returns one of the four old error shapes (spot-check via Bruno against `/auth/login`, `/invitation/verify`, `/admin/user-invite`).

## Performance Considerations

None — this is a pure error-handling refactor with no impact on the success path.

## Migration Notes

Purely additive/refactoring — no database schema changes, no data migration. The only externally-visible change is the shape of error responses (status codes unchanged), which the project's PRD explicitly permits at this stage.

## References

- Related research: `context/changes/api-exception-handling/research.md`
- Similar implementation: `src/Controller/Admin/GroupMembershipController.php` (today's closest-to-target pattern)
- Depended on by: `context/changes/friendship-requests/plan.md`
- Roadmap: `context/foundation/roadmap.md` — Foundation `F-01`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Global API Exception Infrastructure

#### Automated

- [x] 1.1 GroupMembershipControllerTest's existing 8 error-code assertions still pass unchanged
- [x] 1.2 New assertions confirming timestamp/path keys are present on at least one error response
- [x] 1.3 New assertion hitting a #[MapRequestPayload] endpoint with an invalid payload, confirming 422 + VALIDATION_ERROR + non-empty violations array

#### Manual

- [ ] 1.4 Hitting a known 404/403/422 case against Admin\GroupMembershipController via Bruno returns the new envelope shape with correct error code

### Phase 2: Migrate Remaining Controllers

#### Automated

- [x] 2.1 Full test suite passes
- [x] 2.2 No remaining manual {'status'|'valid': ...} error arrays in any controller

#### Manual

- [ ] 2.3 Bruno collection requests against /auth/login (bad credentials), /invitation/verify (bad token), and /admin/user-invite (duplicate email) all return the new consistent envelope
