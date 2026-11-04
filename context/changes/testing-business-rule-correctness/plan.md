# Business-Rule Correctness (Test Rollout Phase 2) Implementation Plan

## Overview

Rollout Phase 2 of `context/foundation/test-plan.md` (risk #4). We prove that the
friend-request rule chain in `FriendshipService::sendRequest()` lands in the
correct final state when rules overlap on one pair, and we close three gaps
found while grounding it:

1. sending a request to an `inactive`/`blocked`/`deleted`-status user is
   currently allowed (search already hides them since `5c362f3`);
2. two crossed sends racing each other end with `DUPLICATE_FRIEND_REQUEST`
   instead of a friendship;
3. the SQLite test database lacks the active-pair unique index that production
   relies on, so the race backstop has never run under test.

## Current State Analysis

- The rule chain lives in one method, `backend/src/Service/FriendshipService.php:42-100`:
  resolve addressee → self → active row (accepted → `ALREADY_FRIENDS`; reversed
  pending → auto-accept; same-direction pending → `DUPLICATE_FRIEND_REQUEST`)
  → cooldown (latest declined, same direction, strict `<`) → create.
  Part of the logic is in repository queries
  (`backend/src/Repository/FriendRequestRepository.php:25-53`).
- Existing tests (`FriendshipServiceTest`, `FriendshipControllerTest`) cover
  each rule on a clean pair; none overlap two rules, and failure tests assert
  only the exception, never the persisted state (research gaps C1–C9).
- Tests and CI run on **SQLite** (`DatabaseTestCase` runs
  `doctrine:schema:create` from the ORM mapping; CI has no Postgres service).
  The `CHECK (requester_id <> addressee_id)` and the partial unique index
  `uniq_friend_request_active_pair` exist only in the Postgres migration
  `backend/migrations/Version20260801160339.php:28-29`. Verified: the test
  DB's `friend_request` has only the two FK indexes.
- `sendRequest` never checks the addressee's `status`; only soft-deleted
  (`deletedAt`) users are hidden, via the Gedmo filter.
- On a unique-index conflict the service maps to `DuplicateFriendRequestException`
  (`FriendshipService.php:91-97`), and Doctrine closes the EntityManager.
- Fixture users who can still receive requests after this change: `admin`,
  `user1` (active), `user5` (new). Many existing send tests target `user2`
  (inactive), `user3` (blocked), `user4` (deleted-status), including the
  fixture row `friend_request_u5_u2_declined_recent` that the HTTP
  cooldown test depends on.
- `FriendshipControllerTest` does not reset `friend_request` between tests —
  each test needs its own unused user pair.

## Desired End State

- `POST /friend-requests {email}` to an `inactive`, `blocked` or
  `deleted`-status user returns 404 `USER_NOT_FOUND` with the same message as
  an unknown email; no friendship is created, including via crossed-request
  auto-accept.
- Search (`GET /users`) and send use one shared definition of "statuses other
  users cannot reach".
- Concurrent crossed sends end in an accepted friendship; concurrent
  same-direction sends end in `DUPLICATE_FRIEND_REQUEST`; a send racing an
  accept ends in `ALREADY_FRIENDS`.
- The SQLite test DB carries the same active-pair unique index as production.
- A dedicated `FriendshipSendRuleChainTest` pins every overlap case C1–C9 with
  expected values taken from the business rules, not from the service.
- `test-plan.md` §6 documents the patterns for rollout Phases 1 and 2.

Verify: full backend suite green; `--filter FriendshipSendRuleChainTest`
green; deliberately swapping the active-row and cooldown blocks in
`sendRequest` makes C1 fail.

### Key Discoveries:

- `backend/tests/Functional/Service/FriendshipServiceTest.php:31-62` — harness to reuse: per-test `DELETE FROM FriendRequest`, `service(now, cooldownDays)` with `MockClock`, `user(email)`.
- `backend/tests/Functional/Controller/FriendshipControllerTest.php:361-383` — pattern for swapping `ClockInterface` with `MockClock` in the container for HTTP cooldown tests.
- `backend/src/Exception/UserNotFoundByEmailException.php` — 404 `USER_NOT_FOUND`; `frontend/lib/apiErrors.ts:5` already maps it, so no frontend change.
- `backend/src/Controller/UserController.php:49-54` — search currently lists `INACTIVE, BLOCKED, DELETED` inline; becomes the shared enum helper.
- `backend/tests/Functional/Controller/UserControllerTest.php:20-24` — `search=user1` asserts exactly one hit; new fixture emails must not contain `user1`.
- `FriendRequestRepository` is a non-final `ServiceEntityRepository` — a test subclass can override `findActiveBetween()` to simulate the race window.

## What We're NOT Doing

- Not changing what happens to **existing** pending requests or friendships with users who later become inactive/blocked/deleted (accept, lists). Not selected in planning.
- Not checking the **requester's** own status (no `UserChecker` exists; that is a login concern, outside Friendship).
- Not moving tests or CI to Postgres.
- Not adding the `CHECK (requester_id <> addressee_id)` constraint to SQLite (needs a table rebuild there; self-request is already rejected by the service and covered by tests).
- Not adding Postgres advisory locks or `SELECT … FOR UPDATE`.
- Not touching the frontend.
- Not testing every overlap case over HTTP — only the status rule, C1 and C4.

## Implementation Approach

Bottom-up, tests before refactor: first make the test DB and fixtures able to
express the rules (Phase 1), then add the new status rule with its tests
(Phase 2), then pin the full rule chain with overlap tests (Phase 3). Only then
restructure `sendRequest` for the retry-on-conflict path (Phase 4), protected
by Phase 3. Phase 5 records the patterns.

Expected values in every new test come from the archived friendship-requests
plan's *Critical Implementation Details*
(`context/archive/2026-07-22-friendship-requests/plan.md:68-74`), PRD FR-006,
and the decisions in this plan — never read back from `FriendshipService`.

## Critical Implementation Details

**State sequencing**: the final check order in `sendRequest` is resolve
addressee → self → addressee status → active row → cooldown → create. The
status check must come before the active-row check, otherwise a pending
request *from* a now-blocked user would be auto-accepted.

**Timing & lifecycle**: after `UniqueConstraintViolationException` Doctrine
has closed the EntityManager and every entity loaded through it is stale —
including the `User $requester` passed in by the controller. The retry must
reset the manager through `ManagerRegistry`, re-load requester and addressee
by id, and run the rule chain exactly once more; a second conflict maps to
`DuplicateFriendRequestException` (no loops).

## Phase 1: Test-DB Parity and Fixtures

### Overview

Give the SQLite test DB the production active-pair invariant and add fixture
users that can still receive requests after Phase 2. No production behavior
changes.

### Changes Required:

#### 1. Active-pair unique index in the test schema

**File**: `backend/tests/DatabaseTestCase.php`

**Intent**: After `doctrine:schema:create` and before fixtures load, create the
SQLite equivalent of `uniq_friend_request_active_pair`, so tests see the same
backstop as production. Only when the connection platform is SQLite.

**Contract**: same index name; SQLite scalar `min()/max()` replace
`LEAST/GREATEST`:
```sql
CREATE UNIQUE INDEX uniq_friend_request_active_pair
  ON friend_request (min(requester_id, addressee_id), max(requester_id, addressee_id))
  WHERE status IN ('pending', 'accepted');
```
A comment points to the Postgres migration as the source of truth.

#### 2. Friendship fixture users

**File**: `backend/fixtures/users.yaml`

**Intent**: Add enough `active` users for every send-related controller test to
own an unused pair (the controller suite does not reset `friend_request`).

**Contract**: new users `friend_1` … `friend_N` (N decided by the
implementer after counting tests, ~8), emails `friendN@example.com`,
`status: active`, `ROLE_USER`. Emails must not contain `user1` (see Key
Discoveries). No group memberships.

#### 3. Cooldown fixture row on an active pair

**File**: `backend/fixtures/friend_requests.yaml`

**Intent**: Move `friend_request_u5_u2_declined_recent` to a pair of active
users so the HTTP cooldown test keeps returning 429 after Phase 2, and update
the controller test that relies on it.

**Contract**: same timing (`createdAt now - 2 days`, `respondedAt now - 1 day`),
new requester/addressee from the new fixture users; `testSendFriendRequestRejectsWhileCooldownActive` points at that pair.

### Success Criteria:

#### Automated Verification:

- Full backend suite passes: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit`
- The index exists in the test DB: `sqlite_master` lists `uniq_friend_request_active_pair` for `friend_request`

#### Manual Verification:

(none)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Reject Requests to Unreachable Users

### Overview

One shared definition of statuses other users cannot reach, used by search and
by `sendRequest`; existing tests re-pointed to reachable users; new tests for
the rule.

### Changes Required:

#### 1. Shared status definition

**File**: `backend/src/Entity/Enum/UserStatusEnum.php`

**Intent**: Single source for "hidden from other users".

**Contract**: a static method returning `[INACTIVE, BLOCKED, DELETED]` (name
chosen by the implementer, e.g. `hiddenFromOtherUsers()`), plus an
instance-level check if convenient. `UserController::search`
(`backend/src/Controller/UserController.php:53`) uses it instead of the inline
list.

#### 2. Status check in the send chain

**File**: `backend/src/Service/FriendshipService.php`

**Intent**: Reject sends to unreachable addressees, indistinguishable from an
unknown email.

**Contract**: after the self check, before `findActiveBetween()`; throws
`UserNotFoundByEmailException` with exactly the same message as the
unknown-email branch (`FriendshipService.php:46`). Docblock `@throws` updated.

#### 3. Re-point existing tests

**Files**: `backend/tests/Functional/Service/FriendshipServiceTest.php`, `backend/tests/Functional/Controller/FriendshipControllerTest.php`

**Intent**: Every test that sends to `user2`/`user3`/`user4` today sends to a
reachable user, so it keeps testing its own rule instead of hitting the new one.

**Contract**: service tests use pairs among `admin`/`user1`/`user5` (table is
reset per test); controller tests use unused `friendN` pairs. Test names and
asserted behaviors unchanged.

#### 4. New tests for the status rule

**Files**: `backend/tests/Functional/Service/FriendshipServiceTest.php`, `backend/tests/Functional/Controller/FriendshipControllerTest.php`

**Intent**: Prove the rule from the outside.

**Contract**:
- Service, data provider over `user2`/`user3`/`user4`: send → `UserNotFoundByEmailException`, message equal to the unknown-email message for the same input pattern, and no `friend_request` row created.
- Service: a pending request from a reachable user that later becomes blocked (status changed in the test) → the other side sending back gets `UserNotFoundByEmailException`, and the original row stays `pending` (no auto-accept).
- HTTP: `POST /friend-requests` to a blocked user → 404, `error = USER_NOT_FOUND`, `message` identical to the unknown-email response.

### Success Criteria:

#### Automated Verification:

- Friendship tests pass: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit --filter 'FriendshipServiceTest|FriendshipControllerTest'`
- User search tests still pass: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit --filter 'UserControllerTest|UserRepositoryTest'`
- Full backend suite passes: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit`

#### Manual Verification:

- `curl -X POST localhost:8000/friend-requests -H 'X-Dev-User: user1@example.com' -H 'Content-Type: application/json' -d '{"email":"user3@example.com"}'` returns 404 with the same message as for `nobody@example.com`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Rule-Chain Overlap Tests

### Overview

Pin the rule chain where two rules meet on one pair. Tests only — no
production code changes.

### Changes Required:

#### 1. Shared harness

**Files**: new trait or abstract base under `backend/tests/Functional/Service/` (e.g. `FriendshipServiceTestTrait`), `backend/tests/Functional/Service/FriendshipServiceTest.php`

**Intent**: Reuse `service(now, cooldownDays)`, `user(email)` and the per-test
`friend_request` reset in both service test classes, plus one new helper that
returns all rows for a pair (id, direction, status) to assert persisted state.

**Contract**: `FriendshipServiceTest` behavior unchanged after extraction.

#### 2. Overlap test class

**File**: `backend/tests/Functional/Service/FriendshipSendRuleChainTest.php` (new)

**Intent**: One test per overlap case. History rows (declined at a chosen
time, cancelled) are built through the service with different `MockClock`
instances; cooldown arithmetic uses the injected `cooldownDays`, not a
hardcoded 3.

**Contract** (expected outcomes from the business rules; A, B are reachable users):

| Test | Setup on pair A/B | Action | Expected |
|---|---|---|---|
| C1 | A→B declined, A still in cooldown; B→A pending | A sends to B | B→A row accepted (same id); no new row |
| C2 | A→B declined, A in cooldown | B sends to A | new pending B→A |
| C3 | A→B declined at T | A sends at T+cooldown / T+cooldown−1s | allowed / `FriendRequestCooldownActiveException` |
| C4 | A→B declined twice: old (expired) and recent (active) | A sends | `FriendRequestCooldownActiveException` |
| C5 | B→A declined | A sends to B | new pending A→B; B→A stays declined |
| C6 | B→A cancelled | A sends to B | new pending A→B; B→A stays cancelled |
| C7 | A→B declined → cooldown passes → A resends → B accepts | A sends | `AlreadyFriendsException` |
| C8 | data provider: self, duplicate, already-friends, cooldown, unreachable addressee | rejected send | rows for the pair identical before and after |
| C9 | A→B pending | B sends to A | `respondedAt` = mock clock now; both pending lists empty; exactly one row, accepted |

Each test names the regression it guards in a one-line comment (e.g. C1:
"cooldown check moved before active-row check").

#### 3. HTTP overlap tests

**File**: `backend/tests/Functional/Controller/FriendshipControllerTest.php`

**Intent**: Show C1 and C4 through the API (status codes and envelope).

**Contract**: C1 → 201 with the accepted request; C4 → 429
`FRIEND_REQUEST_COOLDOWN_ACTIVE`. History rows set up via the API plus a
`MockClock` swapped into the container (pattern at `FriendshipControllerTest.php:361-383`), on unused `friendN` pairs.

### Success Criteria:

#### Automated Verification:

- Overlap tests pass: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit --filter FriendshipSendRuleChainTest`
- Existing friendship tests pass after the harness extraction: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit --filter 'FriendshipServiceTest|FriendshipControllerTest'`
- Full backend suite passes: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit`

#### Manual Verification:

- Deliberate-break check: temporarily move the cooldown block above the active-row block in `sendRequest` → C1 fails; temporarily flip `ORDER BY respondedAt DESC` to `ASC` → C4 fails; revert both

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: Retry the Rule Chain After a Concurrent-Send Conflict

### Overview

Make the race outcome match the rules: on an active-pair conflict, re-run the
chain once on fresh state.

### Changes Required:

#### 1. Service restructuring

**File**: `backend/src/Service/FriendshipService.php`

**Intent**: Extract the rule chain (resolve → self → status → active row →
cooldown → create) into a private method parameterised by the
EntityManager/repositories to use. `sendRequest` runs it; on
`UniqueConstraintViolationException` it resets the manager via
`ManagerRegistry`, re-loads requester and addressee by id from the fresh
manager, and runs the chain once more. A second conflict →
`DuplicateFriendRequestException`.

**Contract**: public signature of `sendRequest(User $requester, string $addresseeEmail): FriendRequest` unchanged; constructor gains `ManagerRegistry`; existing `@throws` list unchanged. The returned entity belongs to the live (possibly new) manager.

#### 2. Race tests

**File**: `backend/tests/Functional/Service/FriendshipSendRuleChainTest.php`

**Intent**: Simulate the race window deterministically: the first service
instance gets a `FriendRequestRepository` subclass whose `findActiveBetween()`
returns `null` on its first call (the competing row is already in the DB);
the retry uses the real repository from the reset manager.

**Contract**:
- B→A pending in DB; A sends to B through the race double → the B→A row is accepted, exactly one row for the pair.
- A→B pending in DB; A sends again through the double → `DuplicateFriendRequestException`, still one pending row.
- A/B accepted in DB; A sends through the double → `AlreadyFriendsException`.
- Each race test first asserts the unique index fired (without the Phase 1 index the double would create a second row — the test must fail in that case).

### Success Criteria:

#### Automated Verification:

- Race and overlap tests pass: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit --filter FriendshipSendRuleChainTest`
- Full backend suite passes: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit`
- Static analysis and style pass on changed files (run only after asking the user): `vendor/bin/phpstan analyse` and `vendor/bin/php-cs-fixer fix --dry-run --diff`

#### Manual Verification:

- Deliberate-break check: temporarily remove the retry (re-throw `DuplicateFriendRequestException` directly) → the crossed race test fails; revert

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 5: Documentation and Cookbook

### Overview

Record what shipped so the next test follows the same patterns.

### Changes Required:

#### 1. Test plan cookbook

**File**: `context/foundation/test-plan.md`

**Intent**: Fill §6.1 (backend unit/integration: authorization pattern from rollout Phase 1, rule-chain overlap + race-double patterns from Phase 2) and §6.5 (adversarial input, from Phase 1); add a §6.6 note for Phase 2; note in §4 that backend tests run on SQLite with a hand-maintained copy of the active-pair index.

**Contract**: no file:line anchors in §1/§2; §6 may name test files.

#### 2. Backend test note

**File**: `backend/CLAUDE.md` (Tests section)

**Intent**: Tell future contributors that the test DB is SQLite built from the ORM mapping, so DB-level invariants added by raw SQL in migrations must be mirrored in `DatabaseTestCase`; and that new friendship tests need reachable (`active`/`new`) addressees.

**Contract**: two bullets.

#### 3. Research correction

**File**: `context/changes/testing-business-rule-correctness/research.md`

**Intent**: Correct the "real Postgres" claim to SQLite, as a dated follow-up section.

**Contract**: `## Follow-up Research 2026-11-01`, frontmatter `last_updated_note` updated.

### Success Criteria:

#### Automated Verification:

- `test-plan.md` §6.1 and §6.5 no longer contain "TBD"

#### Manual Verification:

- Cookbook entries read correctly to someone adding a new friendship rule test

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- None new — the rule logic spans service and repository queries, so isolated unit tests with mocked repositories would mirror the implementation.

### Integration Tests:

- Service-level, DB-backed (SQLite + `MockClock`): status rule, C1–C9, three race cases.
- HTTP: status rule (404 envelope, no status leak), C1 (201 accepted), C4 (429).

### Manual Testing Steps:

1. `POST /friend-requests` to a blocked user and to an unknown email — identical 404 bodies apart from `timestamp`/`path`.
2. Deliberate-break checks listed in Phases 3 and 4.

## Performance Considerations

None. The status check uses the already-loaded addressee; the retry runs only on a unique-index conflict.

## Migration Notes

No production schema change. The SQLite index exists only in the test schema.

## References

- Research: `context/changes/testing-business-rule-correctness/research.md`
- Test plan: `context/foundation/test-plan.md` §2 risk #4, §3 Phase 2
- Business rules (oracle): `context/archive/2026-07-22-friendship-requests/plan.md:68-74`, `context/foundation/prd.md:158`
- Rule chain: `backend/src/Service/FriendshipService.php:42-100`
- Production invariants: `backend/migrations/Version20260801160339.php:28-29`

## Addendum (impl-review 2026-11-04)

- `backend/src/Dto/Response/FriendRequestDto.php` — changed in Phase 4 although not listed: compares users by id instead of `===`, because after the retry's EntityManager reset the viewer and the request's requester can be different objects for the same user. Covered by `backend/tests/Unit/Dto/FriendRequestDtoTest.php` (review finding F1).

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Test-DB Parity and Fixtures

#### Automated

- [x] 1.1 Full backend suite passes — 0da7068
- [x] 1.2 The index exists in the test DB — 0da7068

### Phase 2: Reject Requests to Unreachable Users

#### Automated

- [x] 2.1 Friendship tests pass — c54d63e
- [x] 2.2 User search tests still pass — c54d63e
- [x] 2.3 Full backend suite passes — c54d63e

#### Manual

- [ ] 2.4 POST to a blocked user returns 404 with the same message as an unknown email

### Phase 3: Rule-Chain Overlap Tests

#### Automated

- [x] 3.1 Overlap tests pass — 5971a07
- [x] 3.2 Existing friendship tests pass after the harness extraction — 5971a07
- [x] 3.3 Full backend suite passes — 5971a07

#### Manual

- [ ] 3.4 Deliberate-break check (C1 and C4 fail when broken)

### Phase 4: Retry the Rule Chain After a Concurrent-Send Conflict

#### Automated

- [x] 4.1 Race and overlap tests pass — 2a07009
- [x] 4.2 Full backend suite passes — 2a07009
- [x] 4.3 Static analysis and style pass on changed files — 2a07009

#### Manual

- [ ] 4.4 Deliberate-break check (crossed race test fails without retry)

### Phase 5: Documentation and Cookbook

#### Automated

- [x] 5.1 test-plan.md §6.1 and §6.5 no longer contain "TBD" — eba3661

#### Manual

- [ ] 5.2 Cookbook entries read correctly
