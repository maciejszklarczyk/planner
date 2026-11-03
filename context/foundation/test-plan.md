# Test Plan

> Phased test rollout for this project. Strategy is frozen at the top
> (§1–§5); cookbook patterns at the bottom (§6) fill in as phases ship.
> Read before writing any new test.
>
> Refresh: re-run `/10x-test-plan --refresh` when stale (see §8).
>
> Last updated: 2026-11-03

## 1. Strategy

Tests follow three non-negotiable principles for this project:

1. **Cost × signal.** The cheapest test that gives a real signal for the
   risk wins. Do not promote to e2e because e2e "feels safer." Do not put a
   vision model on top of a deterministic visual diff that already catches
   the regression.
2. **User concerns are first-class evidence.** Risks anchored in "the team
   is worried about X, and the failure would surface somewhere in area Y"
   carry the same weight as PRD lines or hot-spot data.
3. **Risks are scenarios, not code locations.** This plan documents *what
   could fail* and *why we believe it's likely* — drawn from documents,
   interview, and codebase *signal* (churn, structure, test base). It does
   NOT claim to know which line owns the failure. That knowledge is
   produced by `/10x-research` during each rollout phase. If the plan and
   research disagree about where the failure lives, research is the
   ground truth.

Hot-spot scope used for likelihood weighting: `backend/src`, `frontend`
(excluding lockfiles, `.next/`, `context/`).

## 2. Risk Map

The top failure scenarios this project must protect against, ordered by
risk = impact × likelihood. Risks are failure scenarios in user / business
terms, not test names. The Source column cites the *evidence that surfaced
this risk* — never a specific file as "where the failure lives" (that is
research's job, see §1 principle #3).

| # | Risk (failure scenario) | Impact | Likelihood | Source (evidence — not anchor) |
|---|---|---|---|---|
| 1 | A user acts on or sees another user's friend-request/friend data (authorization bypass on accept/decline/cancel/list/search) | High | Medium | interview Q1 ("not leak data"); prd.md Access Control Changes; archive/2026-07-22-friendship-requests/plan.md's voter deliberately has no admin bypass, new code |
| 2 | Frontend shows friend/request state that doesn't match the database (stale cache after a mutation) | High | Medium | interview Q1 ("front shows different data than persisted"); hot-spot dir `frontend/hooks` (36 commits/30d); archive plan.md Phase 6 admits an imprecise "invalidate everything plausibly affected" pattern |
| 3 | A mutation reports success in the UI but the persisted state didn't change, or a specific backend error is swallowed into a generic message | High | Medium | interview Q2 ("UI showed success but nothing happened"); archive plan.md Phase 5 — first instance in this codebase of code-specific error mapping, unmapped codes silently fall back |
| 4 | The self-request/duplicate/crossed-request-auto-accept/cooldown check ordering regresses, producing an invalid friendship state | High | Low-Medium | interview Q2 ("malformed input reached DB, flow failed"); archive plan.md Critical Implementation Details — explicit 4-step ordering; DB constraints are only a partial backstop |
| 5 | `FriendsView.tsx` interactions (accept/decline/cancel/send-dialog) break with no automated regression signal | Medium | Medium-High | interview Q4 (explicit, user-stated); archive plan.md Testing Strategy — "no frontend component-level test in this plan... covered only by manual verification"; hot-spot dirs `frontend/components/friends` (5 commits/30d), `frontend/hooks` (36 commits/30d) |
| 6 | `GET /users?search=` mishandles adversarial or malformed input — specifically LIKE-wildcard injection (`%`/`_` unescaped before binding), turning a partial-match search into full-user-enumeration; SQL injection itself is ruled out (Doctrine `setParameter` fully parameterizes) | Medium | Low | prd.md target_scale (small/low-qps) plus a new untrusted-input surface; archive plan.md Phase 4 — raw `search` param feeds a `LIKE` match; confirmed via research.md (2026-08-08, testing-authorization-data-boundary-hardening) — `UserRepository::findWithPagination` binds `search` via `setParameter` (SQL-injection-safe) but never escapes `%`/`_`, so `search=%` matches every row |

**Impact × Likelihood rubric.**

| Rating | Impact | Likelihood |
|--------|--------|------------|
| High   | user loses access, data, or money; failure is publicly visible | area changes weekly, or we have already been burned here |
| Medium | feature degrades, a workaround exists, only some users affected | touched occasionally, has been a source of bugs |
| Low    | cosmetic, easily reverted, no data effect | stable code, rarely touched |

Risks #1 and #6 are the abuse/security lens rows (authorization/access and
untrusted input respectively) — the Friendship domain accepts user input
and gates access by ownership, so both surfaces are in scope per §2's
mandatory abuse lens.

### Risk Response Guidance

| Risk | What would prove protection | Must challenge | Context `/10x-research` must ground | Likely cheapest layer | Anti-pattern to avoid |
|------|-----------------------------|----------------|--------------------------------------|-----------------------|-----------------------|
| #1 | A non-participant (not requester/addressee) gets 403 on accept/decline/cancel; search/list never returns another user's restricted fields | "Voter test passing = authorization correct" — must be proven against the actual controller wiring, not the voter class in isolation | Voter attribute/subject wiring, controller `#[IsGranted]` placement, response DTO field trimming | integration (functional, controller-level) | Testing the voter class alone without a controller-level 403 assertion |
| #2 | After each mutation, a subsequent read (fresh query, not cache) matches the mutation's effect | "Green toast = state updated" — must assert against real query invalidation/refetch, not a mocked cache | React Query key structure; which keys each mutation invalidates | integration (hook test with real `QueryClientProvider`, mocked `api`) | Asserting only that `api.post` was called, not that dependent queries actually refetch/update |
| #3 | Every mapped error code surfaces its specific message; an unmapped code visibly falls back instead of hiding the failure | "The mutation call succeeded" ≠ "the business action succeeded" — must assert the effect, sourced from the PRD/API contract, not from the hook's own logic | The seven Friendship error codes (from PRD/plan, not implementation); envelope shape `{error, message}` | unit (pure error-mapping helper) + integration (hook error path) | Oracle problem: asserting the toast text matches whatever the hook currently renders, instead of the PRD's intended message per code |
| #4 | Self-request, same-direction duplicate, crossed-request auto-accept, and cooldown-active/expired each land in the correct final state, in combination (two rules on the same pair), and a rejected send leaves the pair's persisted state unchanged | "DB unique index prevents bad states" — the index only catches a second active row per pair, not cooldown, direction, or auto-accept-vs-reject logic. Also: "each rule passes in isolation ⇒ the chain is correct" — ordering regressions only show up when rules overlap on one pair | Exact check ordering (self → active-row → cooldown → create) and declined/cancelled-as-history semantics — the oracle is the archived friendship-requests plan's Critical Implementation Details plus PRD FR-006 (the PRD alone states only the cooldown rule); cancel-vs-declined distinction; injected cooldown length | integration (service-level, DB-backed, injected mock clock) — not pure unit with mocked repositories, because part of the rule logic lives in repository queries (direction filter, latest-declined ordering) | Copying assertions from the service implementation itself rather than from the stated business rules; mocking repositories so query-level rule logic is never exercised |
| #5 | Clicking Accept/Decline/Cancel/Send in a rendered `FriendsView` produces the right API call and UI update | "Hook tests already cover this" — hook tests mock `api`, they don't exercise the component's event wiring, loading states, or the send-dialog autocomplete flow | Component's button→hook wiring; dialog open/close/select flow; disabled-state behavior during `isPending` | integration (RTL component render + interaction) | Snapshot-testing the rendered tree instead of asserting behavior on interaction |
| #6 | Adversarial `search` strings (`%`, `_`, SQL metacharacters, very long strings, empty) return safe, bounded, correctly-scoped results | "Doctrine ORM = automatically safe" is only true for SQL injection (confirmed: `setParameter` is used throughout). The real risk is LIKE-wildcard scope-widening — `%`/`_` are never escaped before being wrapped in `%...%`, so `search=%` returns every user. Must prove either the wildcard is escaped, or the exposure is bounded/accepted and regression-tested as-is. Also: blank-search safety is enforced only in the controller (`UserController.php`), not in `UserRepository::findWithPagination` itself — a future non-admin caller of the repository method could reintroduce full-table exposure | `UserRepository::findWithPagination`/`countWithFilters` LIKE-binding code; `UserController::search` blank-guard; `excludeUserId` interaction | unit/integration (repository or controller test with adversarial inputs) | Testing only the happy-path partial-email match, skipping empty/oversized/wildcard/special-character inputs |

## 3. Phased Rollout

Each row is a discrete rollout phase that will open its own change folder
via `/10x-new`. Status moves left-to-right through the values below; the
orchestrator updates Status as artifacts appear on disk.

| # | Phase name | Goal (one line) | Risks covered | Test types | Status | Change folder |
|---|---|---|---|---|---|---|
| 1 | Authorization & data-boundary hardening | Prove no user can act on or see another user's friend data | #1, #6 | integration (functional controller tests) | complete | context/archive/2026-08-08-testing-authorization-data-boundary-hardening/ |
| 2 | Business-rule correctness | Prove the request lifecycle (self/duplicate/crossed/cooldown) holds in combination, not just isolation | #4 | unit + integration (service-level) | planned | context/changes/testing-business-rule-correctness/ |
| 3 | Frontend state-integrity | Prove UI reflects real DB state after every mutation, and errors surface specifically | #2, #3 | integration (hook tests w/ real QueryClientProvider) | not started | — |
| 4 | Component-level coverage for FriendsView | Prove accept/decline/cancel/send-dialog interactions work end-to-end in the rendered component | #5 | integration (RTL render+interact) | not started | — |

**Status vocabulary** (fixed — parser literals):

| Value          | Meaning                                                                          |
|----------------|------------------------------------------------------------------------------------|
| `not started`  | No change folder for this rollout phase yet.                                     |
| `change opened` | `context/changes/<id>/` exists with `change.md`; research not done.            |
| `researched`   | `research.md` exists in the change folder.                                       |
| `planned`      | `plan.md` exists with a `## Progress` section.                                   |
| `implementing` | Progress section has at least one `[x]` and at least one `[ ]`.                  |
| `complete`     | Progress section is fully `[x]`.                                                 |

## 4. Stack

| Layer               | Tool                        | Version | Notes                                          |
|----------------------|-----------------------------|---------|-------------------------------------------------|
| backend unit + integration | PHPUnit                | ^12.5.17 | `backend/phpunit.dist.xml`; 23 existing test files across Controller/Repository/Service/Security/Unit — meaningful base. Tests (local + CI) run on **SQLite**: `DatabaseTestCase` builds the schema from the ORM mapping, so DB invariants that Postgres migrations add via raw SQL are absent unless mirrored there (the active friend-request pair index is; the self-request CHECK is not) |
| frontend unit + integration | Vitest + React Testing Library | vitest ^4.1.10, RTL ^16.3.2 | `frontend/vitest.config.ts`; only 5 test files today, all hook-level — sparse, zero component `render()` tests |
| e2e                  | none yet                    | —       | see Phase 4 note below — Playwright MCP available for manual/exploratory use, not wired as a suite |
| accessibility        | none yet                    | —       | not requested by PRD/interview; no rollout phase currently targets it |
| (optional) AI-native | Playwright MCP — checked: 2026-08-08 | n/a | only relevant if Phase 4's RTL coverage proves insufficient for the send-dialog's real-browser autocomplete behavior; do not reach for it before RTL is tried |

**Stack grounding tools (current session):**
- Docs: Context7 — available, not queried; no ambiguous framework-version question arose during discovery; checked: 2026-08-08
- Search: Exa.ai — available, not queried; checked: 2026-08-08
- Runtime/browser: Playwright MCP — available; possible use for Phase 4 if RTL coverage proves insufficient; not used yet; checked: 2026-08-08
- Provider/platform: none detected in this session; checked: 2026-08-08

## 5. Quality Gates

| Gate                          | Where             | Required?                   | Catches                                       |
|-------------------------------|-------------------|------------------------------|-----------------------------------------------|
| backend PHPUnit suite         | local + CI         | required                     | logic/authorization regressions (backend)     |
| frontend Vitest suite         | local + CI         | required                     | logic regressions (frontend)                  |
| lint + typecheck (frontend)   | local + CI         | required                     | syntactic / type drift                        |
| authorization integration tests | local + CI       | required after §3 Phase 1    | IDOR / access-control regressions             |
| business-rule ordering tests  | local + CI         | required after §3 Phase 2    | invalid friendship states                     |
| frontend state-integrity tests | local + CI        | required after §3 Phase 3    | stale UI / silent-no-op regressions           |
| component-level RTL tests     | local + CI         | required after §3 Phase 4    | broken FriendsView interactions               |

## 6. Cookbook Patterns

How to add new tests in this project. Each sub-section is filled in once
the relevant rollout phase ships; before that, the sub-section reads
"TBD — see §3 Phase N."

### 6.1 Adding a backend unit/integration test

- **Default layer**: integration, DB-backed. Avoid pure unit tests with mocked
  repositories when the rule logic lives partly in queries — they mirror the
  implementation.
- **Authorization / data boundary** (rollout Phase 1): functional controller
  test with `X-Dev-User`; assert 403 for a participant acting in the wrong role
  and 404 for a non-participant (no existence leak); assert list endpoints never
  return another user's rows. Reference: `backend/tests/Functional/Controller/FriendshipControllerTest.php`.
- **Business-rule chain** (rollout Phase 2): service-level test using
  `FriendshipServiceTestTrait` (clean `friend_request` table, `service(now, cooldownDays)`
  with `MockClock`, `persistedRows()`). Put two rules on the same pair; take the
  expected outcome from the business rules (PRD / archived plan), not from the
  service; after every rejected call assert the persisted rows are unchanged;
  use fixed whole-second times (the DB drops microseconds). Reference:
  `backend/tests/Functional/Service/FriendshipSendRuleChainTest.php`.
- **Concurrency**: simulate the race window with a repository double whose
  lookup misses the competing row once, and let the real DB unique index reject
  the insert. Check the index exists in the SQLite test schema first.
- **Fixtures and shared state**: the test DB is created once per run and every
  test commits. Restore anything you mutate outside the per-test reset (e.g. a
  user's status). Controller tests do not reset `friend_request` — use an unused
  user pair (`friend1`…`friend8@example.com` are reserved for this). Friend-request
  addressees must be `active` or `new`.
- **Prove the test protects something**: temporarily break the rule (reorder a
  check, flip an `ORDER BY`), confirm the test goes red, restore.
- **Run locally**: `docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit`.

### 6.2 Adding a frontend hook/integration test

- TBD — see §3 Phase 3 for the state-integrity (query invalidation)
  pattern.
- **Reference test today**: `frontend/hooks/useSendFriendRequest.test.ts`.
- **Run locally**: `cd frontend && npm run test`.

### 6.3 Adding a frontend component test

- TBD — see §3 Phase 4 for the FriendsView RTL interaction pattern.

### 6.4 Adding a test for a new API endpoint

- **Test type**: integration (functional, PHPUnit), preferred.
- **Reference test today**: `backend/tests/Functional/Controller/FriendshipControllerTest.php`.
- **When to add e2e instead**: only if the endpoint's failure mode requires
  the full deployed shape (auth + session + handler crossing) — no e2e
  suite exists yet; see §4.

### 6.5 Adding a test for adversarial/untrusted input

- **Test type**: repository test plus a functional controller test for the
  same input (rollout Phase 1).
- **Inputs to cover**: LIKE wildcards (`%`, `_`), SQL metacharacters, a very
  long string (300+ chars), empty/blank. Assert a bounded, correctly-scoped
  result — not just "no exception".
- **Existence leaks**: when an endpoint must not reveal whether an account
  exists or its status, compare the response with the one for an unknown value
  (same code, same message modulo the input).
- **Reference tests**: `backend/tests/Functional/Repository/UserRepositoryTest.php`,
  `backend/tests/Functional/Controller/UserControllerTest.php`,
  `FriendshipControllerTest::testSendFriendRequestToBlockedUserReturnsSameNotFoundAsUnknownEmail`.

### 6.6 Per-rollout-phase notes

(Appended by each rollout phase's final sub-phase after it ships.)

- **Phase 2 — Business-rule correctness** (2026-11-03,
  `context/changes/testing-business-rule-correctness/`): overlap cases C1–C9
  and three race cases for the friend-request send chain. Side effects shipped
  with it: sends to `inactive`/`blocked`/`deleted` users are rejected as
  `USER_NOT_FOUND`; racing crossed sends now end as friends (rule chain retried
  once after a unique-index conflict); the SQLite test schema mirrors
  `uniq_friend_request_active_pair`.

## 7. What We Deliberately Don't Test

Exclusions agreed during the rollout (Phase 2 interview, Q5). Future
contributors should respect these unless the underlying assumption changes.

- **shadcn/ui primitives in `components/ui/`** — not owned by this project;
  `frontend/CLAUDE.md` already forbids modifying them. Re-evaluate only if
  the project forks/customizes a primitive beyond styling. (Source: Phase 2
  interview Q5.)

## 8. Freshness Ledger

- Strategy (§1–§5) last reviewed: 2026-08-08
- Stack versions last verified: 2026-08-08
- AI-native tool references last verified: 2026-08-08

Refresh (`/10x-test-plan --refresh`) when:

- a new top-3 risk surfaces from the roadmap or archive,
- a recommended tool's `checked:` date is older than three months,
- the project's tech stack changes (new framework, new test runner),
- §7 negative-space no longer matches what the team believes.
