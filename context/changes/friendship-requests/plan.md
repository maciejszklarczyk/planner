# Friendship Requests Implementation Plan

## Overview

We're building the Friendship domain (roadmap slice S-01, PRD refs US-02/FR-005/FR-006/FR-007): a user can send a friend request by searching for another user, the recipient can accept or decline it, the sender can cancel a still-pending request, both parties then see each other on a friends list, and a decline starts a 3-day (env-configurable) cooldown before the same sender can re-request the same recipient. This is the prerequisite for S-02 (event ownership + friend-gated invites).

This plan was promoted to a full-stack change (see `context/foundation/roadmap.md`'s Baseline note): the frontend already has a fully-built but entirely mocked Friends UI (`frontend/components/friends/FriendsView.tsx`) that this plan wires to the real backend, per `context/changes/friendship-requests/research.md`'s follow-up research on frontend patterns.

**Depends on**: `backend/context/archive/2026-07-22-api-exception-handling/` (roadmap Foundation `F-01`) must land first. That change introduces `ApiExceptionInterface` and the project-wide `kernel.exception` listener that every Friendship domain exception (Phase 2 below) is built on. This plan was originally scoped together with that infrastructure work, then split once the exception-handling migration turned out to be a genuine cross-cutting concern unrelated to Friendship — see `context/foundation/roadmap.md`'s Foundations section for the rationale.

## Current State Analysis

- **No Friendship/FriendRequest domain exists at all.** No entity, no migration, no controller, no tests.
- The Group↔User relation (`UserHasGroup`) and the invitation-token flow (`UserInvitationToken`) are the two closest analogues for the new Friendship entity/repository/service/controller, per `context/changes/friendship-requests/research.md`.
- `src/Repository/UserHasGroupRepository.php:24-41` (`findByUserAndGroup`/`isUserInGroup`) is the direct template for a symmetric pair-lookup.
- `src/Security/GroupVoter.php` is the only voter in the codebase today, and the template for the new `FriendshipVoter`.
- `symfony/rate-limiter` is a declared but entirely unused dependency in `src/` — no custom `RateLimiterFactory` exists anywhere to draw from.
- **`GET /admin/users` (`Admin\UserController::list()`) is `ROLE_ADMIN`-only and returns the full `UserListItemDto`** (including `roles`/`status`) — not suitable to expose to `ROLE_USER` as-is for a "search for a friend" flow (see Phase 4).
- **`src/Controller/UserController.php` already exists as the codebase's home for self-service (non-admin) user actions** (`PUT /user`, `DELETE /user/{userId}`, no class-level `IsGranted` — relies on the global `access_control` catch-all `{path: ^/, roles: ROLE_USER}`) — the natural place for a new user-search action for any authenticated user.
- **The frontend already has a fully-built, entirely mocked Friends UI**: `frontend/components/friends/FriendsView.tsx` (tabs, cards, search box, Accept/Decline/Cancel/Add buttons) and a wired sidebar nav entry (`frontend/components/sidebar/NavPages.tsx`) — all driven by hardcoded `MOCK_*` arrays with no `onClick` handlers. See `context/changes/friendship-requests/research.md`'s Follow-up Research section for the full frontend pattern catalogue (React Query hook templates, DTO/type conventions, error-handling gaps).

## Desired End State

A user can, as `ROLE_USER`:
- `GET /users?search=...` to find another user by email to send a request to (Phase 4; trimmed result shape — no `roles`/`status`).
- `POST /friend-requests` with `{email}` to send a request to another user (rejected if self, duplicate, already-friends, or cooldown-active; auto-accepted if the target already has a pending request to them).
- `GET /friend-requests` to see their own incoming and outgoing pending requests.
- `POST /friend-requests/{id}/accept` / `POST /friend-requests/{id}/decline` — only the recipient may act, enforced by a voter.
- `POST /friend-requests/{id}/cancel` — only the original requester may act, only while still `pending`, enforced by a voter; does **not** start the decline cooldown (see Phase 1/2 `CANCELLED` status).
- `GET /friends` to see their accepted friends.

...and in the browser:
- Visit `/friends` (already routed, already in the sidebar nav) and see their real friends/pending-requests lists instead of mock data, with a live pending-count badge in the nav.
- Search for a user and send them a request via a dialog (autocomplete over `GET /users`).
- Accept, decline, or cancel a request from the UI, each backed by a real mutation with a friendly, code-specific error message on failure (not a generic "something went wrong").
- See the "Suggestions" tab removed (no backend support planned) and the "Usuń ze znajomych" (unfriend) / "Wyślij wiadomość" (messaging) actions rendered as disabled with a "wkrótce dostępne" tooltip (both explicitly out of scope — see `## Parked` in `context/foundation/roadmap.md` and `## What We're NOT Doing` below).

Every Friendship error response uses the project-wide envelope established by `api-exception-handling`: `{"error": "CODE", "message": "...", "timestamp": "...", "path": "..."}`.

**Verification**: new `FriendshipControllerTest` and `UserControllerTest` functional tests cover send/accept/decline/cancel/list/cooldown/crossed-request/self-request/duplicate/search end-to-end on the backend; new frontend hook tests cover every Friendship hook's success/error paths; manual verification confirms the wired `/friends` page end-to-end in the browser.

### Key Discoveries:

- `src/Repository/UserHasGroupRepository.php:24-41` (`findByUserAndGroup`/`isUserInGroup`) is the direct template for a symmetric pair-lookup, generalized with an OR-clause for `FriendRequestRepository::findActiveBetween()`.
- `src/Service/InvitationMailer.php:13-20` shows the established `#[Autowire(env: ...)]` pattern for injecting env config into a service — used here for the cooldown duration.
- `config/services.yaml` autowires/autoconfigures everything under `src/` (`App\: resource: '../src/'`), so no manual service wiring is needed for the new repository/service/controller/voter.
- `src/Security/GroupVoter.php` gives ROLE_ADMIN a bypass because Group is an admin-manageable resource. **Friendship is not** — nothing in the PRD gives `ROLE_ADMIN` any special capability over another user's friend requests, so `FriendshipVoter` deliberately does **not** replicate that bypass.
- `symfony/rate-limiter` is a declared but entirely unused dependency in `src/` — the cooldown is implemented as a manual timestamp comparison (mirroring how `UserInvitationToken` expiry is checked), not via `RateLimiterFactory`.
- **Frontend**: `frontend/lib/api.ts`'s `ApiError.body` (the unified `{error,message,timestamp,path}` envelope) is populated on every request today but consumed nowhere in the existing codebase — every existing mutation hook shows a hardcoded generic toast. This plan's Phase 5 is the first to actually branch on `error.body.error`, establishing a reusable `getApiErrorMessage()` helper.
- **Frontend**: the existing `{data: T[]}` response-envelope convention (`types/groups.ts`, `types/api.ts`) does not match `GET /friend-requests`'s planned `{incoming, outgoing}` shape — `types/friends.ts` (Phase 5) deliberately breaks from that convention for this one endpoint rather than forcing the backend to wrap it.

## What We're NOT Doing

- **Unfriending / removing an accepted friendship** — not requested by any FR in the PRD. Per user decision, this is explicitly flagged as a follow-up topic for this MVP's backlog (see `context/foundation/roadmap.md` → `## Parked`), not built in this slice. The existing mock UI's "Usuń ze znajomych" button is rendered disabled with a tooltip in Phase 6, not wired to anything.
- **In-app messaging** — the mock UI's "Wyślij wiadomość" dropdown item has no corresponding feature anywhere in this codebase and isn't in any PRD/roadmap item. Rendered disabled with a tooltip in Phase 6, same as unfriend.
- **Friend suggestions** (mutual-friends/mutual-events recommendations) — the mock UI's "Sugestie" tab has no backend data source and no FR requests it. Removed from the UI entirely in Phase 6, not just hidden.
- **Rate-limiting how many friend requests a user can send** — PRD Open Question #2, resolved as "no limit in MVP."
- **Group self-service** — PRD Non-Goal, unrelated to this slice.
- **Event ownership/invites (S-02)** — the next roadmap slice, blocked on this one; not started here.
- **Building or modifying the project-wide exception-handling infrastructure** — that's `backend/context/archive/2026-07-22-api-exception-handling/`, a prerequisite this plan depends on but does not itself implement.
- **Changing `Admin\UserController::list()` (`/admin/users`)** — Phase 4 adds a new, separate `/users` search endpoint in the existing non-admin `UserController`; the admin panel's endpoint, access control, and DTO are untouched.
- **Full-text or fuzzy user search** — Phase 4 reuses `UserRepository`'s existing `email LIKE %search%` matching; no new search backend (e.g. Postgres full-text, name matching) is introduced.

## Implementation Approach

Backend first: data model (entity, migration, repository, fixtures), then the service layer's business rules (self-request, duplicate, crossed-request auto-accept, cooldown, cancel), then the HTTP layer (controller, voter, DTOs), then the new user-search endpoint. Every domain exception introduced here implements `ApiExceptionInterface` from the prerequisite `api-exception-handling` change — no new error-handling pattern is invented in the backend phases. Frontend last: a data layer (types, hooks, error-message mapping) followed by wiring the already-built mock UI to it — the UI shell itself needs no new design work, only real data.

## Critical Implementation Details

**Timing & lifecycle**: `respondedAt` on `FriendRequest` is set on accept, decline, *and* cancel — it's not decline-specific. The cooldown check (Phase 2) reads it only from the most recent row where `status = declined` (not `cancelled`) and `requester = <original sender>`, so accepted and cancelled rows never affect cooldown math — cancelling your own request must not penalize you with the decline cooldown.

**State sequencing**: The "crossed request" rule only applies when a *pending* row exists in the reverse direction. A *declined* or *cancelled* row in either direction is just history — it neither blocks a fresh request nor triggers auto-accept. Get the order of checks right in the service: (1) self-request, (2) existing active (pending/accepted) row in either direction — pending-reverse auto-accepts, pending-same-direction or accepted rejects as duplicate — (3) only if no active row exists, check cooldown against the latest declined-same-direction row, (4) create.

**Frontend error-message mapping (Phase 5)**: this plan's Friendship hooks are the first in the codebase to read `ApiError.body.error` instead of showing a static toast. The mapping lives in one shared `lib/apiErrors.ts` helper (not duplicated per-hook) so the seven Friendship error codes (`CANNOT_FRIEND_SELF`, `USER_NOT_FOUND`, `ALREADY_FRIENDS`, `DUPLICATE_FRIEND_REQUEST`, `FRIEND_REQUEST_COOLDOWN_ACTIVE`, `FRIEND_REQUEST_NOT_FOUND`, `FRIEND_REQUEST_NOT_PENDING`) each get a specific Polish message, with the existing hardcoded-string behavior as the fallback for unmapped codes.

## Phase 1: Friendship Data Model

### Overview

The entity, enum, migration, repository, and fixtures for the Friendship domain — no business logic yet, just the persistence layer.

### Changes Required:

#### 1. Status enum

**File**: `src/Entity/Enum/FriendshipStatusEnum.php`

**Intent**: Mirror `UserGroupRoleEnum`'s minimal string-backed shape.

**Contract**: `enum FriendshipStatusEnum: string { case PENDING = 'pending'; case ACCEPTED = 'accepted'; case DECLINED = 'declined'; case CANCELLED = 'cancelled'; }`. `CANCELLED` represents the requester withdrawing their own still-pending request (Phase 2/3) — kept distinct from `DECLINED` so cancelling never triggers the decline cooldown (see Critical Implementation Details).

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

**Prerequisite**: `ApiExceptionInterface` (from `backend/context/archive/2026-07-22-api-exception-handling/`) must already exist in `src/Exception/` before this phase's exception classes can implement it.

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

**Contract**: Five public methods:
- `sendRequest(User $requester, string $addresseeEmail): FriendRequest` — resolves the addressee by email (`UserNotFoundByEmailException` if none), rejects self-request (`CannotFriendSelfException`), checks `findActiveBetween()`: an accepted row → `AlreadyFriendsException`; a pending row where the *existing* requester is the current addressee (i.e. reversed) → flips that row to `accepted`, sets `respondedAt`, returns it (no new row — the crossed-request auto-accept decided during planning); a pending row in the same direction → `DuplicateFriendRequestException`. Only if no active row exists, checks `findLatestDeclinedBySender()` against `$clock->now()` minus `$cooldownDays` → `FriendRequestCooldownActiveException` if still within the window. Otherwise creates and persists a new `pending` row.
- `acceptRequest(FriendRequest $request): FriendRequest` / `declineRequest(FriendRequest $request): FriendRequest` — both require `$request->getStatus() === PENDING` (else `FriendRequestNotPendingException`), set status + `respondedAt = $clock->now()`, flush. **Authorization (only the addressee may call these) is enforced by the voter/controller layer, not here** — mirrors `GroupMembershipService` not self-checking permissions.
- `cancelRequest(FriendRequest $request): FriendRequest` — requires `$request->getStatus() === PENDING` (else `FriendRequestNotPendingException`), sets status to `CANCELLED` + `respondedAt = $clock->now()`, flush. **Deliberately does not touch the cooldown check** — `findLatestDeclinedBySender()` only reads `status = declined`, so a cancelled row is invisible to it. Authorization (only the original requester may call this) is enforced by the voter, not here.
- `listFriends(User $user): array` / `listPending(User $user): array{incoming: array, outgoing: array}` — thin wrappers over the repository methods.

@throws PHPDoc tags on each method listing every exception, per the `GroupMembershipService` convention.

### Success Criteria:

#### Automated Verification:

- `composer show symfony/clock` confirms the package is resolvable
- Unit/functional tests for the service (written in this phase, covering: self-request, duplicate same-direction, crossed-request auto-accept, already-friends, cooldown-active, cooldown-expired, cancel-does-not-trigger-cooldown, cancel-on-non-pending-rejected, successful send/accept/decline/cancel) pass: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit --filter=FriendshipService`

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

**Intent**: Only the request's addressee may accept or decline it; only the request's original requester may cancel it. Deliberately no `ROLE_ADMIN` bypass (see Key Discoveries) — Friendship is a personal relationship, not an admin-manageable resource like Group.

**Contract**: `supports()` restricted to attributes `accept`/`decline`/`cancel` and subject `instanceof FriendRequest`; `voteOnAttribute()` returns `$user === $subject->getAddressee()` for `accept`/`decline`, `$user === $subject->getRequester()` for `cancel`. Mirrors `GroupVoter`'s structure minus the admin short-circuit.

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
- `POST /friend-requests/{id}/accept` / `POST /friend-requests/{id}/decline` / `POST /friend-requests/{id}/cancel` — `#[MapEntity(id: 'id')] FriendRequest $request`, `#[IsGranted('accept', 'request')]` / `#[IsGranted('decline', 'request')]` / `#[IsGranted('cancel', 'request')]` → service call → `FriendRequestDto::fromEntity()`.
- `GET /friends` — `FriendshipService::listFriends()` → `UserListItemDto::fromEntities()`.

No new `access_control` entry needed — falls under the existing catch-all `{ path: ^/, roles: ROLE_USER }`.

#### 5. Functional tests

**File**: `tests/Functional/Controller/FriendshipControllerTest.php`

**Intent**: End-to-end coverage of every rule decided during planning, using the `GroupControllerTest`-style session-login + dynamic fixture-ID lookup pattern (the better template per research, given Friendship shares Group's "only participant can act" 403 shape).

**Contract**: Cooldown-expiry test overrides the container's `ClockInterface` with `Symfony\Component\Clock\MockClock` before making the request:
```php
self::getContainer()->set(ClockInterface::class, new MockClock('+4 days'));
```
Covers: send success, self-request rejection (422), duplicate same-direction (409), crossed-request auto-accept (200, no new row created), already-friends rejection (409), cooldown-active rejection (429, using the fixture's recently-declined row), cooldown-expired success (using `MockClock`), accept as non-addressee (403 via voter), accept/decline on a non-pending request (409), cancel by the original requester (200, status becomes `cancelled`), cancel by a non-requester (403 via voter), cancel on a non-pending request (409), **a cancelled request does not trigger the cooldown on a subsequent resend from the same sender** (regression test for the Critical Implementation Details note), friend list and pending list contents.

### Success Criteria:

#### Automated Verification:

- Full test suite passes: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit`
- `doctrine:schema:validate` still reports no errors after the full migration

#### Manual Verification:

- End-to-end flow tested via Bruno: user_1 sends a request to user_4 (no prior relation per fixtures), user_4 accepts, both `/friends` lists show each other
- Cooldown manually verified against a real (non-mocked) recently-declined fixture pair: re-send attempt returns 429 with `FRIEND_REQUEST_COOLDOWN_ACTIVE`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: User Search Endpoint

### Overview

A new, non-admin endpoint so any `ROLE_USER` can search for another user to send a friend request to — separate from the admin-only `/admin/users` endpoint, with its own trimmed response shape.

### Changes Required:

#### 1. Search result DTO

**File**: `src/Dto/Response/UserSearchResultDto.php`

**Intent**: A deliberately trimmed response shape for a general-audience search — no `roles`/`status`, unlike `UserListItemDto` which the admin panel needs.

**Contract**: Same `fromEntity`/`fromEntities` static-factory convention as `UserListItemDto`. Fields: `id` (int), `name` (string), `email` (string), `avatar` (nullable string).

#### 2. Repository: exclude-self support

**File**: `src/Repository/UserRepository.php`

**Intent**: `findWithPagination()`/`countWithFilters()` already support `excludeGroupId`; add an analogous `excludeUserId` so the search endpoint can omit the caller from their own results (searching for and friend-requesting yourself is pointless, even though `sendRequest()` also rejects it server-side).

**Contract**: Both methods gain an optional `?int $excludeUserId = null` parameter, applied as an additional `andWhere('u.id != :excludeUserId')` clause when non-null. Existing call sites (`Admin\UserController::list()`) are unaffected — the new parameter defaults to `null`.

#### 3. Controller action

**File**: `src/Controller/UserController.php`

**Intent**: Add a `search()` action to the existing non-admin `UserController` (not `Admin\UserController`) — this controller is already the codebase's home for actions any authenticated user may call.

**Contract**: `#[Route('/users', name: 'user_search', methods: ['GET'])]`. No `#[IsGranted]` attribute needed — falls under the existing `access_control` catch-all (`{path: ^/, roles: ROLE_USER}`), same as this controller's existing `editUser`/`deleteUser` actions. Query params: `search` (string, required — return an empty `data` array if blank rather than the full user list, to avoid accidentally exposing every user), `limit` (int, default 20, max 50). Calls `UserRepository::findWithPagination(search: ..., limit: ..., excludeUserId: $currentUser->getId())` via `#[CurrentUser] User $currentUser`. Response: `{"data": UserSearchResultDto[]}` (matches the existing `{data: T[]}` list-response convention — unlike `GET /friend-requests`, this endpoint has no reason to deviate from it).

### Success Criteria:

#### Automated Verification:

- New `tests/Functional/Controller/UserControllerTest.php` passes: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit --filter=UserControllerTest`
- Covers: search by partial email match returns matching users, caller's own account is excluded from results, unauthenticated request returns 401, blank/missing `search` param returns an empty list (not the full user table)
- `Admin\UserControllerTest` (or equivalent existing admin coverage) still passes unchanged — confirms `excludeGroupId`-based admin search behavior is untouched by the new `excludeUserId` parameter

#### Manual Verification:

- Hitting `GET /users?search=<partial email>` as a non-admin `X-Dev-User` returns matching users with only `id`/`name`/`email`/`avatar` fields (no `roles`/`status`)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 5: Friendship Data Layer (Frontend)

### Overview

Types, React Query hooks, and the error-code-to-message helper the UI (Phase 6) needs — no UI changes yet, this phase is pure data-layer plumbing following the patterns catalogued in `context/changes/friendship-requests/research.md`'s Follow-up Research section.

### Changes Required:

#### 1. Types

**File**: `frontend/types/friends.ts`

**Intent**: Hand-written response types matching the backend DTOs from Phases 3-4, following this codebase's existing per-domain-file convention (`types/groups.ts`, `types/events.ts`).

**Contract**: `FriendRequestStatus = "pending" | "accepted" | "declined" | "cancelled"`; `FriendRequestOtherUser { id: number; name: string; email: string; avatar: string | null }`; `FriendRequestDto { id: number; otherUser: FriendRequestOtherUser; status: FriendRequestStatus; createdAt: string }`; `FriendRequestsResponse { incoming: FriendRequestDto[]; outgoing: FriendRequestDto[] }` (deliberately not `{data: T[]}` — matches the backend's actual shape, see Key Discoveries); `FriendsResponse { data: FriendRequestOtherUser[] }` (follows the `{data: T[]}` convention, matches `GET /friends`); `UserSearchResult { id: number; name: string; email: string; avatar: string | null }`; `UserSearchResponse { data: UserSearchResult[] }`.

#### 2. Error-message helper

**File**: `frontend/lib/apiErrors.ts`

**Intent**: The first instance in this codebase of translating the backend's `error` code into a specific user-facing message instead of a generic toast (see Critical Implementation Details) — a small, reusable helper rather than a per-hook switch statement.

**Contract**: `getApiErrorMessage(error: unknown, fallback: string): string` — if `error instanceof ApiError` and `(error.body as {error?: string})?.error` is a key in a local `Record<string, string>` map, return the mapped Polish message; otherwise return `fallback`. Map covers the seven Friendship error codes listed in Critical Implementation Details.

#### 3. Query hooks

**Files**: `frontend/hooks/useFriends.ts`, `frontend/hooks/useFriendRequests.ts`

**Intent**: List queries, following `hooks/useGroupMembers.ts`'s shape exactly.

**Contract**: `useFriends()` → `useQuery({queryKey: ["friends"], queryFn: () => api.get<FriendsResponse>("/friends")})`. `useFriendRequests()` → `useQuery({queryKey: ["friends", "requests"], queryFn: () => api.get<FriendRequestsResponse>("/friend-requests")})`.

#### 4. Mutation hooks

**Files**: `frontend/hooks/useSendFriendRequest.ts`, `frontend/hooks/useAcceptFriendRequest.ts`, `frontend/hooks/useDeclineFriendRequest.ts`, `frontend/hooks/useCancelFriendRequest.ts`

**Intent**: One mutation hook per action, following `hooks/useAddGroupMember.ts`'s shape (toast + `invalidateQueries` in `onSuccess`), but using `getApiErrorMessage()` in `onError` instead of a single hardcoded string.

**Contract**: `useSendFriendRequest()` → `mutationFn: (email: string) => api.post("/friend-requests", {email})`; `onSuccess` toasts + invalidates `["friends", "requests"]`; `onError` toasts `getApiErrorMessage(error, "Nie udało się wysłać zaproszenia.")`. `useAcceptFriendRequest()` / `useDeclineFriendRequest()` / `useCancelFriendRequest()` → `mutationFn: (id: number) => api.post(`/friend-requests/${id}/accept|decline|cancel`)`; each `onSuccess` toasts + invalidates both `["friends"]` and `["friends", "requests"]` (accept changes both lists; decline/cancel only the requests list, but invalidating both is simpler and matches `useAddGroupMember`'s "invalidate everything plausibly affected" pattern); each `onError` uses `getApiErrorMessage()` with an action-appropriate fallback string.

#### 5. Search hook

**File**: `frontend/hooks/useSearchFriendCandidates.ts`

**Intent**: Debounced-via-`enabled` search against the new `GET /users` endpoint (Phase 4), following `hooks/useSearchUsers.ts`'s shape but pointed at the non-admin endpoint and without the `excludeGroupId` parameter (which is Group-specific and doesn't apply here).

**Contract**: `useSearchFriendCandidates(search: string, enabled = true)` → `useQuery({queryKey: ["users", "search", search], queryFn: () => api.get<UserSearchResponse>(`/users?search=${encodeURIComponent(search)}`), enabled: enabled && search.length >= 2})`.

#### 6. Hook tests

**Files**: `frontend/hooks/useSendFriendRequest.test.ts`, `frontend/hooks/useAcceptFriendRequest.test.ts`, `frontend/hooks/useDeclineFriendRequest.test.ts`, `frontend/hooks/useCancelFriendRequest.test.ts`

**Intent**: One test file per mutation hook, following `hooks/useDeleteGroup.test.ts`'s exact structure (mock `@/lib/api` and `sonner`, fresh `QueryClientProvider` wrapper, assert the right endpoint is called and the right toast fires).

**Contract**: Each file covers a success case (asserts the correct `api.post` call + `toast.success`) and at least one mapped-error case (mocks `api.post` rejecting with an `ApiError`-shaped error carrying one of the seven codes, asserts `toast.error` was called with the mapped message, not the fallback) — this is what actually exercises `getApiErrorMessage()`, not just the happy path.

### Success Criteria:

#### Automated Verification:

- New hook tests pass: `cd frontend && npm run test`
- Typecheck passes: `cd frontend && npx tsc --noEmit` (or the project's existing typecheck script)

#### Manual Verification:

- None beyond automated — this phase has no rendered UI yet; manual verification happens in Phase 6

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 6: Friendship UI Wiring

### Overview

Wire the existing, already-built mock UI (`frontend/components/friends/FriendsView.tsx`) to the Phase 5 hooks, remove the two mock elements with no backend support (Suggestions tab, in-app messaging), and render the two out-of-scope actions (unfriend, cancel — wait, cancel now has backend support, see below) appropriately.

### Changes Required:

#### 1. FriendsView data wiring

**File**: `frontend/components/friends/FriendsView.tsx`

**Intent**: Replace every `MOCK_*` array with the Phase 5 hooks; the component's structure, styling, and subcomponents stay as-is — this is a data-source swap, not a redesign.

**Contract**: `useFriends()` replaces `MOCK_FRIENDS`; `useFriendRequests()` replaces `MOCK_PENDING_SENT`/`MOCK_PENDING_RECEIVED` (its `incoming`/`outgoing` fields map directly to those two lists). Add `isLoading` skeleton states following `components/events/EventsView.tsx`'s or `components/users/UsersTable.tsx`'s pattern (`<Skeleton>` placeholders matching the card layout) while either query is loading. The local `Friend`/`Suggestion` interfaces (lines 42-57 today) are replaced by the Phase 5 `FriendRequestOtherUser`/`FriendRequestDto` types — `mutualEvents` (used in `FriendCard`/`StatsRow`) has no backend source and is removed from the UI, not faked with a placeholder.

#### 2. Remove Suggestions tab

**File**: `frontend/components/friends/FriendsView.tsx`

**Intent**: No backend support exists or is planned for friend suggestions (see `## What We're NOT Doing`) — remove rather than leave as dead UI.

**Contract**: Remove the `Suggestion` interface, `MOCK_SUGGESTIONS`, the `SuggestionCard` component, the `"suggestions"` tab from the `Tab` union type, its `TabsTrigger`, its content block, and its `EmptyState` config entry. `Tab` becomes `"friends" | "invitations"`.

#### 3. Wire Accept/Decline/Cancel actions

**File**: `frontend/components/friends/FriendsView.tsx`

**Intent**: The buttons already exist in `PendingReceivedCard` (Accept/Decline) and `PendingSentCard` (Cancel, labeled "Cofnij") with no `onClick` handlers — wire them to the Phase 5 mutation hooks.

**Contract**: `PendingReceivedCard`'s "Akceptuj"/"Odrzuć" buttons call `useAcceptFriendRequest()`/`useDeclineFriendRequest()` with the request's `id`; `PendingSentCard`'s "Cofnij" button calls `useCancelFriendRequest()`. Each mutation's `isPending` state disables its button while in flight (standard React Query pattern, not previously needed since the buttons were inert).

#### 4. Disable out-of-scope actions

**File**: `frontend/components/friends/FriendsView.tsx`

**Intent**: `FriendCard`'s dropdown menu has two items with no backend support in this plan: "Wyślij wiadomość" (no messaging feature exists anywhere) and "Usuń ze znajomych" (unfriend, explicitly parked per roadmap). Render both as disabled with an explanatory tooltip rather than removing them — signals the intended future direction without offering broken functionality.

**Contract**: Wrap both `DropdownMenuItem`s with `disabled` + a `Tooltip`/`title` reading "Wkrótce dostępne" (or equivalent — match this codebase's existing tooltip primitive if one exists in `components/ui/`, otherwise the native `title` attribute is an acceptable minimal fallback).

#### 5. Send-request dialog

**File**: `frontend/components/friends/SendFriendRequestDialog.tsx` (new)

**Intent**: Replace the currently-inert "Zaproś znajomego" header button with a working dialog — search-as-you-type over `useSearchFriendCandidates()` (Phase 5), select a result, send via `useSendFriendRequest()`. Not a form-with-validated-email-field like `InviteUserDialog.tsx` (that pattern fit the admin invite-by-raw-email flow; this pattern fits "pick an existing user from search results," so `hooks/useSearchUsers.ts`'s debounced-search UX is the closer template here, adapted from a table-filter context to a dialog-autocomplete context).

**Contract**: `Dialog` + `InputGroupInput` (matching `FriendsView`'s existing search-box styling) bound to a local `search` state, `useSearchFriendCandidates(search)` results rendered as a selectable list (reuse `Avatar`/`initials()` from `FriendsView.tsx`), clicking a result calls `useSendFriendRequest().mutate(result.email)` and closes the dialog on success.

#### 6. Wire the header button and nav badge

**Files**: `frontend/components/friends/FriendsView.tsx`, `frontend/components/sidebar/NavPages.tsx`

**Intent**: The "Zaproś znajomego" header button opens the new dialog instead of doing nothing; the sidebar's hardcoded `badge: 0` becomes a live pending-request count.

**Contract**: `FriendsView`'s header button becomes the `DialogTrigger` for `SendFriendRequestDialog`. `NavPages.tsx`'s `navPages` array can't call a hook at module scope — move the Friends `badge` value to be computed inside the `NavPages()` component body via `useFriendRequests()`, defaulting to `undefined` (hides the badge) while loading or on error, then `incoming.length` once loaded.

### Success Criteria:

#### Automated Verification:

- Frontend build succeeds: `cd frontend && npm run build`
- Typecheck passes: `cd frontend && npx tsc --noEmit`
- Lint passes: `cd frontend && npm run lint`
- Full frontend test suite passes: `cd frontend && npm run test`

#### Manual Verification:

- Visiting `/friends` with two fresh dev users (`X-Dev-User`) shows real (not mock) friends/pending data, with a working loading skeleton on first load
- Searching and sending a friend request via the new dialog succeeds end-to-end; sending to yourself, to an already-friend, or during an active cooldown shows the correct code-specific error message (not a generic one) — spot-check at least 3 of the 7 mapped error codes
- Accepting, declining, and cancelling a request all update the UI immediately (via query invalidation) without a manual page refresh
- The "Sugestie" tab is gone; "Wyślij wiadomość" and "Usuń ze znajomych" render visibly disabled with a tooltip
- The sidebar's Friends nav badge reflects the real incoming-request count and updates after accepting/declining

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- `FriendshipService` business rules in isolation (Phase 2), particularly the ordering of checks described in Critical Implementation Details, plus cancel's cooldown-exemption behavior
- Frontend: `getApiErrorMessage()` helper (Phase 5) — pure function, easy to unit test directly if desired, though its behavior is also exercised indirectly by the hook tests

### Integration Tests:

- Full backend functional suite per phase (Phase 3 adds `FriendshipControllerTest`, Phase 4 adds `UserControllerTest`)
- Frontend hook tests per Phase 5 (mocked `lib/api`, real `QueryClientProvider`) — no frontend component-level (`render()`) test in this plan, per explicit scope decision; `FriendsView.tsx` itself is covered only by manual verification (Phase 6)

### Manual Testing Steps:

1. Before starting: confirm `backend/context/archive/2026-07-22-api-exception-handling/` has landed (its own plan's success criteria are green) — this plan assumes `ApiExceptionInterface` already exists.
2. After Phase 3: run the full send → accept → friends-list flow via Bruno for two fresh users, then the send → decline → cooldown-blocked-resend → (with real time or a temporary short cooldown) resend-succeeds flow, plus send → cancel → immediate-resend-not-blocked-by-cooldown
3. After Phase 4: confirm `GET /users?search=` as a non-admin returns trimmed results excluding the caller
4. After Phase 6: full browser walkthrough per that phase's Manual Verification list

## Performance Considerations

None specific — data volumes are small per the PRD's `target_scale` (`users: small, qps: low`), and all new queries are indexed lookups on FK/pair columns. The new `/users` search endpoint reuses `UserRepository`'s existing indexed `email` lookup.

## Migration Notes

No backfill needed — Friendship is a wholly new table with no pre-existing data. The `friend_request` migration is purely additive to the schema; no existing table is altered. Phase 4 adds no migration — it's a new controller action + DTO over the existing `user` table.

## References

- Related research: `context/changes/friendship-requests/research.md` (includes a Follow-up Research section on frontend patterns)
- Similar implementation (backend): `src/Service/GroupMembershipService.php`, `src/Security/GroupVoter.php`, `src/Repository/UserHasGroupRepository.php:24-41`
- Similar implementation (frontend): `frontend/hooks/useAddGroupMember.ts`, `frontend/hooks/useGroupMembers.ts`, `frontend/hooks/useSearchUsers.ts`, `frontend/hooks/useDeleteGroup.test.ts`, `frontend/components/users/InviteUserDialog.tsx`
- Depends on: `backend/context/archive/2026-07-22-api-exception-handling/plan.md` (must land first)
- Depends on: `context/foundation/roadmap.md`'s Baseline promotion of this change to full-stack scope
- Follow-up backlog item (per user decision, out of scope for this plan but tracked for this MVP): unfriending / removing an accepted friendship, and in-app messaging — see `context/foundation/roadmap.md` → `## Parked`.

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Friendship Data Model

#### Automated

- [x] 1.1 Migration applies cleanly against the test DB
- [x] 1.2 Fixtures load without error
- [x] 1.3 doctrine:schema:validate reports no mapping errors

#### Manual

- [ ] 1.4 Inspecting the friend_request table after fixture load shows the expected rows and that the partial unique index exists

### Phase 2: Friendship Service Layer

#### Automated

- [ ] 2.1 composer show symfony/clock confirms the package is resolvable
- [ ] 2.2 Service tests (self-request, duplicate, crossed-request auto-accept, already-friends, cooldown-active, cooldown-expired, cancel-does-not-trigger-cooldown, cancel-on-non-pending-rejected, successful send/accept/decline/cancel) pass

### Phase 3: Friendship HTTP Layer & Tests

#### Automated

- [ ] 3.1 Full test suite passes
- [ ] 3.2 doctrine:schema:validate still reports no errors after the full migration

#### Manual

- [ ] 3.3 End-to-end flow tested via Bruno: user_1 sends a request to user_4, user_4 accepts, both /friends lists show each other
- [ ] 3.4 Cooldown manually verified against a real recently-declined fixture pair: re-send attempt returns 429 with FRIEND_REQUEST_COOLDOWN_ACTIVE

### Phase 4: User Search Endpoint

#### Automated

- [ ] 4.1 New UserControllerTest passes (search match, self-excluded, 401 unauthenticated, blank search returns empty list)
- [ ] 4.2 Existing admin user-list test coverage still passes unchanged

#### Manual

- [ ] 4.3 GET /users?search= as a non-admin X-Dev-User returns trimmed results (id/name/email/avatar only, no roles/status)

### Phase 5: Friendship Data Layer (Frontend)

#### Automated

- [ ] 5.1 New hook tests pass (npm run test)
- [ ] 5.2 Typecheck passes (npx tsc --noEmit)

### Phase 6: Friendship UI Wiring

#### Automated

- [ ] 6.1 Frontend build succeeds (npm run build)
- [ ] 6.2 Typecheck passes (npx tsc --noEmit)
- [ ] 6.3 Lint passes (npm run lint)
- [ ] 6.4 Full frontend test suite passes (npm run test)

#### Manual

- [ ] 6.5 /friends shows real friends/pending data for two fresh dev users, with a working loading skeleton
- [ ] 6.6 Sending a friend request via the new dialog succeeds end-to-end; at least 3 of the 7 mapped error codes show their specific message (not generic)
- [ ] 6.7 Accept/decline/cancel all update the UI immediately via query invalidation, no manual refresh
- [ ] 6.8 "Sugestie" tab is gone; "Wyślij wiadomość"/"Usuń ze znajomych" render disabled with a tooltip
- [ ] 6.9 Sidebar Friends nav badge reflects the real incoming-request count and updates after accept/decline
