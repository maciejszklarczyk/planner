---
date: 2026-08-08T22:37:00+02:00
researcher: Maciej Szklarczyk
git_commit: 40354dd5bb3e8d9ce51ccabd1b58426569e25ec6
branch: friendship-requests-implementation
repository: planner
topic: "Rollout Phase 1 grounding: authorization & data-boundary hardening (Risks #1, #6)"
tags: [research, codebase, friendship, authorization, voter, user-search, sql-injection, like-injection]
status: complete
last_updated: 2026-08-08
last_updated_by: Maciej Szklarczyk
---

# Research: Authorization & data-boundary hardening (test-plan Phase 1)

**Date**: 2026-08-08T22:37:00+02:00
**Researcher**: Maciej Szklarczyk
**Git Commit**: 40354dd5bb3e8d9ce51ccabd1b58426569e25ec6
**Branch**: friendship-requests-implementation
**Repository**: planner

## Research Question

Ground rollout Phase 1 of `context/foundation/test-plan.md` ("Authorization & data-boundary hardening") for Risk #1 (a user acts on or sees another user's friend-request/friend data) and Risk #6 (`GET /users?search=` mishandles adversarial/malformed input). For each risk: locate the real failure path in code, verify or correct the plan's Risk Response Guidance, find existing tests, identify the cheapest useful test layer, and flag speculative risks or misleading hot-spot evidence.

## Summary

**Risk #1 (authorization bypass on friend data)**: largely well-covered already. `FriendshipVoter` + a two-stage controller gate (`resolveFriendRequest` participant-check → `denyAccessUnlessGranted`) correctly returns 404 for non-participants and 403 for wrong-side participants on `accept`/`cancel`, backed by passing controller-level tests. This exact 403-vs-404 design was the fix for a previously-caught bug (impl-review finding F5). `list` endpoints need no voter since repository queries are always scoped to the caller's id. **Real gaps found, not speculative**: (a) `decline` is missing the wrong-participant-403 and non-participant-404 controller tests that `accept`/`cancel` both have; (b) no isolation regression test proves user A's `GET /friend-requests`/`GET /friends` response never contains user B's data; (c) `UserListItemDto` (used in friend/request responses) exposes `roles`/`status` of the other user, while `UserSearchResultDto` deliberately trims those same fields — a field-exposure policy inconsistency, though bounded to actual friends/participants (not a stranger-facing leak).

**Risk #6 (adversarial search input)**: SQL injection is fully mitigated — `search` and `excludeUserId` are always bound via Doctrine `setParameter`, never concatenated. But the plan's "Doctrine ORM = automatically safe" framing needs correcting: there is a **real, unmitigated LIKE-wildcard-injection gap** — `$search` is wrapped in `'%'.$search.'%'` with no escaping of `%`/`_`, so a query of `search=%` matches every row, turning a "find one person" endpoint into a full-user-enumeration primitive (id/name/email/avatar, up to 50/request, pageable) available to any authenticated non-admin user. Blank search is safely guarded at the controller (`UserController.php`) but **not** at the repository (`findWithPagination` returns everyone on `''`) — an architecturally fragile safety property that depends on every future caller remembering the controller-level guard. No length validation exists but is not exploitable (email column is 180 chars, LIKE scan is cheap). None of these three edge cases (wildcard, blank-at-repo-layer, oversized) has a regression test today.

## Detailed Findings

### Risk #1 — Friendship authorization

#### Domain layout

- Entity: `backend/src/Entity/FriendRequest.php` — `requester`/`addressee` (`ManyToOne User`), `status` enum, `createdAt`/`respondedAt`.
- Controller: `backend/src/Controller/FriendshipController.php` — `POST /friend-requests` (send), `GET /friend-requests` (list pending), `POST /friend-requests/{id}/accept|decline|cancel`, `GET /friends` (list friends).
- Service: `backend/src/Service/FriendshipService.php` — deliberately does **not** check authorization; docblocks (lines 105, 141) state this is the voter/controller's job, matching `GroupMembershipService`'s convention.
- Repository: `backend/src/Repository/FriendRequestRepository.php` — `findAcceptedForUser($userId)` and `findPendingForUser($userId)` both filter `WHERE fr.requester = :userId OR fr.addressee = :userId`. No unscoped list method exists.

#### Voter — `FriendshipVoter`

`backend/src/Security/FriendshipVoter.php:19-26` — `supports()` gates to `ACCEPT`/`DECLINE`/`CANCEL` attributes and `FriendRequest` subjects only.

`backend/src/Security/FriendshipVoter.php:48-76`:
```php
private function canRespond(FriendRequest $friendRequest, User $user, ?Vote $vote): bool
{
    if ($user === $friendRequest->getAddressee()) {
        return true;
    }
    ...
    return false;
}

private function canCancel(FriendRequest $friendRequest, User $user, ?Vote $vote): bool
{
    if ($user === $friendRequest->getRequester()) {
        return true;
    }
    ...
    return false;
}
```
`ACCEPT`/`DECLINE` require `user === addressee`; `CANCEL` requires `user === requester`. **No `ROLE_ADMIN` bypass** — deliberate, per `context/archive/2026-07-22-friendship-requests/plan-brief.md:34`: *"Friendship voter admin bypass | None (unlike `GroupVoter`) | Friendship is personal, not admin-manageable — PRD gives `ROLE_ADMIN` no capability over it."*

#### Controller wiring — the two-stage gate

`backend/src/Controller/FriendshipController.php:54-107`:
```php
#[Route('/friend-requests/{id}/accept', name: 'accept_friend_request', methods: ['POST'])]
public function accept(int $id, #[CurrentUser] User $user): JsonResponse
{
    $request = $this->resolveFriendRequest($id, $user);
    $this->denyAccessUnlessGranted(FriendshipVoter::ACCEPT, $request);
    $accepted = $this->friendshipService->acceptRequest($request);
    return $this->json(FriendRequestDto::fromEntity($accepted, $user));
}
```
Same shape for `decline` (lines 65-74) and `cancel` (lines 76-85).

`backend/src/Controller/FriendshipController.php:99-107`:
```php
private function resolveFriendRequest(int $id, User $user): FriendRequest
{
    $request = $this->friendRequestRepository->find($id);
    if (!$request || ($request->getRequester() !== $user && $request->getAddressee() !== $user)) {
        throw new FriendRequestNotFoundException("Friend request {$id} not found.");
    }
    return $request;
}
```
Non-participant → 404 (avoids leaking row existence via 403-vs-404). Participant on the wrong side → 403 via the voter, correct `FriendRequest` entity passed as subject every time.

`listPending` (lines 44-52) and `listFriends` (lines 88-93) call **no** voter — correct by construction since repository queries are always scoped to `$user`'s own id; there's no `{id}` subject to authorize against.

This two-stage design is the fix for **impl-review finding F5** (`context/archive/2026-07-22-friendship-requests/`): before the fix, participancy wasn't checked before the voter ran, so a non-participant got 403-if-exists vs 404-if-not, leaking sequential-id occupancy. The fix and its regression tests (`testAcceptFriendRequestAsNonParticipantReturns404`, `testCancelFriendRequestAsNonParticipantReturns404`) are exactly what's in the controller today.

#### Response DTO field-exposure inconsistency

- `UserSearchResultDto` (`backend/src/Dto/Response/UserSearchResultDto.php`) — used only by `GET /users?search=` — trimmed to `id`/`name`/`email`/`avatar`, deliberately (per plan-brief.md: *"avoids leaking roles/status of every user to any authenticated member"*).
- `UserListItemDto` (`backend/src/Dto/Response/UserListItemDto.php`) — full shape including `roles`/`status` — used by `FriendRequestDto::fromEntity()` for `otherUser` (`FriendRequestDto.php:29`) and directly by `listFriends` (`FriendshipController.php:92`).

So once two users are friends (or even have a pending request between them), one can see the other's `roles` and `status` — fields the search endpoint explicitly withholds. **Not a cross-user leak** in the Risk #1 sense (bounded to actual friends/participants, verified scoped by repository queries and the participant check), but a policy inconsistency worth a follow-up decision.

#### Existing tests (controller-level, not voter-in-isolation)

`backend/tests/Functional/Controller/FriendshipControllerTest.php` (363 lines, `WebTestCase`-based). No unit test for `FriendshipVoter` exists (unlike `GroupVoter`, which has `tests/Unit/Security/GroupVoterTest.php`) — coverage is entirely at the functional/controller level, which is exactly what the test-plan's Risk Response Guidance for #1 demands ("must be proven against the actual controller wiring, not the voter class in isolation").

- `testAcceptFriendRequestAsAddresseeSucceeds` (118-132) — happy path, 2xx.
- `testAcceptFriendRequestAsRequesterIsForbidden` (134-147) — wrong-side participant → 403.
- `testAcceptFriendRequestAsNonParticipantReturns404` (149-163) — non-participant → 404.
- `testCancelFriendRequestAsAddresseeIsForbidden` (241-254) — wrong-side participant → 403.
- `testCancelFriendRequestAsNonParticipantReturns404` (278-293) — non-participant → 404.
- `testDeclineFriendRequestAsAddresseeSucceeds` (165-179) — happy path only. **No** `testDeclineFriendRequestAsRequesterIsForbidden` or `testDeclineFriendRequestAsNonParticipantReturns404` exist, even though `decline` shares the identical `resolveFriendRequest`/voter code path as `accept`/`cancel`. Gap in test coverage, not a proven bug — but unverified.
- `testListPendingRequestsShowsIncomingAndOutgoing` (333-349) / `testListFriendsShowsAcceptedPair` (351-362) assert content correctness for the calling user only — no negative assertion proving another user's data is absent from the response.

#### Historical context

`context/archive/2026-07-22-friendship-requests/`:
- `research.md:38` — *"`GroupVoter` is the only voter in the codebase and the template for a `FriendshipVoter` — checks `$user === sender || $user === recipient` for accept/decline actions."*
- `plan-brief.md:28,34` — confirms no-admin-bypass and trimmed-search-DTO are deliberate calls, not oversights.
- `reviews/impl-review.md` (2026-08-01, verdict "NEEDS ATTENTION (pre-triage) — all CONFIRMED findings fixed or explicitly accepted"):
  - **F4** (OBSERVATION, LOW, `FriendshipService.php:43`) — `sendRequest()` 404s on unregistered email, allowing exact-email-registration probing. **SKIPPED** — accepted, one of seven documented error codes, changing it breaks the frontend contract.
  - **F5** (OBSERVATION → **FIXED**, `FriendshipController.php:95`) — the 403-vs-404 existence leak described above. Fixed and tested.

### Risk #6 — `GET /users?search=` adversarial input

#### Controller

`backend/src/Controller/UserController.php:38-55`:
```php
#[Route('/users', name: 'user_search', methods: ['GET'])]
public function search(Request $request, #[CurrentUser] User $currentUser): JsonResponse
{
    $search = $request->query->get('search');
    $limit = max(1, min(50, (int) $request->query->get('limit', 20)));

    if (null === $search || '' === $search) {
        return $this->json(['data' => []]);
    }

    $users = $this->userRepository->findWithPagination(
        limit: $limit,
        search: $search,
        excludeUserId: $currentUser->getId(),
    );

    return $this->json(['data' => UserSearchResultDto::fromEntities($users)]);
}
```
No `#[IsGranted]` (falls under global `ROLE_USER` access_control). `$search` has no length validation, no character allow-listing, no `Assert\...` constraints, no `MapQueryString`/`MapQueryParameter` DTO (unlike request-body DTOs elsewhere, e.g. `EditUserDto` via `#[MapRequestPayload]`).

#### Query-building — SQL injection is fully mitigated

`backend/src/Repository/UserRepository.php:56-63` (duplicated in `countWithFilters`, 101-108):
```php
$qb = $this->createQueryBuilder('u')->orderBy('u.id', 'ASC');

if (null !== $search && '' !== $search) {
    $qb->andWhere('u.email LIKE :search')
        ->setParameter('search', '%'.$search.'%');
}
```
`$search` is always bound via `setParameter` — never concatenated into DQL/SQL. Classic SQL injection (breaking out of a string literal) is **not possible**. `excludeUserId` (lines 76-79) is likewise always parameterized, and is always the authenticated caller's own id (`$currentUser->getId()`), never attacker-controlled from this endpoint. **This confirms the plan's assumption that Doctrine parameterization prevents SQL injection here — that part of "Doctrine ORM = automatically safe" is actually true.**

#### The real gap — LIKE-wildcard scope-widening

Doctrine's parameter binding protects the query *structure*, but does nothing about the *semantic* meaning of `%`/`_` inside the bound LIKE pattern — those are interpreted by the database as wildcards regardless of parameterization. No code anywhere escapes `%`/`_`/backslash before wrapping `$search` in `%...%` (confirmed by grep — no `addcslashes`, no Doctrine LIKE-escape helper, in either `UserController.php` or `UserRepository.php`).

Concretely: `search=%` (or `search=_`) is bound as `%%%` (effectively `LIKE '%'`), matching **every row**. This lets any authenticated `ROLE_USER` (not admin-gated) turn a "find one specific person by partial email" endpoint into a full-user-enumeration primitive — up to 50 users per request (id/name/email/avatar via `UserSearchResultDto`), repeatable. **This is the concrete failure mode the plan's "must challenge: Doctrine ORM = automatically safe" line was gesturing at, but the plan didn't name the mechanism (LIKE-wildcard injection) — worth correcting the guidance to name it explicitly.**

#### Blank-search safety is controller-only, not repository-enforced

- Controller (`UserController.php:44-46`) explicitly short-circuits blank/missing `search` to `[]` — confirmed safe via the public endpoint. Tested: `testSearchWithBlankQueryReturnsEmptyList`, `testSearchWithMissingQueryReturnsEmptyList` (`UserControllerTest.php:57,69`).
- Repository (`UserRepository.php:60`, `findWithPagination`) has **no such guard** — blank `$search` skips the `WHERE` clause entirely and returns *all* users (bounded only by `limit`). Locked in by `UserRepositoryTest::testFindWithPaginationEmptySearchReturnsAll` (`UserRepositoryTest.php:54-60`), which asserts count-all equals count-blank-search.
- This is architecturally fragile: the "blank search ⇒ no over-broad exposure" property lives entirely in the controller, not the data-access boundary. The only other caller of `findWithPagination`/`countWithFilters` today is `Admin\UserController::list()` (`ROLE_ADMIN`-gated, intentionally returns everyone on absent search) — not currently exploitable, but a future non-admin caller that forgets the guard would silently reintroduce the leak. Confirmed intentional per `context/archive/2026-07-22-friendship-requests/plan.md:316`: *"search (string, required — return an empty data array if blank rather than the full user list, to avoid accidentally exposing every user)"*.

#### Oversized input

No length limit anywhere. `User.email` is `#[ORM\Column(length: 180)]` (`src/Entity/User.php:31`), so long inputs can't match, but nothing rejects/truncates before the query runs — wasted but harmless `LIKE` scan, no realistic DoS given the cheap semantics and short column. Not a defense-in-depth gap worth a hard block, but untested.

#### Response DTO

`backend/src/Dto/Response/UserSearchResultDto.php:9-14` — `id`, `name`, `email`, `avatar`. Deliberately excludes `roles`/`status` vs. the admin DTO. Combined with the wildcard gap, this becomes a **user-enumeration / email-harvesting exposure**: any authenticated user can harvest id/name/email/avatar for up to 50 users per request with a single `%` query, repeatable across calls.

#### Existing tests

`backend/tests/Functional/Controller/UserControllerTest.php`:
- `testSearchByPartialEmailMatchReturnsMatchingUsers` (15), `testSearchExcludesTheCallersOwnAccount` (28), `testSearchResultOmitsRolesAndStatus` (42), `testSearchWithBlankQueryReturnsEmptyList` (57), `testSearchWithMissingQueryReturnsEmptyList` (69), `testSearchWithoutAuthenticationReturns401` (81).

`backend/tests/Functional/Repository/UserRepositoryTest.php`:
- `testFindWithPaginationSearchMatchesEmail` (39), `testFindWithPaginationSearchIsPartialMatch` (47), `testFindWithPaginationEmptySearchReturnsAll` (54), `testCountWithFiltersSearchMatchesEmail` (102), `testCountWithFiltersEmptySearchMatchesAll` (109).

**Missing** (confirmed absent by full read of both files):
- No test for `search=%` or `search=_` (the real wildcard-scope-widening gap).
- No test for a very long search string.
- No test for SQL metacharacters (`'`, `"`, `;`, `--`) asserting safe/expected results end-to-end (static parameterization confirmed by code review, but no regression test locks it in).
- No test combining `excludeUserId` with an adversarial `search` value.
- No test on `limit` boundary/adversarial values (`limit=-1`, `limit=abc`, `limit=99999`) — clamped at `UserController.php:42` (`max(1, min(50, ...))`) but the clamp itself is untested.

#### Historical context

`context/archive/2026-07-22-friendship-requests/plan.md`:
- Line 18 — admin `list()` returns full `UserListItemDto`, explicitly deemed unsuitable to expose to `ROLE_USER` "as-is."
- Line 62 — *"Phase 4 reuses UserRepository's existing email LIKE %search% matching; no new search backend ... is introduced"* — the raw-LIKE approach is intentional/accepted debt, but the plan never risk-assessed wildcard-scope-widening specifically.
- Line 316 — blank-search guard is a deliberate controller-layer decision.
- Line 323 — original acceptance criteria list partial-match, exclude-self, 401-unauth, blank-returns-empty — **no** wildcard/length/injection hardening was ever in scope.

## Code References

- [`backend/src/Security/FriendshipVoter.php:19-26`](https://github.com/maciejszklarczyk/planner/blob/40354dd5bb3e8d9ce51ccabd1b58426569e25ec6/backend/src/Security/FriendshipVoter.php#L19-L26) — voter `supports()` gate.
- [`backend/src/Security/FriendshipVoter.php:48-76`](https://github.com/maciejszklarczyk/planner/blob/40354dd5bb3e8d9ce51ccabd1b58426569e25ec6/backend/src/Security/FriendshipVoter.php#L48-L76) — `canRespond`/`canCancel` participant checks, no admin bypass.
- [`backend/src/Controller/FriendshipController.php:54-107`](https://github.com/maciejszklarczyk/planner/blob/40354dd5bb3e8d9ce51ccabd1b58426569e25ec6/backend/src/Controller/FriendshipController.php#L54-L107) — accept/decline/cancel actions and `resolveFriendRequest` two-stage gate.
- [`backend/src/Repository/FriendRequestRepository.php`](https://github.com/maciejszklarczyk/planner/blob/40354dd5bb3e8d9ce51ccabd1b58426569e25ec6/backend/src/Repository/FriendRequestRepository.php) — `findAcceptedForUser`/`findPendingForUser`, always scoped to `:userId`.
- [`backend/src/Dto/Response/UserSearchResultDto.php:9-14`](https://github.com/maciejszklarczyk/planner/blob/40354dd5bb3e8d9ce51ccabd1b58426569e25ec6/backend/src/Dto/Response/UserSearchResultDto.php#L9-L14) — trimmed search DTO (no roles/status).
- [`backend/src/Dto/Response/UserListItemDto.php`](https://github.com/maciejszklarczyk/planner/blob/40354dd5bb3e8d9ce51ccabd1b58426569e25ec6/backend/src/Dto/Response/UserListItemDto.php) — full DTO (includes roles/status), used by friend/request responses.
- [`backend/tests/Functional/Controller/FriendshipControllerTest.php:118-163,241-293,333-362`](https://github.com/maciejszklarczyk/planner/blob/40354dd5bb3e8d9ce51ccabd1b58426569e25ec6/backend/tests/Functional/Controller/FriendshipControllerTest.php#L118-L163) — existing accept/cancel/list controller tests.
- [`backend/src/Controller/UserController.php:38-55`](https://github.com/maciejszklarczyk/planner/blob/40354dd5bb3e8d9ce51ccabd1b58426569e25ec6/backend/src/Controller/UserController.php#L38-L55) — search action, blank-search controller guard.
- [`backend/src/Repository/UserRepository.php:56-63,76-79,101-108`](https://github.com/maciejszklarczyk/planner/blob/40354dd5bb3e8d9ce51ccabd1b58426569e25ec6/backend/src/Repository/UserRepository.php#L56-L108) — parameterized LIKE query, no wildcard escaping, no blank guard at repository layer.
- [`backend/tests/Functional/Controller/UserControllerTest.php:15-88`](https://github.com/maciejszklarczyk/planner/blob/40354dd5bb3e8d9ce51ccabd1b58426569e25ec6/backend/tests/Functional/Controller/UserControllerTest.php#L15-L88) — existing search controller tests.
- [`backend/tests/Functional/Repository/UserRepositoryTest.php:39-113`](https://github.com/maciejszklarczyk/planner/blob/40354dd5bb3e8d9ce51ccabd1b58426569e25ec6/backend/tests/Functional/Repository/UserRepositoryTest.php#L39-L113) — existing repository search tests, including the blank-returns-all assertion.

## Architecture Insights

- Authorization pattern in this codebase: service layer never checks authorization; voter + controller together do (voter for role-on-subject checks, controller for existence/participancy pre-checks to control 403-vs-404 leakage). `FriendshipVoter` mirrors `GroupVoter`'s shape.
- Deliberate 403-vs-404 discipline: non-existence and non-participancy are both surfaced as 404 to avoid leaking row occupancy — this was a reviewed, tested fix (F5), not the original design.
- DTO trimming is used per-endpoint as the data-boundary control (`UserSearchResultDto` vs `UserListItemDto`) rather than per-role field-level serialization groups — this makes exposure policy easy to audit per DTO class but easy to drift (as seen in the roles/status inconsistency).
- Doctrine parameterization is relied on as the sole SQL-safety mechanism project-wide; it is sufficient for injection but the codebase has no existing pattern for LIKE-wildcard escaping — this would be a new pattern if adopted.

## Historical Context (from prior changes)

- `context/archive/2026-07-22-friendship-requests/plan-brief.md` — records the no-admin-bypass and trimmed-search-DTO decisions as deliberate.
- `context/archive/2026-07-22-friendship-requests/research.md` — establishes `GroupVoter` as the template for `FriendshipVoter`.
- `context/archive/2026-07-22-friendship-requests/plan.md` — records the blank-search-returns-empty controller contract and confirms wildcard/injection hardening was never in the original scope.
- `context/archive/2026-07-22-friendship-requests/reviews/impl-review.md` — F5 (403-vs-404 leak, fixed) and F4 (email-enumeration via send-request, accepted) are the two directly relevant prior findings.

## Related Research

None yet under `context/changes/**/research.md` for this rollout — this is the first Phase 1 research artifact.

## Open Questions

1. Should the `decline` action get the same wrong-participant-403 / non-participant-404 controller tests that `accept`/`cancel` already have? (Recommended: yes, cheap addition, closes a real coverage gap on an identical code path.)
2. Should a `GET /friend-requests`/`GET /friends` isolation test be added (user A never sees user B's rows)? (Recommended: yes, one test each, cheap, closes an unverified-but-likely-safe gap.)
3. Should the `roles`/`status` exposure via `UserListItemDto` in friend/request responses be tightened to match `UserSearchResultDto`'s trimming, or is it an intentional "once you're friends, less is hidden" policy? This is a product decision, not something research can resolve — flag for the plan/user, not silently test around it.
4. Should LIKE-wildcard escaping (`%`, `_`) be added to `UserRepository`'s search, or is bounding via `limit`+trimmed-DTO judged sufficient mitigation? This is the highest-value finding from this research — recommend the plan explicitly decide whether to fix the escaping or only add a regression test documenting current (widen-able) behavior as accepted risk.
5. Should the blank-search safety guard be moved from the controller into `findWithPagination` itself (defense-in-depth at the repository boundary), given it currently depends on every caller remembering to check?
