---
date: 2026-11-01T21:46:35+01:00
researcher: Claude (Opus 5.5)
git_commit: 5c362f37ea0a0d18e7940ab5d05eb60f30e73be9
branch: friendship-requests-implementation
repository: planner
topic: "Ground rollout Phase 2 of context/foundation/test-plan.md — Business-rule correctness (risk #4)"
tags: [research, codebase, friendship, friend-request, cooldown, business-rules, testing]
status: complete
last_updated: 2026-11-01
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Recorded user decisions: addressee-status rule in scope; §2 guidance for #4 backported"
---

# Research: Ground rollout Phase 2 — Business-rule correctness (risk #4)

**Date**: 2026-11-01T21:46:35+01:00
**Researcher**: Claude (Opus 5.5)
**Git Commit**: 5c362f3
**Branch**: friendship-requests-implementation
**Repository**: planner

## Research Question

Ground risk #4 from `context/foundation/test-plan.md`: *"The self-request /
duplicate / crossed-request-auto-accept / cooldown check ordering regresses,
producing an invalid friendship state."* Verify the risk-response guidance
(prove each rule lands in the correct final state **in combination**;
challenge "DB unique index prevents bad states"; avoid oracles copied from the
service implementation), locate the real failure path and existing tests, and
pick the cheapest useful test layer.

## Summary

- **Risk is real, not speculative.** All four rules live in one method,
  `FriendshipService::sendRequest()` (`backend/src/Service/FriendshipService.php:42-100`),
  and their *order* is the business logic: self → active-row (accepted /
  reversed-pending / same-direction-pending) → cooldown → create. Swapping
  the active-row and cooldown blocks, or changing one repository query, would
  still pass every existing test.
- **Existing tests cover each rule in isolation, never in combination.**
  `FriendshipServiceTest` (15 tests) and `FriendshipControllerTest` (24 tests)
  test self, duplicate, crossed, already-friends, cooldown-active (+1 day),
  cooldown-expired (+4 days), cancel-no-cooldown — each from a clean pair.
  No test puts two rules on the same pair at once.
- **The DB guard is narrower than it looks** — confirmed. The migration
  (`backend/migrations/Version20260801160339.php:28-29`) enforces only
  (a) no self-row (`CHECK requester_id <> addressee_id`) and (b) at most one
  `pending|accepted` row per unordered pair. It knows nothing about cooldown,
  direction, auto-accept vs. reject, or which declined row is "latest".
  Every combination gap below produces rows the DB happily accepts.
- **Cheapest layer: service-level DB-backed tests** in the existing
  `FriendshipServiceTest` harness (real Postgres + `MockClock` + per-test
  `DELETE FROM FriendRequest`). Not pure unit tests with mocked repositories:
  half of the rule logic lives in repository queries (direction filter,
  `status = declined`, `ORDER BY respondedAt DESC`), so mocking them would
  mirror the implementation and miss exactly the regressions we care about.
- **Oracle source correction:** the PRD only states FR-006 (decline starts a
  cooldown for *the same sender → same recipient*; length left as an open
  question). The ordering rule and the cancel-vs-decline distinction exist
  only in the archived plan's *Critical Implementation Details*
  (`context/archive/2026-07-22-friendship-requests/plan.md:68-74`). Tests must
  take their expected values from those two documents, not from
  `FriendshipService`.
- **Two adjacent findings outside risk #4's wording** (see Open Questions):
  `sendRequest` accepts any addressee status (inactive/blocked/deleted-status
  users can still receive requests by email, even though search now hides
  them), and a *concurrent* crossed send surfaces as `DUPLICATE_FRIEND_REQUEST`
  instead of auto-accepting.

## Detailed Findings

### The rule chain (live code)

`FriendshipService::sendRequest()` — `backend/src/Service/FriendshipService.php`:

| Step | Lines | Behavior | Exception / result |
|---|---|---|---|
| 0. resolve addressee | 44-47 | `findOneBy(['email' => …])` exact match; Gedmo filter hides soft-deleted (`deletedAt`) users | `UserNotFoundByEmailException` (404 `USER_NOT_FOUND`) |
| 1. self | 52-54 | compares IDs (so email case games can't bypass it) | `CannotFriendSelfException` (422) |
| 2a. accepted row either direction | 56-60 | `findActiveBetween()` → status `accepted` | `AlreadyFriendsException` (409) |
| 2b. reversed pending | 64-70 | existing pending row whose requester is the current addressee → flipped to `accepted`, `respondedAt = clock.now()`, same row returned | auto-accept, **no new row** |
| 2c. same-direction pending | 72 | | `DuplicateFriendRequestException` (409) |
| 3. cooldown | 75-82 | only reached when no active row; `findLatestDeclinedBySender(requester, addressee)` — direction-specific; blocked while `now < respondedAt + cooldownDays` (strict `<`) | `FriendRequestCooldownActiveException` (429) |
| 4. create | 84-99 | new pending row; `UniqueConstraintViolationException` → `DuplicateFriendRequestException` | new row |

Consequences of this order that are business rules, not accidents (all from
`archive/.../plan.md:70-72`):

- An active row **pre-empts** the cooldown: if A→B was declined (A in
  cooldown) but B has since sent B→A, A's send auto-accepts.
- Cooldown is **direction-specific**: A→B declined does not stop B→A.
- `declined` and `cancelled` rows are **history only**: they neither block
  a fresh request nor trigger auto-accept.
- Cancel never starts a cooldown (`status = declined` filter,
  `FriendRequestRepository.php:43`).

### Repository queries that carry rule logic

`backend/src/Repository/FriendRequestRepository.php`:

- `findActiveBetween()` (25-35) — `status IN (pending, accepted)` + OR over
  both directions; `getOneOrNullResult()` relies on the partial unique index
  to guarantee ≤1 row (would throw `NonUniqueResultException` otherwise).
- `findLatestDeclinedBySender()` (40-53) — `status = declined`, fixed
  direction, `ORDER BY respondedAt DESC LIMIT 1`. The ordering is
  load-bearing: with several declines on the same pair, an `ASC` regression
  would pick an old, expired decline and let a sender bypass an active
  cooldown. **No test has more than one declined row per pair.**

### DB backstop (what it does and doesn't catch)

`backend/migrations/Version20260801160339.php`:

- `:28` `CHECK (requester_id <> addressee_id)` — catches self-rows only.
- `:29` partial unique index on `(LEAST, GREATEST) WHERE status IN ('pending','accepted')` — catches a second active row for a pair.

Does **not** catch: cooldown bypass, wrong-direction cooldown, a crossed send
that creates a second row instead of flipping (would be caught — but as a
500/duplicate, not as the intended auto-accept), a crossed send that rejects
instead of accepting, a declined/cancelled reverse row wrongly auto-accepted.
Test-plan guidance "challenge: DB unique index prevents bad states" is
**confirmed accurate**.

### Existing tests

`backend/tests/Functional/Service/FriendshipServiceTest.php` (DB-backed, `MockClock`, clean `FriendRequest` table per test — lines 31-54):

- self (64), unknown email (71), create (78), duplicate same-direction (87),
  crossed auto-accept (96 — asserts same id + `ACCEPTED`, not `respondedAt`
  or pending lists), already-friends (108), cooldown active at +1 day (118),
  cooldown expired at +4 days (131), cancel-no-cooldown (145),
  cancel/accept non-pending (158, 179), accept/decline set `respondedAt`
  (168, 189), list friends/pending (200, 212).
- **Every failure test asserts only the exception**, never that the
  `friend_request` table is unchanged afterwards.

`backend/tests/Functional/Controller/FriendshipControllerTest.php` — HTTP-level
mirrors of the same isolated cases (35-117, 347-383), plus authorization
(Phase 1 of the rollout). Cooldown controller tests rely on fixture rows with
real-time `"now - N days"` values (`backend/fixtures/friend_requests.yaml`) and
`MockClock('+4 days')` swapped into the container (377).

No test exercises the race catch at `FriendshipService.php:91-97`.

### Gaps — combination cases with no test today

Expected values below come from `archive/.../plan.md:68-74` (ordering,
history semantics) and PRD FR-006 (`context/foundation/prd.md:158`), not from
the service code.

| # | Scenario (same pair) | Expected per rules | Regression it catches |
|---|---|---|---|
| C1 | A→B declined, A in cooldown; B→A now pending; A sends to B | auto-accept B→A row (active row pre-empts cooldown) | cooldown block moved before active-row check |
| C2 | A→B declined, A in cooldown; B sends to A | new pending B→A (cooldown is direction-specific) | cooldown lookup made direction-agnostic |
| C3 | A→B declined at T; send at exactly T+3d / T+3d−1s | allowed / blocked | `<` → `<=`, or days→hours unit drift |
| C4 | A→B declined twice: old (expired) + recent (active); A sends | blocked | `ORDER BY respondedAt` flipped or dropped |
| C5 | B→A declined (history), A sends to B | new pending A→B, not auto-accept, no cooldown for A | `findActiveBetween` widened to include declined |
| C6 | B→A cancelled, A sends to B | new pending A→B, not auto-accept | same, for cancelled |
| C7 | A→B declined → cooldown expires → A resends → B accepts → A sends | `AlreadyFriends` (accepted beats decline history) | accepted check dropped/reordered after cooldown |
| C8 | Every rejected send (self, duplicate, already-friends, cooldown) | row count + statuses for the pair unchanged | partial write before throwing |
| C9 | Crossed auto-accept | `respondedAt` = clock now, both users' pending lists empty, one accepted row total | half-applied flip |

C1, C2, C4 and C8 carry the most signal; C3 is the cheapest boundary guard.

## Code References

- `backend/src/Service/FriendshipService.php:42-100` — `sendRequest`, full rule chain
- `backend/src/Service/FriendshipService.php:64-70` — crossed auto-accept flip
- `backend/src/Service/FriendshipService.php:75-82` — cooldown check (strict `<`)
- `backend/src/Service/FriendshipService.php:91-97` — race catch → duplicate
- `backend/src/Service/FriendshipService.php:105-152` — accept/decline/cancel, pending-only guard
- `backend/src/Repository/FriendRequestRepository.php:25-35` — `findActiveBetween`
- `backend/src/Repository/FriendRequestRepository.php:40-53` — `findLatestDeclinedBySender` (`ORDER BY respondedAt DESC`)
- `backend/migrations/Version20260801160339.php:28-29` — DB CHECK + partial unique index
- `backend/fixtures/friend_requests.yaml` — 4 seeded rows (real-time relative dates)
- `backend/tests/Functional/Service/FriendshipServiceTest.php:31-54` — reusable harness (`service(now, cooldownDays)`, `user(email)`)
- `backend/tests/Functional/Controller/FriendshipControllerTest.php:361-383` — MockClock-in-container pattern for HTTP cooldown tests
- `context/archive/2026-07-22-friendship-requests/plan.md:68-74` — oracle: check order + history semantics
- `context/foundation/prd.md:158` — oracle: FR-006 cooldown definition

## Architecture Insights

- Business rules are concentrated in one service method plus two repository
  queries; the controller is a thin pass-through
  (`FriendshipController.php:33-41`). Service-level tests therefore cover the
  rule chain fully; HTTP tests add only status-code/envelope mapping, already
  covered.
- `ClockInterface` is injected, and the test harness builds the service by
  hand with a `MockClock` and a `cooldownDays` override — boundary and
  multi-decline scenarios cost nothing extra to set up.
- Building history rows (declined at a chosen time, cancelled) is done
  through the service itself with different clocks; no factory or raw SQL is
  needed. Calling `declineRequest`/`cancelRequest` directly is acceptable
  because Phase 2 tests the send-rule chain, not who may decline (that is
  the voter, covered by rollout Phase 1).

## Historical Context (from prior changes)

- `context/archive/2026-07-22-friendship-requests/plan.md:68-74` — the 4-step
  ordering and "declined/cancelled are history" rules were explicit planning
  decisions; the plan's Phase 2 success criteria listed only isolated cases,
  which is why combinations are untested.
- `9153a78` (2026-08-01) — added the `UniqueConstraintViolationException`
  catch for concurrent sends; no test was added for it.
- `context/archive/2026-08-08-testing-authorization-data-boundary-hardening/`
  — rollout Phase 1; established that service/controller tests reuse fixture
  users by email rather than positional indexes (`17a6e07`).

## Related Research

- `context/archive/2026-07-22-friendship-requests/research.md`
- `context/archive/2026-08-08-testing-authorization-data-boundary-hardening/research.md`

## Open Questions

1. **Addressee status is never checked in `sendRequest`.** As of `5c362f3`,
   `GET /users?search=` hides `inactive`/`blocked`/`deleted`-status users, but
   `POST /friend-requests {email}` still lets anyone send them a request
   (only soft-deleted `deletedAt` users are rejected, via the Gedmo filter →
   `USER_NOT_FOUND`). Not part of risk #4's wording and not stated in the PRD.
   **Decision (user, 2026-11-01): include in this rollout phase** — the plan
   must add a rejection rule for sends to `inactive`/`blocked`/`deleted`-status
   addressees plus tests. Open for `/10x-plan`: which error code (recommended:
   reuse `USER_NOT_FOUND`, so the response does not reveal account status),
   and where the check sits in the chain (recommended: step 0, together with
   addressee resolution, before self/active-row/cooldown).
2. **Concurrent crossed sends.** If A→B and B→A race past
   `findActiveBetween()`, the loser gets `DUPLICATE_FRIEND_REQUEST` and the
   pair stays `pending` instead of becoming friends. State stays valid (no
   invalid row), so it is a UX gap, not data corruption. Testing it
   deterministically needs a seam inside `sendRequest` → recommend accepting
   as residual risk, not testing in this phase.
3. **PRD staleness.** PRD Open Question #3 (cooldown length, "Block: yes") is
   still open in `prd.md:247-248`, although the implementation settled on
   `FRIEND_REQUEST_COOLDOWN_DAYS=3`. Tests should use the injected
   `cooldownDays` value rather than hardcoding 3.
4. **Test-plan §6 not filled after Phase 1.** §6.1 and §6.5 still say "TBD —
   see Phase 1" although Phase 1 is complete. Phase 2's final sub-phase should
   fill §6.1 for both phases.
