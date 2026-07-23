# Friendship Requests Implementation Plan

## Overview

We're building the Friendship domain (roadmap slice S-01, PRD refs US-02/FR-005/FR-006/FR-007): a user can send a friend request by email, the recipient can accept or decline it, both parties then see each other on a friends list, and a decline starts a 3-day (env-configurable) cooldown before the same sender can re-request the same recipient. This is the prerequisite for S-02 (event ownership + friend-gated invites).

**Depends on**: `context/changes/api-exception-handling/` (roadmap Foundation `F-01`) must land first. That change introduces `ApiExceptionInterface` and the project-wide `kernel.exception` listener that every Friendship domain exception (Phase 2 below) is built on. This plan was originally scoped together with that infrastructure work, then split once the exception-handling migration turned out to be a genuine cross-cutting concern unrelated to Friendship — see `context/foundation/roadmap.md`'s Foundations section for the rationale.

## Current State Analysis

- **No Friendship/FriendRequest domain exists at all.** No entity, no migration, no controller, no tests.
- The Group↔User relation (`UserHasGroup`) and the invitation-token flow (`UserInvitationToken`) are the two closest analogues for the new Friendship entity/repository/service/controller, per `context/changes/friendship-requests/research.md`.
- `src/Repository/UserHasGroupRepository.php:24-41` (`findByUserAndGroup`/`isUserInGroup`) is the direct template for a symmetric pair-lookup.
- `src/Security/GroupVoter.php` is the only voter in the codebase today, and the template for the new `FriendshipVoter`.
- `symfony/rate-limiter` is a declared but entirely unused dependency in `src/` — no custom `RateLimiterFactory` exists anywhere to draw from.

## Desired End State

A user can, as `ROLE_USER`:
- `POST /friend-requests` with `{email}` to send a request to another user (rejected if self, duplicate, already-friends, or cooldown-active; auto-accepted if the target already has a pending request to them).
- `GET /friend-requests` to see their own incoming and outgoing pending requests.
- `POST /friend-requests/{id}/accept` / `POST /friend-requests/{id}/decline` — only the recipient may act, enforced by a voter.
- `GET /friends` to see their accepted friends.

Every Friendship error response uses the project-wide envelope established by `api-exception-handling`: `{"error": "CODE", "message": "...", "timestamp": "...", "path": "..."}`.

**Verification**: new `FriendshipControllerTest` functional tests cover send/accept/decline/list/cooldown/crossed-request/self-request/duplicate end-to-end.

### Key Discoveries:

- `src/Repository/UserHasGroupRepository.php:24-41` (`findByUserAndGroup`/`isUserInGroup`) is the direct template for a symmetric pair-lookup, generalized with an OR-clause for `FriendRequestRepository::findActiveBetween()`.
- `src/Service/InvitationMailer.php:13-20` shows the established `#[Autowire(env: ...)]` pattern for injecting env config into a service — used here for the cooldown duration.
- `config/services.yaml` autowires/autoconfigures everything under `src/` (`App\: resource: '../src/'`), so no manual service wiring is needed for the new repository/service/controller/voter.
- `src/Security/GroupVoter.php` gives ROLE_ADMIN a bypass because Group is an admin-manageable resource. **Friendship is not** — nothing in the PRD gives `ROLE_ADMIN` any special capability over another user's friend requests, so `FriendshipVoter` deliberately does **not** replicate that bypass.
- `symfony/rate-limiter` is a declared but entirely unused dependency in `src/` — the cooldown is implemented as a manual timestamp comparison (mirroring how `UserInvitationToken` expiry is checked), not via `RateLimiterFactory`.

## What We're NOT Doing

- **Unfriending / removing an accepted friendship** — not requested by any FR in the PRD. Per user decision, this is explicitly flagged as a follow-up topic for this MVP's backlog (see `context/foundation/roadmap.md` → `## Parked`), not built in this slice.
- **Rate-limiting how many friend requests a user can send** — PRD Open Question #2, resolved as "no limit in MVP."
- **Group self-service** — PRD Non-Goal, unrelated to this slice.
- **Event ownership/invites (S-02)** — the next roadmap slice, blocked on this one; not started here.
- **Building or modifying the project-wide exception-handling infrastructure** — that's `context/changes/api-exception-handling/`, a prerequisite this plan depends on but does not itself implement.

## Implementation Approach

Data model first (entity, migration, repository, fixtures), then the service layer's business rules (self-request, duplicate, crossed-request auto-accept, cooldown), then the HTTP layer (controller, voter, DTOs) on top. Every domain exception introduced here implements `ApiExceptionInterface` from the prerequisite `api-exception-handling` change — no new error-handling pattern is invented in this plan.

## Critical Implementation Details

**Timing & lifecycle**: `respondedAt` on `FriendRequest` is set on both accept *and* decline — it's not decline-specific. The cooldown check (Phase 2) reads it only from the most recent row where `status = declined` and `requester = <original sender>`, so accepted rows never affect cooldown math.

**State sequencing**: The "crossed request" rule only applies when a *pending* row exists in the reverse direction. A *declined* row in either direction is just history — it neither blocks a fresh request nor triggers auto-accept. Get the order of checks right in the service: (1) self-request, (2) existing active (pending/accepted) row in either direction — pending-reverse auto-accepts, pending-same-direction or accepted rejects as duplicate — (3) only if no active row exists, check cooldown against the latest declined-same-direction row, (4) create.

## Phase 1: Friendship Data Model

### Overview

The entity, enum, migration, repository, and fixtures for the Friendship domain — no business logic yet, just the persistence layer.

### Changes Required:

#### 1. Status enum

**File**: `src/Entity/Enum/FriendshipStatusEnum.php`

**Intent**: Mirror `UserGroupRoleEnum`'s minimal string-backed shape.

**Contract**: `enum FriendshipStatusEnum: string { case PENDING = 'pending'; case ACCEPTED = 'accepted'; case DECLINED = 'declined'; }`.

#### 2. Entity

**File**: `src/Entity/FriendRequest.php`

**Intent**: One row per send *attempt* (the "new row per attempt" model decided during planning, preserving decline history for cooldown checks) — not one row per user pair. `requester`/`addressee` are the two `ManyToOne(User::class)` sides; `respondedAt` is set on both accept and decline (see Critical Implementation Details).

**Contract**: `int` autoincrement PK (matching every other entity in this codebase — no UUID). Fields: `requester` (`ManyToOne(User::class)`, `JoinColumn(nullable: false)`), `addressee` (same), `status` (`#[ORM\Column(enumType: FriendshipStatusEnum::class)]`, default `PENDING`), `createdAt` (`\DateTimeImmutable`, set in constructor, following `UserActivityLog`'s manual-field style — no shared timestamp trait exists in this codebase), `respondedAt` (nullable `\DateTimeImmutable`).

#### 3. Migration

**File**: new Doctrine migration (generate via `bin/console doctrine:migrations:diff` or `make:migration`)

**Intent**: Create `friend_request` table matching the codebase's existing migration conventions (int identity PK, `TIMESTAMP(0) WITHOUT TIME ZONE`, explicit index per FK).

**Contract**: Beyond the standard columns/FKs, two invariants that must be enforced at the DB level, not just in application code (the codebase's one existing pair-relation, `user_has_group`, shipped *without* a DB-level uniqueness guarantee — a gap this migration must not repeat):
```sql
ALTER TABLE friend_request ADD CONSTRAINT friend_request_no_self_request CHECK (requester_id <> addressee_id);
CREATE UNIQUE INDEX uniq_friend_request_active_pair ON friend_request (LEAST(requester_id, addressee_id), GREATEST(requester_id, addressee_id)) WHERE status IN ('pending', 'accepted');
```
The partial unique index allows unlimited *declined* history rows for a pair while guaranteeing only one active (pending or accepted) relationship can ever exist between any two users, regardless of who is `requester` vs `addressee` — this is what makes the crossed-request and duplicate-request checks in Phase 2 race-safe rather than just application-level best-effort.

#### 4. Repository

**File**: `src/Repository/FriendRequestRepository.php`

**Intent**: Give the service layer the four lookups it needs, generalizing `UserHasGroupRepository::findByUserAndGroup`'s pair-lookup pattern to a symmetric, direction-aware one.

**Contract**:
- `findActiveBetween(int $userAId, int $userBId): ?FriendRequest` — the pending-or-accepted row between the pair in either direction (mirrors the partial unique index's scope).
- `findLatestDeclinedBySender(int $requesterId, int $addresseeId): ?FriendRequest` — most recent `declined` row in that *specific* direction, for the cooldown check.
- `findAcceptedForUser(int $userId): array` — all `accepted` rows where the user is either side, eager-loading the other side's `User` (mirroring `UserHasGroupRepository::findByGroup`'s `join`/`addSelect` N+1 avoidance).
- `findPendingForUser(int $userId): array{incoming: FriendRequest[], outgoing: FriendRequest[]}`.

#### 5. Fixtures

**File**: `fixtures/friend_requests.yaml` (new; add to the load array in `src/DataFixtures/AppFixtures.php`)

**Intent**: Deterministic Alice-YAML rows covering every state the service/tests need: an accepted pair, a pending outgoing request, a declined request within the cooldown window, and a declined request past the cooldown window (for testing the boundary).

**Contract**: Follow `fixtures/user_has_groups.yaml`'s `@user_x` reference syntax; reference existing `user_1`..`user_5`.

### Success Criteria:

#### Automated Verification:

- Migration applies cleanly against the test DB: `docker compose run --rm php bin/console doctrine:migrations:migrate --env=test --no-interaction`
- Fixtures load without error: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/console doctrine:fixtures:load --no-interaction --env=test`
- `doctrine:schema:validate` reports no mapping errors

#### Manual Verification:

- Inspecting the `friend_request` table after fixture load shows the expected rows and that the partial unique index exists (`\d friend_request` in psql)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Friendship Service Layer

### Overview

The business rules: self-request rejection, duplicate/already-friends rejection, crossed-request auto-accept, and the cooldown check — all the decisions made during planning, now implemented behind a `ClockInterface`-driven service so the cooldown is testable without waiting 3 real days.

**Prerequisite**: `ApiExceptionInterface` (from `context/changes/api-exception-handling/`) must already exist in `src/Exception/` before this phase's exception classes can implement it.

### Changes Required:

#### 1. Clock dependency

**File**: `composer.json`

**Intent**: Make `symfony/clock` an explicit direct dependency (it's very likely already present transitively via `symfony/http-kernel`, but this codebase has no prior use of `ClockInterface`, so declare it explicitly rather than rely on an implicit transitive resolution).

**Contract**: Add `"symfony/clock": "7.4.*"` to `require`.

#### 2. Domain exceptions

**Files**: `src/Exception/CannotFriendSelfException.php` (422, `CANNOT_FRIEND_SELF`), `src/Exception/UserNotFoundByEmailException.php` (404, `USER_NOT_FOUND`), `src/Exception/AlreadyFriendsException.php` (409, `ALREADY_FRIENDS`), `src/Exception/DuplicateFriendRequestException.php` (409, `DUPLICATE_FRIEND_REQUEST`), `src/Exception/FriendRequestCooldownActiveException.php` (429, `FRIEND_REQUEST_COOLDOWN_ACTIVE`), `src/Exception/FriendRequestNotFoundException.php` (404, `FRIEND_REQUEST_NOT_FOUND`), `src/Exception/FriendRequestNotPendingException.php` (409, `FRIEND_REQUEST_NOT_PENDING`)

**Intent**: One exception per business rule identified during planning, each `implements ApiExceptionInterface` per the `api-exception-handling` convention.

**Contract**: Same flat, one-class-per-exception shape as the codebase's existing exceptions (no shared abstract base — consistent with `CannotRemoveLastOwnerException` et al.).

#### 3. Env config

**Files**: `.env`, `.env.test`

**Intent**: Cooldown duration must be configurable, not hardcoded (this was the explicit reason `UserInvitationToken`'s hardcoded `+1 day` was flagged as an anti-pattern during research).

**Contract**: `FRIEND_REQUEST_COOLDOWN_DAYS=3` in both files.

#### 4. Service

**File**: `src/Service/FriendshipService.php`

**Intent**: Constructor-injected `EntityManagerInterface`, `FriendRequestRepository`, `UserRepository`, `ClockInterface`, and `#[Autowire(env: 'int:FRIEND_REQUEST_COOLDOWN_DAYS')] int $cooldownDays` — following `GroupMembershipService`'s plain-service convention (no interfaces/ports).

**Contract**: Four public methods:
- `sendRequest(User $requester, string $addresseeEmail): FriendRequest` — resolves the addressee by email (`UserNotFoundByEmailException` if none), rejects self-request (`CannotFriendSelfException`), checks `findActiveBetween()`: an accepted row → `AlreadyFriendsException`; a pending row where the *existing* requester is the current addressee (i.e. reversed) → flips that row to `accepted`, sets `respondedAt`, returns it (no new row — the crossed-request auto-accept decided during planning); a pending row in the same direction → `DuplicateFriendRequestException`. Only if no active row exists, checks `findLatestDeclinedBySender()` against `$clock->now()` minus `$cooldownDays` → `FriendRequestCooldownActiveException` if still within the window. Otherwise creates and persists a new `pending` row.
- `acceptRequest(FriendRequest $request): FriendRequest` / `declineRequest(FriendRequest $request): FriendRequest` — both require `$request->getStatus() === PENDING` (else `FriendRequestNotPendingException`), set status + `respondedAt = $clock->now()`, flush. **Authorization (only the addressee may call these) is enforced by the voter/controller layer, not here** — mirrors `GroupMembershipService` not self-checking permissions.
- `listFriends(User $user): array` / `listPending(User $user): array{incoming: array, outgoing: array}` — thin wrappers over the repository methods.

@throws PHPDoc tags on each method listing every exception, per the `GroupMembershipService` convention.

### Success Criteria:

#### Automated Verification:

- `composer show symfony/clock` confirms the package is resolvable
- Unit/functional tests for the service (written in this phase, covering: self-request, duplicate same-direction, crossed-request auto-accept, already-friends, cooldown-active, cooldown-expired, successful send/accept/decline) pass: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit --filter=FriendshipService`

#### Manual Verification:

- None beyond automated — this phase has no HTTP surface yet; manual verification happens in Phase 3

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Friendship HTTP Layer & Tests

### Overview

The controller, voter, DTOs, and routes that expose the service to `ROLE_USER`, plus end-to-end functional tests including a `MockClock`-based cooldown-expiry test.

### Changes Required:

#### 1. Voter

**File**: `src/Security/FriendshipVoter.php`

**Intent**: Only the request's addressee may accept or decline it. Deliberately no `ROLE_ADMIN` bypass (see Key Discoveries) — Friendship is a personal relationship, not an admin-manageable resource like Group.

**Contract**: `supports()` restricted to attributes `accept`/`decline` and subject `instanceof FriendRequest`; `voteOnAttribute()` returns `$user === $subject->getAddressee()`. Mirrors `GroupVoter`'s structure minus the admin short-circuit.

#### 2. Request DTO

**File**: `src/Dto/Friendship/SendFriendRequestDto.php`

**Intent**: Validate the target email.

**Contract**: `final readonly class` per `CreateEventDto`'s preferred style; single `string $email` property with `#[Assert\NotBlank]`/`#[Assert\Email]`.

#### 3. Response DTO

**File**: `src/Dto/Response/FriendRequestDto.php`

**Intent**: Represent a single pending/accepted/declined request for the `GET /friend-requests` response, flattening the *other* user (not both sides — the client already knows who they are) into the existing `UserListItemDto`.

**Contract**: Static `fromEntity(FriendRequest $request, User $viewingAs): self` and `fromEntities(array $requests, User $viewingAs): array`, following the codebase's `fromEntity`/`fromEntities` factory convention. Fields: `id`, `otherUser` (`UserListItemDto`), `status` (`.value` string), `createdAt`. The friend list itself (`GET /friends`) reuses the existing `UserListItemDto::fromEntities()` directly — no new DTO needed there.

#### 4. Controller

**File**: `src/Controller/FriendshipController.php`

**Intent**: Expose the service under `ROLE_USER` (no admin gate), following `Admin\GroupMembershipController`'s conventions minus the `#[IsGranted('ROLE_ADMIN')]` class-level attribute.

**Contract**: `#[Route('/friend-requests')]`/`#[Route('/friends')]` class-level split or per-action routes — routes:
- `POST /friend-requests` — `#[MapRequestPayload] SendFriendRequestDto $dto`, `#[CurrentUser] User $user` → `FriendshipService::sendRequest()` → `FriendRequestDto::fromEntity()`.
- `GET /friend-requests` — `FriendshipService::listPending()` → `{"incoming": [...], "outgoing": [...]}` of `FriendRequestDto`.
- `POST /friend-requests/{id}/accept` / `POST /friend-requests/{id}/decline` — `#[MapEntity(id: 'id')] FriendRequest $request`, `#[IsGranted('accept', 'request')]` / `#[IsGranted('decline', 'request')]` → service call → `FriendRequestDto::fromEntity()`.
- `GET /friends` — `FriendshipService::listFriends()` → `UserListItemDto::fromEntities()`.

No new `access_control` entry needed — falls under the existing catch-all `{ path: ^/, roles: ROLE_USER }`.

#### 5. Functional tests

**File**: `tests/Functional/Controller/FriendshipControllerTest.php`

**Intent**: End-to-end coverage of every rule decided during planning, using the `GroupControllerTest`-style session-login + dynamic fixture-ID lookup pattern (the better template per research, given Friendship shares Group's "only participant can act" 403 shape).

**Contract**: Cooldown-expiry test overrides the container's `ClockInterface` with `Symfony\Component\Clock\MockClock` before making the request:
```php
self::getContainer()->set(ClockInterface::class, new MockClock('+4 days'));
```
Covers: send success, self-request rejection (422), duplicate same-direction (409), crossed-request auto-accept (200, no new row created), already-friends rejection (409), cooldown-active rejection (429, using the fixture's recently-declined row), cooldown-expired success (using `MockClock`), accept as non-addressee (403 via voter), accept/decline on a non-pending request (409), friend list and pending list contents.

### Success Criteria:

#### Automated Verification:

- Full test suite passes: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit`
- `doctrine:schema:validate` still reports no errors after the full migration

#### Manual Verification:

- End-to-end flow tested via Bruno: user_1 sends a request to user_4 (no prior relation per fixtures), user_4 accepts, both `/friends` lists show each other
- Cooldown manually verified against a real (non-mocked) recently-declined fixture pair: re-send attempt returns 429 with `FRIEND_REQUEST_COOLDOWN_ACTIVE`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- `FriendshipService` business rules in isolation (Phase 2), particularly the ordering of checks described in Critical Implementation Details

### Integration Tests:

- Full functional suite per phase (Phase 3 adds the new `FriendshipControllerTest`)

### Manual Testing Steps:

1. Before starting: confirm `context/changes/api-exception-handling/` has landed (its own plan's success criteria are green) — this plan assumes `ApiExceptionInterface` already exists.
2. After Phase 3: run the full send → accept → friends-list flow via Bruno for two fresh users, then the send → decline → cooldown-blocked-resend → (with real time or a temporary short cooldown) resend-succeeds flow

## Performance Considerations

None specific — data volumes are small per the PRD's `target_scale` (`users: small, qps: low`), and all new queries are indexed lookups on FK/pair columns.

## Migration Notes

No backfill needed — Friendship is a wholly new table with no pre-existing data. The `friend_request` migration is purely additive to the schema; no existing table is altered.

## References

- Related research: `context/changes/friendship-requests/research.md`
- Similar implementation: `src/Service/GroupMembershipService.php`, `src/Security/GroupVoter.php`, `src/Repository/UserHasGroupRepository.php:24-41`
- Depends on: `context/changes/api-exception-handling/plan.md` (must land first)
- Follow-up backlog item (per user decision, out of scope for this plan but tracked for this MVP): unfriending / removing an accepted friendship — see `context/foundation/roadmap.md` → `## Parked`.

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Friendship Data Model

#### Automated

- [ ] 1.1 Migration applies cleanly against the test DB
- [ ] 1.2 Fixtures load without error
- [ ] 1.3 doctrine:schema:validate reports no mapping errors

#### Manual

- [ ] 1.4 Inspecting the friend_request table after fixture load shows the expected rows and that the partial unique index exists

### Phase 2: Friendship Service Layer

#### Automated

- [ ] 2.1 composer show symfony/clock confirms the package is resolvable
- [ ] 2.2 Service tests (self-request, duplicate, crossed-request auto-accept, already-friends, cooldown-active, cooldown-expired, successful send/accept/decline) pass

### Phase 3: Friendship HTTP Layer & Tests

#### Automated

- [ ] 3.1 Full test suite passes
- [ ] 3.2 doctrine:schema:validate still reports no errors after the full migration

#### Manual

- [ ] 3.3 End-to-end flow tested via Bruno: user_1 sends a request to user_4, user_4 accepts, both /friends lists show each other
- [ ] 3.4 Cooldown manually verified against a real recently-declined fixture pair: re-send attempt returns 429 with FRIEND_REQUEST_COOLDOWN_ACTIVE
