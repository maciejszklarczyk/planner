# Authorization & Data-Boundary Hardening — Plan Brief

> Full plan: `context/changes/testing-authorization-data-boundary-hardening/plan.md`
> Research: `context/changes/testing-authorization-data-boundary-hardening/research.md`

## What & Why

Rollout Phase 1 of the project's test plan (`context/foundation/test-plan.md`) targets two risks: a user acting on or seeing another user's friend data (Risk #1), and `GET /users?search=` mishandling adversarial input (Risk #6). Research found Risk #1 is mostly already covered and Risk #6 has one real, unmitigated gap — LIKE-wildcard scope-widening lets any authenticated non-admin user enumerate the entire user table via `search=%`.

## Starting Point

`FriendshipVoter` + a two-stage controller gate already correctly return 403/404 for `accept`/`cancel`, tested at the controller level — this was itself the fix for a prior implementation-review finding. `decline` shares the same code path but is untested for the negative cases. `UserRepository`'s search binds `$search` via Doctrine `setParameter` (SQL-injection-safe) but never escapes `%`/`_` before wrapping it in a LIKE pattern, and its blank-search safety currently lives only in the controller, not the repository.

## Desired End State

`decline` has the same 403/404 coverage as `accept`/`cancel`. `GET /friend-requests`/`GET /friends` are proven to never leak another user's rows. `search=%` (or `_`) matches only a literal `%`/`_` character instead of every row. An explicit `search: ''` is blocked by default at the repository layer too, not just the controller, with admin's "browse all" flow preserved via an explicit opt-in flag.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
|---|---|---|---|
| Fix vs. accept the wildcard gap | Fix by escaping `%`/`_` via Doctrine's `escapeStringForLike` | Closes a real user-enumeration/email-harvesting primitive, not just a theoretical risk | Plan (user, round 1) |
| Blank-search guard placement | Harden the repository too, via an explicit `allowEmptySearchResults` flag | Removes a fragile "depends on every caller remembering" invariant that only the controller currently enforces | Plan (user, round 1) |
| Oversized-input handling | Test only, no length cap added | Not exploitable given the 180-char email column and cheap LIKE scan — a cap would be scope creep | Plan (user, round 1) |
| New Risk #6 test layer | Both controller and repository | Matches the existing test suite's split and closes gaps research flagged at both boundaries | Plan (user, round 1) |
| Guard flag semantics for Admin | Explicit opt-in (`allowEmptySearchResults: true`) on Admin's call site | Default-safe repository behavior, admin explicitly asks for the "show all" behavior it already relies on | Plan (user, round 2) |
| Guard scope: `null` vs `''` | Guard triggers only on explicit empty string, never on `null` | A blanket guard on `null` would break 6 existing repository tests and Admin's default no-search-term browse — found during implementation-detail research, not asked as a question | Plan (research during Step 2) |
| Priority if time is tight | All 4 items are must-have | Phase 1 is scoped tightly enough (2 risks, integration tests only) that nothing found is separable from the stated goal | Plan (user, round 2) |

## Scope

**In scope:** Decline 403/404 tests; friend-list/request-list isolation tests; LIKE-wildcard escaping fix; repository-level empty-search guard + Admin call-site update; regression tests for wildcard/blank/oversized/SQL-metacharacter search input at both controller and repository layers.

**Out of scope:** Length validation/cap on `search` (not exploitable); the `UserListItemDto` vs `UserSearchResultDto` roles/status field-exposure inconsistency (product-policy question, not a bug); a `FriendshipVoter` unit test in isolation (the test-plan explicitly requires controller-level proof).

## Architecture / Approach

Phase 1 is pure test-addition against existing, correct production code (Risk #1). Phase 2 makes two small, targeted changes inside `UserRepository` (LIKE-escaping in the query-building code, an opt-in flag gating the empty-string case) plus a one-line call-site update in `Admin\UserController`, backed by regression tests proving the fix at both the data-access layer and the public HTTP boundary.

## Phases at a Glance

| Phase | What it delivers | Key risk |
|---|---|---|
| 1. Risk #1 — Authorization test coverage | Decline 403/404 tests + list-isolation tests | Low — test-only, zero production-code change |
| 2. Risk #6 — Search data-boundary hardening | Wildcard-escaping fix + empty-search guard + regression tests | Medium — production-code change to a shared repository method used by both the public search and admin list endpoints; must not regress Admin's default browse behavior |

**Prerequisites:** None beyond the existing test suite and local Docker Compose stack.
**Estimated effort:** ~1 session across 2 phases.

## Open Risks & Assumptions

- Assumes Doctrine DQL's `ESCAPE` clause behaves consistently across the project's Postgres 16 setup — should be confirmed by the new repository-level wildcard test actually passing, not just by static code review.
- The `UserListItemDto`/`UserSearchResultDto` field-exposure inconsistency remains unresolved; flagged in research but deliberately out of scope here.

## Success Criteria (Summary)

- `decline`, like `accept`/`cancel`, provably 403s a wrong-side participant and 404s a non-participant at the controller level.
- No user can retrieve another unrelated user's friend-request/friend-list rows.
- `search=%` (or `_`) no longer returns every user; SQL-metacharacter and oversized search input return safe, bounded results without error.
- Admin's default "browse all users" flow is unaffected by the new guard.
