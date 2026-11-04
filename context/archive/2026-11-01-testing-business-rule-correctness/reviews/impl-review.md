<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Business-Rule Correctness (Test Rollout Phase 2)

- **Plan**: context/changes/testing-business-rule-correctness/plan.md
- **Scope**: Phases 1–5 of 5
- **Date**: 2026-11-04
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warning, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Success criteria re-run on 2026-11-04: full suite 236/236; `FriendshipSendRuleChainTest` 17/17; friendship service+controller 46/46; user search 42/42; phpstan no errors. Manual rows 2.4, 3.4, 4.4, 5.2 pending (3.4 and 4.4 were exercised automatically during implementation).

Retry path checked for production safety: FrankenPHP not in worker mode, no post-controller listener flushes, DoctrineBundle 3.2 resets the manager in place.

Note: `context/foundation/test-plan.md` §3 still shows Phase 2 as `planned` (should be `implementing` while manual rows are pending).

## Findings

### F1 — Unplanned DTO change has no test for the case it fixes

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: backend/src/Dto/Response/FriendRequestDto.php:26
- **Detail**: `FriendRequestDto::fromEntity` now compares users by id instead of `===`. Not in the plan's file list (narrated as ADAPT). Correct, but no test builds two different `User` objects with the same id, so reverting to `===` passes the suite.
- **Fix**: Add a unit test in `tests/Unit/Dto/` with distinct viewer/requester objects sharing an id, asserting `otherUser` is the addressee; note the file as a plan addendum.
- **Decision**: FIXED — unit test `backend/tests/Unit/Dto/FriendRequestDtoTest.php` (red when reverted to `===`), plan addendum added

### F2 — Hidden users' existing requests and friendships are still actionable

- **Severity**: OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Scope Discipline
- **Location**: backend/src/Service/FriendshipService.php (acceptRequest, listPending, listFriends)
- **Detail**: Out of scope by decision, but asymmetric: B cannot send to a blocked A (USER_NOT_FOUND), yet B can accept A's pending request sent before the block, and blocked accounts remain in pending/friend lists.
- **Fix**: Open a follow-up change (`/10x-new`) applying `UserStatusEnum::hiddenFromOtherUsers()` to accept and the lists.
  - Strength: One consistent rule across send/accept/list.
  - Tradeoff: Needs a product decision (hide vs. grey out, existing friendships); touches repository queries and frontend.
  - Confidence: HIGH — gap is visible directly in the code.
  - Blind spot: How often accounts are blocked; whether admins expect existing friendships to survive.
- **Decision**: FIXED — follow-up change opened: `context/changes/friendship-hidden-user-relations/` (no code change in this change)

### F3 — Retry block departs from the file's conventions

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: backend/src/Service/FriendshipService.php:56-66
- **Detail**: Three `\assert()` calls (none elsewhere in `src/`; the codebase uses `?? throw new \LogicException`), and the second-conflict DUPLICATE message uses the email while other messages use ids.
- **Fix**: Replace the asserts with instanceof checks throwing `\LogicException`; make the message wording consistent.
- **Decision**: FIXED — asserts replaced with instanceof checks throwing `\LogicException`; second-conflict message no longer mixes id and email (suite 238/238, phpstan OK)
