# Business-Rule Correctness (Test Rollout Phase 2) — Plan Brief

> Full plan: `context/changes/testing-business-rule-correctness/plan.md`
> Research: `context/changes/testing-business-rule-correctness/research.md`

## What & Why

Risk #4 of the test plan: the friend-request rules (self, duplicate, crossed
auto-accept, cooldown) only work because they run in a fixed order, and no
test checks two rules on the same pair. While grounding it we found three
related gaps: requests can be sent to inactive/blocked/deleted users, two
crossed sends at the same moment end in an error instead of a friendship, and
the SQLite test DB lacks the unique index production relies on.

## Starting Point

All rules live in `FriendshipService::sendRequest()` plus two repository
queries. Tests cover each rule in isolation and assert only exceptions. Tests
and CI run on SQLite built from the ORM mapping, so the Postgres-only index and
CHECK from the migration are absent there.

## Desired End State

Sending to an unreachable user returns the same 404 as an unknown email.
Search and send share one list of unreachable statuses. Racing crossed sends
become a friendship. A dedicated test class pins every rule overlap (C1–C9)
and the race, and breaking the rule order makes those tests fail.

## Key Decisions Made

| Decision | Choice | Why | Source |
|---|---|---|---|
| Unreachable addressee | Reject in this phase | Search already hides them; sending should match | Research (user decision) |
| Error code | `USER_NOT_FOUND` 404, same message | Doesn't reveal account status; frontend already maps it | Plan |
| Blocked statuses | inactive, blocked, deleted | Same set as search | Plan |
| Crossed request from now-blocked user | Rejected (status check before active-row) | No friendship with blocked accounts | Plan |
| Race fix | Retry the rule chain once after a unique-index conflict | Works on any DB and is testable on SQLite | Plan |
| Test DB | Add SQLite copy of the active-pair index | Tests see the production backstop | Plan |
| HTTP coverage | Status rule, C1, C4 | Signal where users see it, no full duplication | Plan |
| Test layout | New `FriendshipSendRuleChainTest` + shared harness | Overlaps separated from single-rule tests | Plan |
| Test oracle | Archived plan rules + PRD FR-006 | Avoid copying the implementation | Research |

## Scope

**In scope:** SQLite index + fixture users; status rule + shared enum helper;
re-pointed existing tests; overlap tests C1–C9; retry-on-conflict + race tests;
HTTP tests for status/C1/C4; test-plan §6, backend/CLAUDE.md and research
correction.

**Out of scope:** existing relations with users blocked later; requester's own
status; Postgres in tests/CI; SQLite CHECK constraint; locks; frontend.

## Architecture / Approach

Tests before refactor. Phase 1 makes the test DB and fixtures able to express
the rules; Phase 2 adds the status rule; Phase 3 pins the chain with overlap
tests; Phase 4 restructures `sendRequest` into a rule-chain method that is
retried once on a fresh EntityManager after a unique-index conflict, with the
race simulated by a repository double; Phase 5 documents the patterns.

## Phases at a Glance

| Phase | What it delivers | Key risk |
|---|---|---|
| 1. Test-DB parity + fixtures | SQLite index, active fixture users | Index breaks a test that relied on duplicate rows |
| 2. Unreachable users | Status rule, shared enum, re-pointed tests | Missing a test that targets user2/3/4 |
| 3. Overlap tests | C1–C9 + HTTP C1/C4 | Assertions mirroring the code instead of the rules |
| 4. Race retry | Retry on conflict + race tests | Stale entities after EntityManager reset |
| 5. Docs | Cookbook §6, CLAUDE.md, research fix | — |

**Prerequisites:** backend containers running; no pending changes in `FriendshipService`.
**Estimated effort:** ~2–3 sessions across 5 phases.

## Open Risks & Assumptions

- Assumes DoctrineBundle's `resetManager()` leaves repositories obtained from the registry usable; Phase 4 re-acquires them from the fresh manager to avoid relying on it.
- Existing pending requests from users blocked later can still be accepted — explicitly out of scope.
- The SQLite index is a hand-maintained copy; drift from the Postgres migration is possible (documented in CLAUDE.md).

## Success Criteria (Summary)

- Full backend suite green, including the new overlap and race tests.
- Breaking the rule order, the latest-decline ordering, or the retry makes a named test fail.
- A request to a blocked user is indistinguishable from one to an unknown email.
