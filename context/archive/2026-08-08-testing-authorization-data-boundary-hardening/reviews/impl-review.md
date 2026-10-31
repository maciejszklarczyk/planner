<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Authorization & Data-Boundary Hardening

- **Plan**: context/changes/testing-authorization-data-boundary-hardening/plan.md
- **Scope**: Phase 1 of 2, Phase 2 of 2 (full plan)
- **Date**: 2026-08-08
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 0 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Findings

### F1 — Phase 1 tests deviate from the plan's literal test-setup mechanics

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is documentation, not code
- **Dimension**: Plan Adherence
- **Location**: backend/tests/Functional/Controller/FriendshipControllerTest.php:225-265, 406-441
- **Detail**: The plan specifies creating a fresh pending request via `POST` for each new decline test, and querying `GET /friend-requests`/`GET /friends` as `user_2` for the isolation tests (naming the fixture `user_1 -> user_5` pending row specifically). The implementation instead reuses existing pending rows (fetched via `GET`) for the decline tests, and queries as `user_4` instead of `user_2` for isolation (checking absence of `admin`/`user1` emails rather than independently naming the `user_1 -> user_5` pair). Root cause, confirmed by both the plan-drift sub-agent and inline code comments: `DatabaseTestCase` never rolls back between test methods in a class, so every undirected user pair in the fixture set is already an active relationship by the time these tests run — a literal fresh-`POST` approach for two more pairs would have collided with an existing relationship or left stray pending rows (this was hit and fixed once during implementation). Both adaptations are correctly reasoned, documented inline, and preserve the plan's actual intent.
- **Fix**: Add a short note to Phase 1 of plan.md recording the fixture-saturation constraint and the "reuse via GET, verify with a relationship-free querying user" approach, so a future reader of plan.md doesn't have to rediscover the reasoning from test-file comments alone.
- **Decision**: FIXED

### F2 — New tests inherit a latent cross-class test-isolation hazard

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: backend/tests/DatabaseTestCase.php:15-22; backend/tests/Functional/Service/FriendshipServiceTest.php:41; backend/tests/Functional/Controller/FriendshipControllerTest.php:225-246
- **Detail**: `DatabaseTestCase::$isDbSetUp` is a static flag shared across the entire PHPUnit process, not per test class (no late static binding), so fixtures load exactly once for the whole suite and `FriendshipControllerTest`'s cumulative in-class state persists across methods by design. `FriendshipServiceTest` separately runs an unconditional `DELETE FROM FriendRequest` in its own `setUp()`. Under today's default declaration-order execution, Controller tests run before Service tests, so there's no live collision, and the new `testDeclineFriendRequestAsRequesterIsForbidden`'s positional `outgoing[0]['id']` read is currently unambiguous (traced and confirmed by the safety-review sub-agent). This is pre-existing infrastructure the new tests inherit rather than introduce, but it's fragile to any change in execution order (random order, parallel/paratest runners, a new test class inserted between them).
- **Fix**: No code change required for this change. Track as a follow-up: (1) make the new decline tests filter by a specific `otherUser` email instead of indexing `outgoing[0]`/`incoming[0]` positionally — cheap, removes the one avoidable part of the fragility; (2) separately reconsider whether cross-class fixture/state isolation should be hardened (e.g. per-class transactions) as a broader test-infra improvement, out of scope here.
- **Decision**: FIXED (part 1 only — the two new decline tests now filter by `otherUser.email` instead of indexing `outgoing[0]` positionally; part 2, the broader cross-class test-infra hardening, remains a follow-up, out of scope here)
