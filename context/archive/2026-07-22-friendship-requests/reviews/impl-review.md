<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Friendship Requests Implementation Plan

- **Plan**: context/changes/friendship-requests/plan.md
- **Scope**: Full plan (Phases 1-6)
- **Date**: 2026-08-01
- **Verdict**: NEEDS ATTENTION (pre-triage) — all CONFIRMED findings fixed or explicitly accepted during triage
- **Findings**: 0 critical, 3 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

All 6 phases fully implemented and matching plan intent. Verified directly (not just trusting Progress checkmarks): 47 friendship/user-search PHPUnit tests, full backend suite (197 tests after fixes), `doctrine:schema:validate` (only a pre-existing unrelated drift on `user_invitation_token`), frontend vitest/tsc/lint/build all green.

## Findings

### F1 — Concurrent sendRequest can surface as a raw 500 instead of 409

- **Severity**: WARNING
- **Impact**: MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: backend/src/Service/FriendshipService.php:41-92
- **Detail**: The app-level `findActiveBetween()` check and the final `flush()` weren't guarded against a DB-level unique-violation. The migration's partial unique index (`uniq_friend_request_active_pair`) exists specifically to make this race safe, but a losing concurrent `flush()` threw a raw `UniqueConstraintViolationException` — not `ApiExceptionInterface` — surfacing as a generic 500 instead of a clean 409.
- **Fix**: Catch `UniqueConstraintViolationException` around the final `flush()` in `sendRequest()`, rethrow as `DuplicateFriendRequestException`.
- **Decision**: FIXED (Fix A applied). Verified: 29 Friendship tests green, no regressions.

### F2 — Planned HTTP-layer test scenarios missing from FriendshipControllerTest

- **Severity**: WARNING
- **Impact**: MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria / Plan Adherence
- **Location**: backend/tests/Functional/Controller/FriendshipControllerTest.php
- **Detail**: Plan's Phase 3 contract required HTTP-layer tests for MockClock cooldown-expiry, accept/decline/cancel-on-non-pending 409s, and a cancelled-doesn't-trigger-cooldown regression. None existed — `testAcceptOnNonPendingRequestReturns409` didn't actually attempt an accept or assert 409. All rules were covered only at the service-unit level (`FriendshipServiceTest.php`, not in the plan).
- **Fix**: Added the missing HTTP-layer tests (MockClock override via `self::getContainer()->set(ClockInterface::class, new MockClock('+4 days'))`, decline/cancel-on-non-pending 409 tests, cancelled-doesn't-trigger-cooldown regression test) and fixed the misnamed accept-on-non-pending test to actually attempt the accept.
- **Decision**: FIXED (Fix A applied). File grew from 16 to 24 test methods; full class (24 tests) and full suite (197 tests) green.

### F3 — backend/.env cooldown config still uncommitted

- **Severity**: WARNING
- **Impact**: LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: backend/.env:69
- **Detail**: `FRIEND_REQUEST_COOLDOWN_DAYS=3` exists in the working tree but was never committed — the Phase 2 commit explicitly flagged this as a manual follow-up (".env itself is sandbox-restricted").
- **Fix**: Stage and commit backend/.env.
- **Decision**: SKIPPED — left for the user to commit themselves.

### F4 — Email-existence enumeration via friend-request send

- **Severity**: OBSERVATION
- **Impact**: LOW
- **Dimension**: Safety & Quality
- **Location**: backend/src/Service/FriendshipService.php:43
- **Detail**: `sendRequest()` 404s (`USER_NOT_FOUND`) on an unregistered email, letting an authenticated user probe exact-email registration. Distinct from the intentionally-open `/users?search=` endpoint.
- **Decision**: SKIPPED — `USER_NOT_FOUND` is one of the plan's seven documented error codes and the frontend's `apiErrors.ts`/send-dialog already surface a specific Polish message for it; changing this would break the established contract and UX for a low-severity, session-gated risk. Accepted as-is.

### F5 — 403-vs-404 existence leak on FriendRequest ids

- **Severity**: OBSERVATION
- **Impact**: LOW
- **Dimension**: Safety & Quality
- **Location**: backend/src/Controller/FriendshipController.php:95
- **Detail**: accept/decline/cancel resolved `{id}` before the voter ran, so a non-participant got 403 (exists) vs 404 (doesn't exist) — leaking occupancy of a sequential id, no content.
- **Fix**: `resolveFriendRequest()` now checks whether the current user is the requester or addressee; non-participants get `FriendRequestNotFoundException` (404) uniformly. Participants attempting the wrong action (e.g. requester trying to accept) still get 403 via the voter.
- **Decision**: FIXED. Updated/added tests: `testAcceptFriendRequestAsRequesterIsForbidden` (renamed, now tests participant-wrong-action 403), `testAcceptFriendRequestAsNonParticipantReturns404` (new), `testCancelFriendRequestAsAddresseeIsForbidden` (renamed, participant-wrong-action 403), `testCancelFriendRequestAsNonParticipantReturns404` (new). Full suite (197 tests) green.

## Summary

Fixed: F1, F2, F5 (3)
Skipped: F3, F4 (2) — both with explicit rationale recorded above
