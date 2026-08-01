---
date: 2026-07-22T20:52:05Z
researcher: Maciej Szklarczyk
git_commit: 7e3a4e187c67702bcd43b763a7517193304bbbc3
branch: main
repository: backend
topic: "Friendship domain (send/accept/decline friend requests, friend list) — implementation patterns to reuse"
tags: [research, codebase, friendship, user, group, invitation-token, voter, testing, frontend, nextjs, react-query, shadcn]
status: complete
last_updated: 2026-08-01
last_updated_by: Maciej Szklarczyk
last_updated_note: "Added follow-up research on frontend implementation patterns — friendship-requests promoted to a full-stack change"
---

# Research: Friendship domain — patterns to reuse for FR-005/FR-006/FR-007

**Date**: 2026-07-22T20:52:05Z
**Researcher**: Maciej Szklarczyk
**Git Commit**: 7e3a4e187c67702bcd43b763a7517193304bbbc3
**Branch**: main
**Repository**: backend

## Research Question

Roadmap slice S-01 (`friendship-requests`, PRD refs US-02/FR-005/FR-006/FR-007) requires a new User↔User Friendship domain: send a friend request by email, accept/decline it, view a friend list, and start a cooldown after decline. Nothing like this exists yet. What existing patterns in this codebase (entities, repositories, services, controllers, DTOs, voters, tests, fixtures, env-config wiring) should the implementation follow?

## Summary

The codebase has one directly analogous relation (`Group`↔`User` via `UserHasGroup`) and one directly analogous "secret token with expiry" flow (`UserInvitationToken`). Both give strong, concrete templates:

- **Entity shape**: int autoincrement PKs everywhere (no UUID), string-backed enums mapped via `#[ORM\Column(enumType: ...)]`, Doctrine attributes throughout.
- **Join/relation entity**: `UserHasGroup` is the template for a `FriendRequest`/`Friendship` entity — two `ManyToOne(User::class)` sides + a status enum. Its one weakness (no unique DB constraint on the pair) should **not** be repeated — add a real unique constraint for Friendship.
- **Expiry/cooldown**: `UserInvitationToken` hardcodes `+1 day` in the constructor — an anti-pattern to avoid. The repo's real convention for injecting a duration is `#[Autowire(env: 'int:...')]` on a service constructor (seen in `InvitationMailer`), not the entity. `symfony/rate-limiter` is a dependency but **unused** in `src/` — cooldown should be a manual timestamp check (mirroring `UserInvitationTokenRepository`-style "supersede on retry" logic), not a `RateLimiterFactory`.
- **Repository pair-lookup**: `UserHasGroupRepository::findByUserAndGroup`/`isUserInGroup` is the template for `FriendshipRepository::findBetweenUsers`/`areFriends`, generalized to an OR-based query since Friendship is symmetric but requests have direction (sender/recipient).
- **Service layer**: plain constructor-injected service, throws `NotFoundHttpException` for missing entities and custom `\RuntimeException`-based domain exceptions for business-rule violations, mutates + flushes, returns entities (controller does entity→DTO mapping).
- **Controller**: `#[Route]` + `#[IsGranted]` + `#[MapRequestPayload]` request DTOs + try/catch → hand-rolled `{'error': CODE, 'message': ...}` JSON. No global exception listener exists.
- **DTOs**: prefer the newer `final readonly class` style (`CreateEventDto`) over the older mutable `GroupMembership` DTOs. Response DTOs use static `fromEntity`/`fromEntities` factories with `\LogicException` guards.
- **Authorization**: `GroupVoter` is the only voter in the codebase and the template for a `FriendshipVoter` — checks `$user === sender || $user === recipient` for accept/decline actions. No new `access_control` entry needed (authenticated routes fall under the catch-all `^/ → ROLE_USER`).
- **Testing**: two coexisting patterns — `X-Dev-User` header per-request (`EventControllerTest`) or session login + dynamic fixture-ID lookup by email (`GroupControllerTest`/`GroupMembershipControllerTest`). The latter is the better template since Friendship shares Group's "only participant can act" shape. Fixtures are Alice YAML files under `fixtures/` (not `src/DataFixtures/`), loaded via `AppFixtures::load()`.

## Detailed Findings

### Entities & relations

- `src/Entity/User.php` — int autoincrement PK (`#[ORM\Id] #[ORM\GeneratedValue]`), table `` `user` `` (backtick-quoted), soft-delete via Gedmo `SoftDeleteableEntity` trait, `roles` array, `status` backed enum (`UserStatusEnum`). Existing collection pattern: `userHasGroups` as `OneToMany(mappedBy: 'user', orphanRemoval: true)`, initialized in constructor, with `addUserHasGroup`/`removeUserHasGroup` maintaining both sides. `isMemberOf(Group $group)` uses `Collection::exists()` closure — same pattern would give `User::hasFriendRequestFrom()`/`isFriendsWith()` if going the collection-helper route (optional; repository-level check is equally valid and matches `UserHasGroupRepository`'s style more closely).
- `src/Entity/UserHasGroup.php` — the direct template for a Friendship/FriendRequest entity: surrogate `int id`, two `#[ManyToOne(...)] #[JoinColumn(nullable: false)]` sides, `role` as `#[ORM\Column(enumType: UserGroupRoleEnum::class)]`. **No unique constraint on the (user, group) pair** — uniqueness enforced only in application code before insert. This is a known gap; a Friendship pair (`requester_id`, `addressee_id`) should get a **real DB unique constraint** (plus, if desired, a `CHECK (requester_id <> addressee_id)`), unlike `UserHasGroup`.
- No timestamp trait exists anywhere in the codebase. `UserActivityLog` is the only entity with `createdAt` (`\DateTimeImmutable`, set in constructor) — no `updatedAt` pattern exists. A Friendship entity needing `createdAt`/`respondedAt`/cooldown-relevant timestamps should follow `UserActivityLog`'s manual-field style, not a shared trait (none exists to reuse).
- `src/Entity/Enum/UserGroupRoleEnum.php` — minimal string-backed enum (`OWNER = 'owner'`, `MEMBER = 'member'`), mapped natively via Doctrine 3's `enumType`. `UserStatusEnum` is the same pattern with more cases. A `FriendshipStatusEnum` (`PENDING`, `ACCEPTED`, `DECLINED`) should follow this exact shape.

### Repositories

- `src/Repository/UserRepository.php::findWithPagination()`/`countWithFilters()` — manual QueryBuilder pagination (`setFirstResult`/`setMaxResults`), no Doctrine Paginator bundle. Relevant if the friend list endpoint needs pagination (PRD doesn't explicitly require it for FR-007, but the existing `GET /admin/users?page&limit&search` shows the expected shape if added later).
- `src/Repository/UserHasGroupRepository.php::findByUserAndGroup()` (24-33) / `isUserInGroup()` (38-41) — **the** template for a symmetric pair-lookup:
  ```php
  public function findByUserAndGroup(int $userId, int $groupId): ?UserHasGroup
  {
      return $this->createQueryBuilder('uhg')
          ->andWhere('uhg.user = :userId')
          ->andWhere('uhg.group = :groupId')
          ->setParameter('userId', $userId)
          ->setParameter('groupId', $groupId)
          ->getQuery()
          ->getOneOrNullResult();
  }
  ```
  No existing entity has a self-referencing "pair between two users" relation, so there's no verbatim symmetric-pair lookup to copy — but the shape generalizes directly: `FriendshipRepository::findBetweenUsers(int $userAId, int $userBId): ?Friendship` using `WHERE (requester = :a AND addressee = :b) OR (requester = :b AND addressee = :a)`, plus `areFriends()`/`hasPendingRequestBetween()` boolean wrappers mirroring `isUserInGroup()`.
- `findByGroup()` (46-59) eager-loads via `join`/`addSelect` to avoid N+1 — relevant for a friend-list endpoint that needs the friend's `User` data, not just the join-row.

### Invitation token flow (expiry + secret-token conventions)

- `src/Entity/UserInvitationToken.php` — stores **sha256 hash** of the token, not the raw value (`token: hash('sha256', $rawToken)`); raw token (`bin2hex(random_bytes(32))`) only ever appears in the outbound email. Expiry: `expiresAt = new \DateTimeImmutable('+1 day')`, hardcoded in the constructor — **avoid this hardcoding for Friendship's cooldown**; it's exactly the anti-pattern the PRD's roadmap explicitly wants to avoid ("wartość konfigurowalna, nie hardcoded").
- `src/Controller/InvitationController.php` — three-step guard sequence on verify/complete: not-found → 400, already-used → 400, expired → 400, in that order. Re-derives `hash('sha256', $token)` before lookup each time.
- Token creation lives inline in `src/Controller/Admin/UserController.php::createToken()` (no dedicated `InvitationService`) — the controller-does-orchestration style is the existing convention when no service layer exists yet for a flow. `resendUserInvite()` shows the "invalidate previous tokens on retry" pattern (`findActiveByEmail()` + mark each `usedAt`) — directly useful precedent for "supersede a still-pending friend request if the same sender re-sends," and for cooldown bookkeeping after decline.
- Migration history for `user_invitation_token` (`migrations/Version20260218172813.php`) shipped **without** a unique index on `token`, retrofitted later in `Version20260310000001.php`. Cautionary example: Friendship's migration must define its unique pair constraint from day one, not retrofit it.

### Env-configurable values (for the cooldown duration)

- Two coexisting wiring patterns:
  - **Direct `#[Autowire(env: ...)]` on a constructor property** — `src/Service/InvitationMailer.php`: `#[Autowire(env: 'FRONTEND_URL')] private readonly string $frontendUrl`. This is the closest precedent and the recommended pattern for `FRIEND_REQUEST_COOLDOWN_...` — e.g. `#[Autowire(env: 'int:FRIEND_REQUEST_COOLDOWN_DAYS')]` (the `int:` env-var processor prefix is needed since raw env values are strings).
  - **`parameters:` in `config/services.yaml` bound via `%env(...)%`**, then wired as named constructor args — used for S3 config. Either pattern is acceptable; `#[Autowire(env:)]` is more local to the consuming service and matches `InvitationMailer` most directly.
- `config/packages/rate_limiter.yaml` is **only a commented-out example** — no active custom limiter exists. `symfony/rate-limiter` is a composer dependency but `RateLimiterFactory` is **not used anywhere in `src/`** (confirmed via grep). The only active rate limiting is Symfony's declarative `login_throttling` in `security.yaml` (3 attempts / 15 min), which is framework-native, not a custom limiter service. **Given no existing custom-limiter example to extend, implement the friend-request cooldown as a manual timestamp check** (query most-recent-declined-request timestamp + compare against `now() - cooldown`), consistent with how `UserInvitationToken` expiry is checked manually — not via `symfony/rate-limiter`.

### Access control / security.yaml

- `access_control` in `config/packages/security.yaml` is a top-to-bottom first-match list; only `PUBLIC_ACCESS` routes (`/auth/login`, `/invitation/verify`, `/invitation/complete`, health/version/doc) are listed explicitly. Everything else falls under the catch-all `{ path: ^/, roles: ROLE_USER }`. **A new Friendship controller under `ROLE_USER` needs no new `access_control` entry.**
- `DevHeaderAuthenticator` and `json_login` are both registered under `when@test:` / `when@dev:`, so functional tests can use either `X-Dev-User` header or a real login — both are valid.

### Authorization pattern (voter)

- `src/Security/GroupVoter.php` is the **only** voter in the codebase and the direct template for a `FriendshipVoter`:
  - `supports()` restricts to specific attributes (`VIEW`/`DELETE`) and a specific subject type (`instanceof Group`).
  - `voteOnAttribute()` short-circuits `true` for `ROLE_ADMIN` via injected `AccessDecisionManagerInterface`, otherwise delegates to `canView()`/`canDelete()` comparing `$user === $group->getGroupOwnerUser()` or membership.
  - Wired via `#[IsGranted('view', 'group')]` on controller actions with `#[MapEntity(id: 'group')]` resolving the entity from the route param. Auto-registered as `security.voter` — no explicit service wiring needed.
  - A `FriendshipVoter` for accept/decline should follow this exact shape, checking `$user === $friendRequest->getRecipient()` (accept/decline) and possibly `$user === $friendRequest->getRequester() || $user === $friendRequest->getRecipient()` for view/cancel.
- `EventController` has **no** voter and **no** ownership field at all today (confirmed by reading the full controller) — consistent with the PRD's statement that Event ownership doesn't exist yet; not a pattern to draw from for Friendship.

### DTOs

- Two coexisting DTO styles:
  - Older, mutable style (`src/Dto/GroupMembership/AddUserToGroupDto.php`, `UpdateUserRoleDto.php`): plain class, `#[Assert\...]` on properties, enum fields typed as `?EnumType` with constructor doing `EnumType::tryFrom(strtolower($raw))` so `#[Assert\NotNull]` catches invalid enum strings.
  - Newer, preferred style (`src/Dto/Event/CreateEventDto.php`): `final readonly class`, promoted readonly properties directly typed, `#[Assert\NotBlank]`/`#[Assert\Length(...)]`. **Use this style for new Friendship request DTOs** (e.g. `SendFriendRequestDto` with a validated `email` field).
- Response DTOs (`src/Dto/Response/*`) use a consistent static-factory convention: `fromEntity(Entity $e): self` (null-guards required fields, throws `\LogicException` on violated invariants) and `fromEntities(array $entities): array` for batch mapping. A `FriendshipDto`/`FriendListItemDto` should follow this exactly, flattening the related `User` into something like the existing `UserListItemDto`.

### Service & exception conventions

- `src/Service/GroupMembershipService.php` — plain constructor-injected service (`EntityManagerInterface` + repositories, no interfaces/ports). Each method: (1) resolve entities via repository `find()`, throw `NotFoundHttpException` (Symfony's built-in, not custom) if missing; (2) validate business invariants, throw custom `\RuntimeException`-based exceptions; (3) mutate + `persist()`/`remove()` + `flush()`; return the entity (DTO mapping happens in the controller). PHPDoc `@throws` tags document every exception per method — worth keeping.
- Exceptions (`src/Exception/CannotRemoveLastOwnerException.php` etc.) all extend `\RuntimeException` directly — no shared `DomainException` base class exists. Message built from IDs in the constructor. Caught per-exception-type in the controller, mapped to a hand-rolled `{'error': 'CODE', 'message': ...}` JSON body — **no global exception listener/normalizer exists**, so a new `FriendshipController` must replicate this same manual try/catch-per-exception-type pattern (e.g. `FriendshipAlreadyExistsException`, `CannotFriendSelfException`, `FriendRequestCooldownActiveException`, `FriendRequestNotFoundException`).

### Controller conventions

- `src/Controller/Admin/GroupMembershipController.php` — class-level `#[Route(...)]` + `#[IsGranted('ROLE_ADMIN')]` + `#[OA\Tag(...)]` for Swagger; constructor property promotion with `readonly` services; per-action `#[Route]` with explicit `methods:`; request DTOs via `#[MapRequestPayload] Dto $dto` (auto-validates, 422 on failure before method body runs); current user via `#[CurrentUser] User $currentUser`; response via entity → Response DTO `::fromEntity()` → `$this->json($dto, $status)`; list endpoints wrap as `['data' => [...]]`.
- A new `FriendshipController` (non-admin, under `ROLE_USER`) should mirror this shape minus the `ROLE_ADMIN` gate, using `#[IsGranted]` (or the new `FriendshipVoter`) per-action instead where participant-only actions (accept/decline) are involved.

### Testing & fixtures

- `tests/DatabaseTestCase.php` — drops/recreates schema + reloads fixtures once per test **class** (`setUpBeforeClass`, not per-test), against SQLite (`.env.test`: `DATABASE_URL="sqlite:///%kernel.project_dir%/var/test.db"`). No DAMA/doctrine-test-bundle transactional rollback — tests within a class must be written knowing state persists across test methods in that class (existing tests order destructive cases last, e.g. a comment "run last — soft-deletes a group not used by other tests").
- Two valid auth patterns in tests:
  - `EventControllerTest.php` — per-request `X-Dev-User` header: `$client->request('GET', '/events/1', [], [], ['HTTP_X_DEV_USER' => 'user1@example.com'])`. No login step, assumes fixed fixture IDs.
  - `GroupControllerTest.php` / `Admin/GroupMembershipControllerTest.php` — session login via `POST /auth/login` (json_login), then dynamic fixture-ID resolution by email/name (`getGroupIdByName()`, `getUserIdByEmail()`) rather than hardcoded IDs. This is the **better template for Friendship tests** given the same "only participant can act" 403-testing shape:
    ```php
    public function testDeleteGroupAsNonOwnerReturnsForbidden(): void
    {
        $groupId = $this->getGroupIdByName('Group 1');
        $client = $this->loginAs(self::USER1_EMAIL, self::USER1_PASSWORD);
        $client->request('DELETE', "/groups/{$groupId}");
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
    ```
- Fixtures are **Alice YAML** files at repo root `fixtures/*.yaml` (not `src/DataFixtures/*.php`), loaded via `AppFixtures::load([...])` in a fixed array. New fixtures need a new `fixtures/friendships.yaml` (or `friend_requests.yaml`) added to that load array, referencing `@user_1`..`@user_5`/`@user_admin` with representative pending/accepted/declined rows — following the deterministic map style already documented in `fixtures/user_has_groups.yaml` and mirrored in test docblocks.
- `phpunit.dist.xml` — bootstrap `tests/bootstrap.php`, forces `APP_ENV=test`, `failOnDeprecation/Notice/Warning="true"`. No special config needed for a new controller/test — it inherits the existing env.

## Code References

- `src/Entity/User.php:26-29,49-56,58-59,163-190` — PK shape, `userHasGroups` collection pattern, `isMemberOf()`, `addUserHasGroup`/`removeUserHasGroup`
- `src/Entity/UserHasGroup.php:21-33` — join-entity template (two `ManyToOne`, enum column, no unique constraint — gap to fix)
- `src/Entity/Enum/UserGroupRoleEnum.php` — string-backed enum template
- `src/Entity/UserActivityLog.php:22-26` — only existing `createdAt` example (no shared timestamp trait exists)
- `src/Repository/UserHasGroupRepository.php:24-41` — `findByUserAndGroup`/`isUserInGroup`, template for `FriendshipRepository::findBetweenUsers`/`areFriends`
- `src/Repository/UserHasGroupRepository.php:46-59` — eager-load pattern (`join`/`addSelect`) to avoid N+1, relevant for friend list
- `src/Repository/UserRepository.php:48-79,87-111` — manual QB pagination pattern
- `src/Service/GroupMembershipService.php:30-108` — service-layer conventions (NotFoundHttpException + custom exceptions, `@throws` docblocks)
- `src/Exception/CannotRemoveLastOwnerException.php:7-13` — domain exception template (`extends \RuntimeException`)
- `src/Controller/Admin/GroupMembershipController.php:28-138` — controller conventions (routing, DTO validation, response shaping, try/catch → JSON error)
- `src/Dto/GroupMembership/AddUserToGroupDto.php`, `UpdateUserRoleDto.php` — older mutable DTO style with enum `tryFrom` conversion
- `src/Dto/Event/CreateEventDto.php` — preferred `final readonly class` DTO style
- `src/Dto/Response/GroupMembershipDto.php`, `UserListItemDto.php`, `GroupListItemDto.php` — response DTO `fromEntity`/`fromEntities` factory convention
- `src/Entity/UserInvitationToken.php:13-35` — secret-token entity (sha256-at-rest, hardcoded `+1 day` expiry — anti-pattern to avoid)
- `src/Controller/InvitationController.php:30-76` — three-step token guard sequence (not-found → used → expired)
- `src/Controller/Admin/UserController.php:74-138` — inline token creation/resend, "invalidate previous tokens on retry" pattern
- `src/Service/InvitationMailer.php:13-20` — `#[Autowire(env: ...)]` pattern for injecting env config into a service
- `config/packages/rate_limiter.yaml` — commented-out example only; no active custom limiter; `RateLimiterFactory` unused in `src/`
- `config/packages/security.yaml:19-61` — firewall/access_control shape, `login_throttling` (3/15min), `when@dev`/`when@test` custom authenticator wiring
- `src/Security/GroupVoter.php` — full voter template (`supports`/`voteOnAttribute`, ROLE_ADMIN short-circuit, ownership checks)
- `src/Security/DevHeaderAuthenticator.php:19-58` — dev-header auth resolution for tests
- `src/Controller/EventController.php:1-77` — confirms Event has no owner/voter yet (not a pattern source for Friendship)
- `tests/DatabaseTestCase.php:11-43` — schema drop/recreate + fixture load once per test class
- `tests/Controller/EventControllerTest.php:24-41` — `X-Dev-User` header test pattern
- `tests/Functional/Controller/GroupControllerTest.php:32-69,158-165` — session-login + dynamic fixture-ID lookup test pattern (better template for Friendship)
- `src/DataFixtures/AppFixtures.php:22-53` — Alice YAML fixture loading + Postgres-only autoincrement reset
- `fixtures/users.yaml`, `fixtures/groups.yaml`, `fixtures/user_has_groups.yaml` — deterministic fixture map to extend with a new `fixtures/friendships.yaml`
- `migrations/Version20260218172813.php:23`, `Version20260310000001.php:19-20` — migration conventions (int identity PK, `TIMESTAMP(0) WITHOUT TIME ZONE`, cautionary retrofitted-unique-index example)
- `migrations/Version20260125215059.php:26-30` — `user_has_group` table DDL shape to mirror for a `friend_request` table

## Architecture Insights

- **No UUIDs anywhere** — every entity uses `int` autoincrement PKs (`GENERATED BY DEFAULT AS IDENTITY`). Follow this for Friendship.
- **No shared timestamp trait** — each entity that needs `createdAt` declares it manually (see `UserActivityLog`). Nothing to reuse; declare fields directly on the new entity.
- **No global exception listener** — every controller manually catches domain exceptions and maps them to `{'error': CODE, 'message': ...}` JSON. This must be replicated for the new `FriendshipController`.
- **Two DTO styles coexist**; the newer `final readonly class` style (`CreateEventDto`) is the better one to extend going forward.
- **`symfony/rate-limiter` is a declared but unused dependency** in application code — the codebase's real "N per window" precedent is Symfony's declarative `login_throttling`, not a custom `RateLimiterFactory`. For the FR-006 cooldown, a manual timestamp-based check (mirroring the invitation-token expiry check) is more consistent with existing code than introducing the rate-limiter component for the first time.
- **Env config for service-level values** goes through `#[Autowire(env: 'int:VAR_NAME')]` directly on the consuming service's constructor — this is the pattern to use for `FRIEND_REQUEST_COOLDOWN_DAYS` (or similar), keeping it configurable per the roadmap's explicit "not hardcoded" requirement.
- **Voters are the established authorization mechanism** beyond simple `ROLE_*` checks (`GroupVoter`), auto-registered by implementing `Voter` — no manual service tagging required.
- **Fixtures are Alice YAML at repo root**, not PHP classes under `src/DataFixtures/` (only the loader/orchestration class lives there). New Friendship fixtures follow this file-based convention.

## Historical Context (from prior changes)

- `context/changes/trip-domain-model/` — the folder existed in git status as deleted (`change.md`, `frame.md`) at the time of this research; this suggests a prior/parallel exploration of the same overall Event/Friendship domain problem that was abandoned or superseded by the current roadmap-driven approach (`context/foundation/roadmap.md`, `context/foundation/prd.md`). No content was read from it since it's deleted in the working tree — if relevant prior decisions are needed, check `git show HEAD:context/changes/trip-domain-model/change.md` before this change proceeds further, though the current roadmap/PRD already supersede it as the authoritative source.
- `context/archive/2026-07-12-cicd-rework/`, `context/archive/2026-07-12-fix/` — unrelated to Friendship (CI/CD and an unspecified fix); not consulted further.

## Related Research

- `context/foundation/prd.md` — defines FR-005/FR-006/FR-007 (Friendship) and FR-001–FR-004 (Event ownership/invites, blocked on this slice) with Socratic resolutions already recorded (no rate limit for FR-005 in MVP; cooldown length for FR-006 still an open question in the PRD's `## Open Questions` #3, though the roadmap has since resolved it to 3 days/configurable — see roadmap `## Open Roadmap Questions` resolution note).
- `context/foundation/roadmap.md` — slice S-01 (`friendship-requests`) is this change; already resolved the cooldown-length open question to "3 days, env-configurable, not hardcoded."

## Open Questions

- None blocking for this research. The roadmap has already resolved the two items that were open in the PRD (cooldown length = 3 days, configurable; no rate limit on sending requests in MVP). Any remaining design decisions (e.g., exact enum values, exact env var name, exact HTTP status codes per error case) are solution-design choices for `/10x-plan`, not research gaps.

---

## Follow-up Research 2026-08-01T14:00:52Z — Frontend implementation patterns

**Context**: `friendship-requests` was promoted from a backend-only change to a full-stack one (see `context/foundation/roadmap.md`'s Baseline note and the `chore(context): promote friendship-requests...` commit). This follow-up researches the `frontend/` (Next.js 16 App Router) side, since the original research above and `plan.md`'s three phases cover backend only.

**Git commit**: c6c8fceac69f20d3db1658aaa707b552017b3104
**Branch**: chore/promote-friendship-requests-fullstack
**Repository**: planner (monorepo — `frontend/`)

### Research Question

What existing frontend patterns (routing, auth, components, data-fetching, API client, error handling, testing) should a Friendship UI (send/accept/decline requests, friend list) follow?

### Summary — the headline finding

**The frontend already has a fully-built Friends UI, wired to nothing.** `app/(dashboard)/friends/page.tsx` + `components/friends/FriendsView.tsx` (560 lines) render tabs for Friends / Invitations (received+sent) / Suggestions, with Accept/Decline/Cancel/Add buttons already in place — but every list is backed by hardcoded `MOCK_*` arrays (`FriendsView.tsx:62-154`, explicitly commented `// Mock data — replace with API when backend ready`) and the action buttons have **no onClick handlers**. The sidebar nav item "Friends" → `/friends` also already exists (`components/sidebar/NavPages.tsx:43-47`) with a hardcoded `badge: 0`. This changes the shape of Phase-4-equivalent work from "build a UI" to "wire an existing UI to real data" — plus reconcile scope gaps (see below).

### Detailed Findings

#### Routing & auth (all free — no new work needed)

- Route groups: `(auth)` public (`/login`, `/set-password`, no auth check) vs `(dashboard)` protected (`app/(dashboard)/layout.tsx:14-43` does a client-side `useAuth()` gate + `router.push("/login")` redirect). Any new route under `(dashboard)/` inherits this for free.
- `/friends` already exists as a protected route at `app/(dashboard)/friends/page.tsx:1-10`; no new routing work needed, only wiring.
- Auth/session: `hooks/useAuth.ts:10-59` — a `useQuery(["auth","me"], ...)` hook (not a Context/Zustand store), treats HTTP 401 as `user: null` rather than throwing, `refetchInterval: 10min`. `User.roles` (`types/auth.ts:3-10`) drives admin-only UI gating elsewhere (`isAdmin = user?.roles?.includes("ROLE_ADMIN")`).
- Nav: `components/sidebar/NavPages.tsx:43-47` — Friends item has a `badge?: number` field, currently hardcoded `0`; wiring it to a live pending-count is a natural follow-up once the query hooks exist.

#### API client & error handling (`lib/api.ts`, full file, 78 lines)

- `api.get/post/put/postFormData/delete` — thin fetch wrapper, always `credentials: "include"`, base URL from `NEXT_PUBLIC_API_URL` (default `http://localhost:8000`). **No `api.patch` method exists** despite `frontend/CLAUDE.md` documenting one — stale doc, not a real gap for Friendship (no PATCH endpoints planned).
- On non-2xx, throws `ApiError extends Error` with `{status, statusText, body}` (`lib/api.ts:3-11`) — `body` is the full parsed backend JSON, i.e. the unified `{error, message, timestamp, path}` envelope from `api-exception-handling`. **`ApiError.body` is populated everywhere but consumed nowhere** — every existing `onError` handler in this codebase shows a hardcoded generic toast string (e.g. `hooks/useAddGroupMember.ts:25`: `toast.error("Błąd", { description: "Nie udało się dodać użytkownika do grupy" })`), never `error.body.message` or a switch on `error.body.error`. A Friendship feature that wants to show e.g. "Already friends" vs "Cooldown active" as distinct messages (its error taxonomy has 7 distinct codes per `plan.md` Phase 2 — `CANNOT_FRIEND_SELF`, `ALREADY_FRIENDS`, `DUPLICATE_FRIEND_REQUEST`, `FRIEND_REQUEST_COOLDOWN_ACTIVE`, etc.) would be the **first** feature in this codebase to actually branch on the backend's `error` code — no existing convention to copy, only the `ApiError` class to build on.
- One risk noted: if a non-OK response body isn't valid JSON, the `await res.json()` inside `throw new ApiError(...)` itself throws and masks the real error (no try/catch around that parse) — pre-existing client bug, not introduced by Friendship, but worth being aware of if the backend ever returns a non-JSON 5xx for a friend-request call.
- Global 401 handling lives in `lib/queryClient.ts:14-24` (mutation-level `onError`, redirects to `/login` after a toast) — applies automatically to any new Friendship mutation hook, no extra wiring needed.

#### Data fetching / mutations (TanStack Query v5, no server actions, no SWR)

- Query key convention: array namespaced by domain, e.g. `["admin","groups", groupId, "members"]`, `["auth","me"]` → Friendship should use `["friends"]`, `["friends","requests"]` (or split `["friends","requests","incoming"|"outgoing"]` to match the backend's `{incoming, outgoing}` response shape from `plan.md`).
- **Direct copy-paste templates already exist** for every operation Friendship needs:
  - List query → `hooks/useGroupMembers.ts` (7 lines, `useQuery` + `api.get`)
  - Create mutation w/ toast + invalidate → `hooks/useAddGroupMember.ts` (21 lines)
  - Delete/remove mutation → `hooks/useRemoveGroupMember.ts` / `hooks/useDeleteGroup.ts` (ID-in-URL action, closest template for accept/decline-by-id)
  - Debounced search → `hooks/useSearchUsers.ts` (`enabled: enabled && search.length >= 2` gate) — useful if "send request" becomes a user-search autocomplete rather than a raw email field (backend's `POST /friend-requests` per `plan.md` takes `{email}` directly, so this may not be needed for MVP, but the frontend mock (`Suggestion` tab) implies a richer UX was originally envisioned)
- Response envelope convention: every existing list type is `{ data: T[] }` (`types/groups.ts`, `types/api.ts`). **Mismatch to resolve during planning**: backend's planned `GET /friend-requests` returns `{incoming: [...], outgoing: [...]}` (no `data` wrapper) per `plan.md` Phase 3 — a new `types/friends.ts` will need to deliberately break from the `{data: T[]}` convention for that one endpoint, or the backend plan should be revisited to match the frontend convention. Flagging as an open question below.
- No generated types — no OpenAPI/Swagger codegen despite Swagger existing at `/api/doc`. Types are hand-written per domain (`types/groups.ts`, `types/events.ts`, etc.) — a new `types/friends.ts` follows this convention.

#### UI components (shadcn/ui on Radix, `components/ui/`)

- Everything Friendship needs already exists as a primitive: `Badge` (status pills — pattern: `<Badge variant={...} className="capitalize">{status}</Badge>`, see `components/users/GroupsTableColumn.tsx:166-173`), `Avatar`, `Card`, `Dialog`/`AlertDialog` (confirm flows), `Tabs` (already used by `FriendsView` for the incoming/outgoing split), `Skeleton` (loading state), `sonner`-backed `Toast`.
- `FriendsView.tsx:331-334` already has a "pending" badge with a `Clock` icon — the exact idiom (`variant="outline" + icon + PL label`) to extend for `accepted`/`declined` states.
- Icons: both `lucide-react` (used inside `FriendsView.tsx`/`EventsView.tsx`) and `@tabler/icons-react` (used in nav/dialogs) coexist — `FriendsView` already uses lucide, so continue with that inside it.
- Forms: react-hook-form + zod + shadcn `Field`/`FieldError`, e.g. `components/users/InviteUserDialog.tsx` (101 lines) — near-exact template for a "send friend request" dialog (single validated email field, dialog trigger, mutation on submit, success/error toast).

#### Testing

- Vitest + Testing Library + jsdom, **no Playwright/E2E config in `frontend/`** (the Playwright MCP tool available in this environment isn't wired into the project's own test suite — confirms `/10x-e2e` isn't applicable here unless E2E tooling is added first).
- Exactly one existing test in the whole frontend, and it's a hook test: `hooks/useDeleteGroup.test.ts` — mocks `@/lib/api` and `sonner`, wraps in a fresh `QueryClientProvider`, asserts `mutate()` calls the right endpoint and shows a toast. Direct template for testing `useAcceptFriendRequest`/`useDeclineFriendRequest`/etc. No component-level (`render()`) test exists yet anywhere in the repo — a `FriendsView` test would be the first of its kind.

### Code References (frontend)

- `frontend/components/friends/FriendsView.tsx:1-559` — existing mock UI to wire up (MOCK_* arrays lines 62-154; unwired Accept/Decline/Cancel/Add buttons at lines 298-305, 337-339, 377-380)
- `frontend/app/(dashboard)/friends/page.tsx:1-10` — existing route, already protected, no changes needed beyond whatever FriendsView needs
- `frontend/components/sidebar/NavPages.tsx:43-47` — existing "Friends" nav item, `badge: 0` hardcoded, wire to live pending-count later
- `frontend/lib/api.ts:1-78` — fetch client + `ApiError` (full file)
- `frontend/lib/queryClient.ts:1-28` — global QueryClient config incl. 401 handling
- `frontend/hooks/useAuth.ts:10-59` — session/auth hook
- `frontend/hooks/useGroupMembers.ts`, `useAddGroupMember.ts`, `useRemoveGroupMember.ts`, `useDeleteGroup.ts`, `useSearchUsers.ts`, `useInvite.ts` — direct hook templates for list/create/remove/search/invite-by-email
- `frontend/hooks/useDeleteGroup.test.ts` — hook test template
- `frontend/components/users/InviteUserDialog.tsx` — react-hook-form + zod dialog-form template (closest to "send friend request")
- `frontend/components/users/GroupsTableColumn.tsx:166-173`, `UsersTableColumn.tsx:208-220` — status Badge rendering pattern
- `frontend/types/groups.ts`, `types/auth.ts`, `types/api.ts` — response-type conventions (`{data: T[]}` envelope) to follow/reconcile for `types/friends.ts`
- `frontend/components/events/EventsView.tsx` — alternate list-view reference (client-side pagination + skeleton loading), less directly relevant than FriendsView/GroupMembers since FriendsView already exists

### Architecture Insights (frontend)

- **No server actions, no middleware.ts, no SSR-fetched auth** — this app is a client-rendered SPA-over-App-Router: every data fetch and the auth check itself happen in `"use client"` components via React Query. A Friendship implementation should follow this same client-only pattern, not introduce server components/actions as a new pattern.
- **No client-side global store (Zustand/Redux/Context)** for domain or auth state — React Query's cache *is* the app's state layer. Friendship state (friends list, pending requests) should live purely in query cache, invalidated on mutation success, exactly like Group membership.
- **Error-code branching is an unestablished pattern** — this is the one genuine gap. Every existing mutation hook shows a static toast string regardless of what the backend actually said. Since Friendship's backend plan defines 7 distinct domain error codes (vs. Group's ~2), this feature will likely be the one to establish "read `error.body.error`, map to a friendly message" as a reusable pattern (e.g. a small `getErrorMessage(error: unknown, fallback: string): string` helper) — worth deciding explicitly during `/10x-plan`, not left implicit.
- **A UI/backend contract mismatch exists today** (`{data: T[]}` envelope convention vs. planned `{incoming, outgoing}` shape for `GET /friend-requests`) — worth resolving explicitly in planning: either the frontend type breaks convention for this one endpoint, or the backend plan's response shape is revisited before Phase 3 implementation. Flagged, not resolved, here.

### Open Questions (frontend)

1. **Should `/10x-frame` or `/10x-plan` treat "wire the existing FriendsView mock" as the frontend phase(s), or should the existing mock UI be reconsidered/rebuilt?** The "Suggestions" tab (`SuggestionCard`, mutual-friends/mutual-events fields) has **no corresponding backend endpoint** in `plan.md`'s Phase 3 — it needs to be either deferred/removed from the UI or added as new backend scope. This is a scope decision, not a research gap.
2. **Response-shape mismatch** (`{data: T[]}` vs `{incoming, outgoing}`) — resolve during planning, see Architecture Insights above.
3. **Error-code-to-message mapping convention** — no existing pattern; needs an explicit design decision in `/10x-plan` rather than each hook inventing its own.
