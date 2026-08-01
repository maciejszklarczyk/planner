---
date: 2026-07-22T20:52:05Z
researcher: Maciej Szklarczyk
git_commit: 7e3a4e187c67702bcd43b763a7517193304bbbc3
branch: main
repository: backend
topic: "Inventory of every exception-to-JSON site in the codebase, ahead of introducing a global kernel.exception listener"
tags: [research, codebase, exceptions, error-handling, security-handlers, testing]
status: complete
last_updated: 2026-07-22
last_updated_by: Maciej Szklarczyk
---

# Research: Exception-to-JSON inventory for a global exception-handling migration

**Date**: 2026-07-22T20:52:05Z
**Researcher**: Maciej Szklarczyk
**Git Commit**: 7e3a4e187c67702bcd43b763a7517193304bbbc3
**Branch**: main
**Repository**: backend

## Research Question

Originally gathered while planning `friendship-requests`: the user decided to introduce a project-wide `ApiExceptionInterface` + `kernel.exception` listener and migrate ALL existing controllers onto it, not just the new Friendship code. Before that migration can be planned, every current site that converts an exception (or a manual condition) into a JSON error response needs to be inventoried, along with every test that locks in today's shapes.

## Summary

The codebase has **no** `kernel.exception` listener or `ExceptionListener`/`ExceptionSubscriber` anywhere — confirmed via grep, zero matches. Error handling today is spread across three independent mechanisms, each producing a *different* JSON shape:

1. **Controller try/catch** — only `Admin\GroupMembershipController` does this, catching 3 custom domain exceptions (all bare `\RuntimeException` subclasses, no shared interface) plus `NotFoundHttpException`, producing `{error: CODE, message: ...}`.
2. **Controller manual `if` checks** — `Admin\UserController` (`{status: 'error', message: ...}`), `InvitationController` (`{valid: false, message: ...}`), `UserAvatarController` and `UserController` (bare `{message: ...}`), `AuthController` (`{error: <message>}` with no separate `message` key). Four distinct shapes across five controllers.
3. **Symfony Security handlers** — `JsonAccessDeniedHandler` (403) and `JsonAuthenticationEntryPoint` (401), both already independently using `{error: CODE, message: ...}` with the *correct* codes (`ACCESS_DENIED`, `AUTHENTICATION_REQUIRED`) — these run at the firewall level, outside the controller layer entirely, so they're a distinct integration point the new listener does not automatically cover.

`EventController` and `GroupController` have no manual error handling at all — they rely entirely on Symfony's uncustomized defaults for `#[MapEntity]` 404s and `#[MapRequestPayload]` 422s (no `error_controller` override exists in `config/packages/framework.yaml`).

11 existing functional-test assertions lock in specific shapes/codes and must either keep passing (the 8 in `GroupMembershipControllerTest`, whose codes already match the target design) or be deliberately updated (the 3 in `UserControllerTest` asserting the old `status` shape, plus 1 loose assertion in `AuthControllerTest` that already anticipates a future rename).

## Detailed Findings

### Controllers with try/catch → JSON

**`src/Controller/Admin/GroupMembershipController.php`** — the only real try/catch consumer in the codebase:
- `addUser()` (lines 41-73): catches `NotFoundHttpException` → `{error: 'NOT_FOUND', message}`, 404; `UserAlreadyInGroupException` → `{error: 'USER_ALREADY_IN_GROUP', message}`, 400; `GroupAlreadyHasOwnerException` → `{error: 'GROUP_ALREADY_HAS_OWNER', message}`, 422.
- `removeUser()` (lines 94-111): catches `NotFoundHttpException` → 404; `CannotRemoveLastOwnerException` → `{error: 'CANNOT_REMOVE_LAST_OWNER', message}`, 422.
- `updateUserRole()` (lines 114-139): catches `NotFoundHttpException` → 404; `CannotRemoveLastOwnerException` → 422; `GroupAlreadyHasOwnerException` → 422.
- `listUsers()` (lines 76-91): no try/catch — a manual `if (!$group)` → `{error: 'NOT_FOUND', message: 'Group not found.'}`, 404. Same shape as the caught cases, different mechanism (a migration target too).

### Controllers with manual `if` → JSON (four distinct, incompatible shapes)

**`src/Controller/Admin/UserController.php`** — `{status: 'error', message: ...}` shape:
- `sendUserInvite()` (75-94): duplicate email → 400 (raw int, no `Response::HTTP_*` constant).
- `resendUserInvite()` (100-121): user not found → 404; already completed registration → 400.

**`src/Controller/AuthController.php`** — its own one-off shape:
- `login()` (23-40): missing credentials → `{message: 'Missing credentials'}`, 401.
- `me()` (49-58): not authenticated → `{error: 'Not authenticated'}`, 401 — note this uses `error` for the *message text*, not a code, unlike every other controller.

**`src/Controller/InvitationController.php`** — `{valid: false, message: ...}` shape (public, unauthenticated endpoints):
- `verify()` (31-47) and `complete()` (50-77): invalid token / already used / expired → 400 each; `complete()` additionally: user not found → 404.

**`src/Controller/UserAvatarController.php`** — bare `{message: ...}` shape:
- `upload()` (35-66): missing credentials → 401; no file → 422; too large → 422; invalid mime → 422.
- `delete()` (69-91): missing credentials → 401.
- Note: the private `resizeAndCropToWebp()` helper (93-139) throws plain `\RuntimeException` on GD failures (103, 122, 135) — currently **uncaught**, bubbles to Symfony's default 500 handler. The new listener's generic fallback branch will start producing a structured envelope for these instead of Symfony's raw default — a behavior improvement, not a regression, but worth calling out since it's a change in what these failure paths return.

**`src/Controller/UserController.php`** — bare `{message: ...}` shape:
- `editUser()` (26-48): missing credentials → 401; user not found → 404; no permission → 403.
- `deleteUser()` (51-71): same three checks.

**`src/Controller/EventController.php`, `src/Controller/GroupController.php`** — no manual error handling at all; rely entirely on Symfony framework defaults (`#[MapEntity]` 404, `#[MapRequestPayload]` 422, voter-driven 403 via `#[IsGranted]`).

**`src/Controller/HealthCheckController.php`** — no error handling (success-only).

### Custom domain exceptions

All three in `src/Exception/` extend `\RuntimeException` directly, no shared base class, no interface:
- `CannotRemoveLastOwnerException.php:7-13` — `__construct(int $groupId)`.
- `GroupAlreadyHasOwnerException.php:7-13` — `__construct(int $groupId)`.
- `UserAlreadyInGroupException.php:7-13` — `__construct(int $userId, int $groupId)`.

None carry an error-code or HTTP-status property today — that mapping lives entirely in the controller's catch block (see above).

### `NotFoundHttpException` usage

Thrown directly (not via a custom class) 6 times, all in `src/Service/GroupMembershipService.php` (lines 45, 50, 83, 88, 113, 118) and always caught by the corresponding `GroupMembershipController` method. Also thrown **implicitly** by `#[MapEntity]` param converters in `EventController`/`GroupController` — these are never caught anywhere, so they currently hit Symfony's pure default error handling (no `error_controller` override exists in `config/packages/framework.yaml`; confirmed by reading the full file).

### `#[MapRequestPayload]` validation failures

No custom formatter exists — `config/packages/validator.yaml` only sets `auto_mapping` (commented out) and a `when@test` override for `not_compromised_password`. Validation failures throw Symfony's built-in `UnprocessableEntityHttpException` wrapping a `ConstraintViolationList`, serialized by Symfony's default error controller into its standard problem-details-ish shape — no app code touches this today.

### Symfony Security handlers (a third, separate mechanism)

- `src/Security/JsonAccessDeniedHandler.php` (full file, 26 lines) — implements `AccessDeniedHandlerInterface`, returns `{error: 'ACCESS_DENIED', message: 'You do not have sufficient permissions to access this resource'}`, 403.
- `src/Security/JsonAuthenticationEntryPoint.php` (full file, 26 lines) — implements `AuthenticationEntryPointInterface`, returns `{error: 'AUTHENTICATION_REQUIRED', message: 'Full authentication is required to access this resource'}`, 401.
- Both are registered in `config/packages/security.yaml`: `entry_point: App\Security\JsonAuthenticationEntryPoint` (line 23), `access_denied_handler: App\Security\JsonAccessDeniedHandler` (line 30).
- These run at the Symfony Security firewall layer — triggered by `access_control` denials and by `AccessDeniedException` thrown from voters (e.g. `GroupVoter`) — **before** any `kernel.exception` listener would see the exception in the normal case, since Security's own listener has higher priority and short-circuits by calling these handlers directly. A new global listener does **not** automatically cover this path; these two classes need to be updated independently to use the same envelope-building logic, but their `error`/`message` values are already correct and don't need to change.

### `src/Kernel.php` / `config/services.yaml`

- Bare `MicroKernelTrait` kernel, no custom exception wiring.
- `App\: resource: '../src/'` with `_defaults: autowire: true, autoconfigure: true` — any new listener class dropped into `src/EventListener/` (or similar) auto-registers and auto-tags with **no manual service wiring needed**. Only `UserAvatarController` has an explicit `services.yaml` override, and only because it needs scalar constructor args (`$s3PublicUrl`, `$s3Bucket`), not because autowiring is off.
- Confirmed: no exception-normalizer service or `EventListener`/`EventSubscriber` directory exists anywhere in `src/` today. Fully greenfield addition.

### Functional tests locking in current shapes

**`tests/Functional/Controller/Admin/GroupMembershipControllerTest.php`** — 8 hard assertions on exact `error` code strings: `CANNOT_REMOVE_LAST_OWNER` (154-155, 249-250), `NOT_FOUND` (165-167, 176-178, 263-264, 276-277), `GROUP_ALREADY_HAS_OWNER` (309-311, 338-340). These codes already match what a new `ApiExceptionInterface`-based system should produce — they define the target contract, not just a regression guard.

**`tests/Functional/Controller/Admin/UserControllerTest.php`** — 3 assertions on `assertSame('error', $data['status'])` (145/147-148, 194/196-197, 212/214-215) — asserts the **old shape** and must be rewritten to assert the new `error` code strings instead.

**`tests/Functional/Controller/AuthControllerTest.php`** — 1 assertion (117-125) already written defensively: `assertContains($responseData['error'], ['Not authenticated', 'AUTHENTICATION_REQUIRED'])` — anticipates the exact rename this migration performs; should be tightened to assert only `AUTHENTICATION_REQUIRED` once the migration lands.

**`tests/Controller/EventControllerTest.php`, `tests/Controller/HealthCheckControllerTest.php`** — no error-shape assertions, only status-code assertions. No coupling to worry about.

No other test file references an `'error'` key.

## Code References

- `src/Controller/Admin/GroupMembershipController.php:41-139` — the only try/catch consumer, all 3 action methods
- `src/Controller/Admin/UserController.php:75-121` — `{status, message}` shape
- `src/Controller/AuthController.php:23-58` — one-off `{error: <message text>}` shape
- `src/Controller/InvitationController.php:31-77` — `{valid, message}` shape (public endpoints)
- `src/Controller/UserAvatarController.php:35-139` — bare `{message}` shape; uncaught `\RuntimeException` in `resizeAndCropToWebp()`
- `src/Controller/UserController.php:26-71` — bare `{message}` shape
- `src/Exception/CannotRemoveLastOwnerException.php:7-13`, `GroupAlreadyHasOwnerException.php:7-13`, `UserAlreadyInGroupException.php:7-13` — the 3 existing domain exceptions, all bare `\RuntimeException`
- `src/Service/GroupMembershipService.php:45,50,83,88,113,118` — the 6 direct `NotFoundHttpException` throw sites
- `src/Security/JsonAccessDeniedHandler.php`, `src/Security/JsonAuthenticationEntryPoint.php` — the two Security-firewall-level handlers
- `config/packages/security.yaml:23,30` — where the two handlers are registered
- `config/packages/framework.yaml` — no `error_controller` override
- `config/packages/validator.yaml` — no custom validation-failure formatter
- `config/services.yaml` — `App\: resource: '../src/'` autowire/autoconfigure, confirming a new listener needs no manual wiring
- `tests/Functional/Controller/Admin/GroupMembershipControllerTest.php:154-155,165-167,176-178,249-250,263-264,276-277,309-311,338-340` — the 8 assertions to keep passing
- `tests/Functional/Controller/Admin/UserControllerTest.php:145,147-148,194,196-197,212,214-215` — the 3 assertions to update
- `tests/Functional/Controller/AuthControllerTest.php:117-125` — the 1 assertion to tighten

## Architecture Insights

- Three independent error-producing mechanisms coexist (controller try/catch, controller manual `if`, Security firewall handlers) — a complete migration must touch all three, not just controllers.
- The Security handlers already use the target shape's `error`/`message` keys and correct code values — they need the new envelope's *additional* fields (`timestamp`, `path`) but not a code rename, unlike every controller-level shape.
- `GroupMembershipControllerTest`'s existing codes (`NOT_FOUND`, `CANNOT_REMOVE_LAST_OWNER`, `GROUP_ALREADY_HAS_OWNER`) should be treated as the established naming convention for the new `ApiExceptionInterface`-based codes, not renamed for consistency's sake.
- No `error_controller` override and no custom validation-failure formatter exist — `#[MapEntity]` 404s and `#[MapRequestPayload]` 422s from `EventController`/`GroupController` currently hit pure Symfony defaults with zero app-level shaping; the new listener is the first thing to touch these paths at all.

## Historical Context (from prior changes)

None found specific to error handling — `context/changes/**/` and `context/archive/**/` were not searched further for this narrow topic since the live-codebase inventory above is exhaustive and directly answers the research question.

## Related Research

- `context/changes/friendship-requests/research.md` — the original Friendship-domain research this exception-handling work was split out of; still relevant for how `friendship-requests`' own new exceptions will plug into the system built here.

## Open Questions

None blocking. All design decisions (envelope shape, error-code taxonomy, interface vs. central map, migration scope) were resolved during the `/10x-plan friendship-requests` session before the split — see `context/changes/api-exception-handling/plan.md` and `plan-brief.md`.
