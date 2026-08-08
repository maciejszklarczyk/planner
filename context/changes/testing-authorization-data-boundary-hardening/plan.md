# Authorization & Data-Boundary Hardening Implementation Plan

## Overview

Close the two authorization test gaps and the two data-boundary code gaps that `research.md` found while grounding rollout Phase 1 of `context/foundation/test-plan.md`: Risk #1 (a user acts on or sees another user's friend-request/friend data) and Risk #6 (`GET /users?search=` mishandling adversarial input, specifically LIKE-wildcard scope-widening).

## Current State Analysis

- **Risk #1** is largely already well-covered: `FriendshipVoter` (`backend/src/Security/FriendshipVoter.php`) + a two-stage controller gate in `FriendshipController::resolveFriendRequest()` (`backend/src/Controller/FriendshipController.php:99-107`) correctly return 404 for non-participants and 403 for wrong-side participants on `accept`/`cancel`, backed by passing controller-level tests (`backend/tests/Functional/Controller/FriendshipControllerTest.php`). `decline` shares the identical code path but only has a happy-path test (`testDeclineFriendRequestAsAddresseeSucceeds`, line 165) — no `testDeclineFriendRequestAsRequesterIsForbidden` or `testDeclineFriendRequestAsNonParticipantReturns404` exist. `listPending`/`listFriends` need no voter (repository queries are always scoped to the caller's id via `FriendRequestRepository::findPendingForUser`/`findAcceptedForUser`) but have no explicit isolation regression test proving another user's data never appears.
- **Risk #6**: SQL injection is fully mitigated — `UserRepository::findWithPagination`/`countWithFilters` (`backend/src/Repository/UserRepository.php:56-63,101-108`) always bind `search` via Doctrine `setParameter`, never concatenate. The real gap: `$search` is wrapped in `'%'.$search.'%'` with no escaping of `%`/`_`, so `search=%` (or `_`) matches every row — turning the "find one person" endpoint into a full-user-enumeration primitive (id/name/email/avatar via `UserSearchResultDto`, up to 50/request, pageable). Doctrine DBAL 4 ships `AbstractPlatform::escapeStringForLike(string $inputString, string $escapeChar): string` (confirmed present in `vendor/doctrine/dbal/src/Platforms/AbstractPlatform.php:2368`) for exactly this purpose.
- Blank search is guarded only in `UserController::search()` (`backend/src/Controller/UserController.php:44-46`, short-circuits before the repository is ever called) — not in the repository itself. `UserRepository::findWithPagination()`/`countWithFilters()` currently treat `search === null` and `search === ''` identically (both skip the `WHERE` clause, both return everything). This identical treatment is what six existing repository tests and `Admin\UserController::list()`'s "browse with no search term" flow (`backend/src/Controller/Admin/UserController.php:42-57`, called with implicit `null` when the `search` query param is absent) rely on — none of them pass an explicit `search: ''`. Only `UserRepositoryTest::testFindWithPaginationEmptySearchReturnsAll` (line 54) and `testCountWithFiltersEmptySearchMatchesAll` (line 109) exercise the explicit-empty-string case, and the public `/users` endpoint can never reach the repository with `search === ''` today (the controller returns `[]` first). The fix must therefore gate on `search === ''` specifically, leaving `search === null` untouched, or it will silently break six unrelated tests and Admin's default browse behavior.

### Key Discoveries:

- `FriendshipController::accept/decline/cancel` (`backend/src/Controller/FriendshipController.php:54-107`) all share `resolveFriendRequest()` — a fix or test pattern proven on `accept`/`cancel` transfers directly to `decline`.
- `UserRepository::findWithPagination`/`countWithFilters` are called with no `search` argument (implicit `null`) by `UserRepositoryTest::testFindWithPaginationReturnsUsers` (line 31), `testFindWithPaginationExcludesGroupMembers` (line 62), `testFindWithPaginationLimitRespectsPageSize` (line 78), `testFindWithPaginationPageTwoReturnsDifferentUsers` (line 85), `testCountWithFiltersReturnsTotal` (line 95), `testCountWithFiltersExcludesGroupMembers` (line 117) — these must keep returning full/paginated results after the fix.
- `Admin\UserController::list()` passes `search: $search` where `$search` is `null` unless the client explicitly sends `?search=` — so it is unaffected by an empty-string-only guard by default; no admin call-site change is needed for the guard itself, only for explicitly documenting intent (see Phase 2).

## Desired End State

- `decline` has the same wrong-participant-403 and non-participant-404 controller test coverage as `accept`/`cancel`.
- `GET /friend-requests` and `GET /friends` each have a regression test proving one user's response never contains another unrelated user's rows.
- `UserRepository`'s LIKE search escapes `%`/`_` so `search=%`/`search=_` match only a literal `%`/`_` character in an email, not every row — proven by a repository-level test and a controller-level end-to-end test.
- `UserRepository::findWithPagination`/`countWithFilters` gain an explicit `allowEmptySearchResults` flag: default `false` means an explicit `search: ''` returns no results; passing `true` preserves today's "return everything" behavior. `search: null` is unaffected either way.
- All of the above is verified by `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit`.

## What We're NOT Doing

- Not adding a length cap or `Assert\Length` validation on the `search` query parameter — research confirmed oversized input is not exploitable (180-char `email` column, cheap `LIKE` scan); this phase only adds a regression test proving that.
- Not changing the `UserListItemDto` vs `UserSearchResultDto` field-exposure inconsistency research flagged (roles/status visible between friends but not via search) — that's a product-policy question, not an authorization bug, and out of scope for this rollout phase.
- Not adding a `FriendshipVoter` unit test in isolation — the test-plan's Risk #1 guidance explicitly requires proof at the controller level, and unit-testing the voter alone would be the anti-pattern it warns against.
- Not touching `excludeGroupId`'s own query (`NOT IN (SELECT IDENTITY(...))`) — it takes no free-text user input and isn't part of either risk.

## Implementation Approach

Two phases, ordered by risk and blast radius: Phase 1 is pure test-addition (zero production-code risk) closing the Risk #1 gaps. Phase 2 makes two small, targeted production-code changes to `UserRepository` (LIKE-escaping, empty-search flag) plus one call-site touch in `Admin\UserController`, backed by regression tests at both the repository and controller layers.

## Phase 1: Risk #1 — Authorization Test Coverage

### Overview

Mirror `accept`/`cancel`'s existing wrong-participant-403 / non-participant-404 tests onto `decline`, and add isolation tests proving `GET /friend-requests` and `GET /friends` never leak another user's rows. No production code changes.

### Changes Required:

#### 1. Decline authorization tests

**File**: `backend/tests/Functional/Controller/FriendshipControllerTest.php`

**Intent**: Add `testDeclineFriendRequestAsRequesterIsForbidden` and `testDeclineFriendRequestAsNonParticipantReturns404`, following the exact structure of `testAcceptFriendRequestAsRequesterIsForbidden` (lines 134-147) and `testAcceptFriendRequestAsNonParticipantReturns404` (lines 149-163) — same fixture-pair-selection discipline (must use a pair not already consumed elsewhere in the class), same two-request pattern (create via `POST /friend-requests`, then hit `/friend-requests/{id}/decline` as the wrong actor), same assertions (`HTTP_FORBIDDEN` for a wrong-side participant, `HTTP_NOT_FOUND` for a non-participant).

**Contract**: Two new test methods in the existing class; no new fixture pairs needed beyond picking currently-unused sender/addressee/third-party combinations from the six fixture users (admin, user_1..user_5).

#### 2. List isolation tests

**File**: `backend/tests/Functional/Controller/FriendshipControllerTest.php`

**Intent**: Add `testListPendingRequestsNeverIncludesAnotherUsersUnrelatedRequest` and `testListFriendsNeverIncludesAnotherUsersUnrelatedFriendship`, proving that when user A has a pending request/friendship with user B, a third user C's `GET /friend-requests`/`GET /friends` response does not contain that A↔B row.

**Contract**: Reuse an existing fixture pair not involving the querying user (e.g. assert from `user_2`'s perspective that the `admin`↔`user_1` accepted friendship and `user_1`→`user_5` pending request are absent from `user_2`'s `incoming`/`outgoing`/`friends` arrays). Assert both presence of the caller's own data (already covered) is unaffected and absence of the unrelated pair's `otherUser.email` in the response.

### Success Criteria:

#### Automated Verification:

- Backend PHPUnit suite passes: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit --filter FriendshipControllerTest`
- Full backend suite still green: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit`

#### Manual Verification:

- None — this phase is test-only, controller behavior is unchanged.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Risk #6 — Search Data-Boundary Hardening

### Overview

Fix the LIKE-wildcard scope-widening gap and add an explicit empty-search guard to `UserRepository`, with regression tests at both the repository and controller layers covering wildcard, blank, oversized, and SQL-metacharacter inputs.

### Critical Implementation Details

**LIKE-wildcard escaping**: Doctrine DQL supports an optional `ESCAPE` clause on `LIKE`. The fix must escape `$search`'s literal `%`/`_`/backslash characters via `$this->getEntityManager()->getConnection()->getDatabasePlatform()->escapeStringForLike($search, '\\')` *before* wrapping in the unescaped `%...%` delimiters, then declare `ESCAPE '\\'` in the DQL so the database treats the escaped characters literally:

```php
$platform = $this->getEntityManager()->getConnection()->getDatabasePlatform();
$escapedSearch = $platform->escapeStringForLike($search, '\\');
$qb->andWhere("u.email LIKE :search ESCAPE '\\\\'")
    ->setParameter('search', '%'.$escapedSearch.'%');
```

Apply identically in both `findWithPagination()` and `countWithFilters()` — they currently duplicate the same LIKE-building block.

**Empty-search guard scoping**: The new `allowEmptySearchResults` parameter must gate on `$search === ''` specifically, NOT on `null !== $search` as the current code does. `$search === null` must always remain unfiltered regardless of the flag — six existing repository tests and `Admin\UserController::list()`'s default no-search-term browse call it with implicit `null` and expect full/paginated results. Only the explicit-empty-string branch changes behavior.

### Changes Required:

#### 1. LIKE-wildcard escaping + empty-search guard

**File**: `backend/src/Repository/UserRepository.php`

**Intent**: Escape `%`/`_` before binding into the LIKE pattern (closes the enumeration gap), and add an `allowEmptySearchResults` flag so an explicit `search: ''` returns no results by default instead of silently falling through to "no filter."

**Contract**: `findWithPagination(int $page = 1, int $limit = 50, ?string $search = null, ?int $excludeGroupId = null, ?int $excludeUserId = null, bool $allowEmptySearchResults = false): array` and the matching signature on `countWithFilters()`. When `'' === $search && !$allowEmptySearchResults`, return `[]` (or `0` for `countWithFilters`) without building/running the query. When `$search` is a non-empty string, apply the escaped-LIKE change from Critical Implementation Details above. When `$search === null`, behavior is unchanged (no filter applied) regardless of the flag.

#### 2. Admin call-site update

**File**: `backend/src/Controller/Admin/UserController.php`

**Intent**: Preserve today's admin behavior of showing all users when `search` is explicitly sent as an empty string (not just when omitted), now that the repository defaults to blocking that case.

**Contract**: `UserController::list()`'s calls to `findWithPagination(...)` and `countWithFilters(...)` (lines 52-62) pass `allowEmptySearchResults: true`.

#### 3. Repository-level regression tests

**File**: `backend/tests/Functional/Repository/UserRepositoryTest.php`

**Intent**: Prove the wildcard fix and the empty-search guard, and update the two tests whose meaning changes under the new default.

**Contract**: Add `testFindWithPaginationSearchTreatsPercentAsLiteralCharacter` (search `%` matches only emails containing a literal `%`, i.e. none in current fixtures → asserts empty result, not all users), `testFindWithPaginationSearchTreatsUnderscoreAsLiteralCharacter` (same for `_`), `testFindWithPaginationSearchWithSqlMetacharactersReturnsNoMatchesWithoutError` (search a string containing `'`, `;`, `--` → asserts no exception, bounded/empty result), `testFindWithPaginationVeryLongSearchReturnsNoMatchesWithoutError` (search a string longer than 180 chars → asserts no exception, empty result). Update `testFindWithPaginationEmptySearchReturnsAll` (line 54) to pass `allowEmptySearchResults: true` and keep asserting all-returned; add `testFindWithPaginationEmptySearchWithoutFlagReturnsNoResults` asserting `search: ''` without the flag returns `[]`. Mirror both changes for `countWithFilters` (`testCountWithFiltersEmptySearchMatchesAll` at line 109 gets the flag; add `testCountWithFiltersEmptySearchWithoutFlagReturnsZero`).

#### 4. Controller-level regression tests

**File**: `backend/tests/Functional/Controller/UserControllerTest.php`

**Intent**: Prove the wildcard fix end-to-end through the public `/users` endpoint.

**Contract**: Add `testSearchWithPercentWildcardDoesNotReturnEveryUser` (GET `/users?search=%` → asserts the response does not contain all fixture users — either empty or bounded to literal matches) and `testSearchWithSqlMetacharactersReturnsSafeResult` (GET `/users?search=' OR '1'='1` → asserts `2xx` with an empty/bounded `data` array, not a 500).

### Success Criteria:

#### Automated Verification:

- Repository tests pass: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit --filter UserRepositoryTest`
- Controller tests pass: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit --filter UserControllerTest`
- Admin controller tests still pass (no regression from the call-site change): `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit --filter Admin`
- Full backend suite passes: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit`

#### Manual Verification:

- Manually hit `GET /users?search=%` with `X-Dev-User` header set to a fixture user and confirm the response is empty/bounded, not the full user list.
- Manually confirm `GET /admin/users` (no `search` param) still returns the full paginated user list as an admin.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- None planned — both risks are proven at the integration/functional level per the test-plan's Risk Response Guidance (Risk #1 explicitly requires controller-level proof, not voter-in-isolation; Risk #6 is a data-access-layer concern best proven against real Doctrine/Postgres behavior).

### Integration Tests:

- Phase 1: `FriendshipControllerTest` additions (decline 403/404, list isolation).
- Phase 2: `UserRepositoryTest` additions (wildcard, SQL-metacharacter, oversized, empty-search-flag behavior) and `UserControllerTest` additions (end-to-end wildcard and SQL-metacharacter safety).

### Manual Testing Steps:

1. As a fixture user, `POST /friend-requests/{id}/decline` on a request you're not the addressee of → expect 403.
2. As a non-participant, `POST /friend-requests/{id}/decline` → expect 404.
3. As one user, `GET /friend-requests` and `GET /friends` → confirm no unrelated pair's data appears.
4. `GET /users?search=%` as any authenticated non-admin user → confirm you do NOT get back every user.
5. `GET /admin/users` with no `search` param as an admin → confirm the full list still returns (guard didn't regress admin browsing).

## Performance Considerations

None — `escapeStringForLike` is a pure string transform, no added queries; the empty-search guard short-circuits before a query would run, which is strictly cheaper than today's behavior.

## Migration Notes

No schema or data migration. Pure application-code + test change; no deployment ordering constraints (repository and controller changes ship together in Phase 2).

## References

- Research: `context/changes/testing-authorization-data-boundary-hardening/research.md`
- Rollout tracking: `context/foundation/test-plan.md` §3 Phase 1
- Existing pattern for wrong-participant/non-participant tests: `backend/tests/Functional/Controller/FriendshipControllerTest.php:134-163` (accept), `:241-293` (cancel)
- Existing LIKE-search code to modify: `backend/src/Repository/UserRepository.php:56-63,101-108`
- Doctrine escape helper: `vendor/doctrine/dbal/src/Platforms/AbstractPlatform.php:2368`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Risk #1 — Authorization Test Coverage

#### Automated

- [x] 1.1 Backend PHPUnit suite passes (FriendshipControllerTest filter) — 907adf1
- [x] 1.2 Full backend suite still green — 907adf1

#### Manual

(none)

### Phase 2: Risk #6 — Search Data-Boundary Hardening

#### Automated

- [x] 2.1 Repository tests pass (UserRepositoryTest filter) — 9c6eb14
- [x] 2.2 Controller tests pass (UserControllerTest filter) — 9c6eb14
- [x] 2.3 Admin controller tests still pass (no regression from call-site change) — 9c6eb14
- [x] 2.4 Full backend suite passes — 9c6eb14

#### Manual

- [ ] 2.5 GET /users?search=% does not return every user
- [ ] 2.6 GET /admin/users with no search param still returns full list as admin
