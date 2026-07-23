# Friendship Requests — Plan Brief

> Full plan: `context/changes/friendship-requests/plan.md`
> Research: `context/changes/friendship-requests/research.md`
> Depends on: `context/changes/api-exception-handling/` (must land first)

## What & Why

Roadmap slice S-01: users can send a friend request by email, accept/decline it, and see a friends list — the prerequisite for S-02's friend-gated event invitations.

## Starting Point

No Friendship domain exists today — no entity, controller, or tests. The Group↔User relation (`UserHasGroup`) and the invitation-token flow (`UserInvitationToken`) are the closest existing analogues. This plan assumes `context/changes/api-exception-handling/` has already landed, providing `ApiExceptionInterface` and the project-wide `kernel.exception` listener that every exception here plugs into.

## Desired End State

Users can send/accept/decline friend requests and view their friends list, with self-request, duplicate, already-friends, crossed-request (auto-accept), and 3-day cooldown-after-decline all enforced server-side.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
|---|---|---|---|
| Crossed requests (A→B pending, B tries B→A) | Auto-accept, no new row | User's explicit choice over the recommended "reject as duplicate" | Plan (user Q&A) |
| Request lifecycle data model | New row per send attempt (history preserved) | User's explicit choice over "single row mutated in place" — preserves decline history for cooldown math | Plan (user Q&A) |
| Pending-requests visibility | Add `GET /friend-requests` (incoming+outgoing) | Without it, FR-006 (accept/decline) has no way to discover a request id to act on | Plan (user Q&A) |
| Cooldown testing | Inject `ClockInterface`, use `MockClock` in tests | Deterministic, fast tests without waiting 3 real days; standard Symfony 7 component | Plan (user Q&A) |
| Unfriending | Explicitly out of scope, tracked as MVP follow-up | Not requested by any PRD FR; user asked it be noted, not built now | Plan (user Q&A) — see roadmap `## Parked` |
| Friendship voter admin bypass | None (unlike `GroupVoter`) | Friendship is personal, not admin-manageable — PRD gives `ROLE_ADMIN` no capability over it | Plan (research-grounded) |
| Error handling | Reuse `ApiExceptionInterface` from a prerequisite change | Split out to `api-exception-handling` (Foundation F-01) since it turned out to be cross-cutting, unrelated to Friendship | Roadmap |

## Scope

**In scope:**
- Friendship entity/migration/repository/service/controller/voter/DTOs
- Send/accept/decline/list-friends/list-pending endpoints, cooldown, crossed-request auto-accept
- Full functional test coverage including a `MockClock`-based cooldown test

**Out of scope:**
- Unfriending (tracked as a follow-up in the roadmap's Parked section, not built)
- Rate-limiting how many requests a user can send (PRD: no limit in MVP)
- Group self-service, Event ownership/invites (S-02, blocked on this slice)
- Building the exception-handling infrastructure itself (`api-exception-handling`, a prerequisite)

## Architecture / Approach

Friendship mirrors the existing `UserHasGroup`/`GroupMembershipService`/`GroupVoter` pattern closely, with a partial unique DB index (`WHERE status IN ('pending','accepted')`) making the crossed-request/duplicate logic race-safe — a guarantee the codebase's one existing analogous relation (`user_has_group`) never had. Built bottom-up: data model (Phase 1) → service layer business rules (Phase 2) → HTTP layer (Phase 3).

## Phases at a Glance

| Phase | What it delivers | Key risk |
|---|---|---|
| 1. Friendship Data Model | Entity, migration (partial unique index), repository, fixtures | Getting the partial-unique-index semantics right (must allow unlimited declined history, block only active pairs) |
| 2. Friendship Service Layer | Business rules (self/duplicate/crossed/cooldown), Clock-injected | Check-ordering bug (e.g. cooldown checked before active-row check) would misfire |
| 3. Friendship HTTP Layer & Tests | Controller, voter, DTOs, full functional coverage | `MockClock` container override must be wired correctly for the cooldown-expiry test |

**Prerequisites:** `context/changes/api-exception-handling/` must be implemented and its tests green first — this plan's Phase 2 exceptions implement `ApiExceptionInterface` from that change.
**Estimated effort:** ~3 sessions across 3 phases, fits within the PRD's 3-week after-hours budget (alongside `api-exception-handling`'s own ~2 sessions).

## Open Risks & Assumptions

- Assumes `symfony/clock` is safely addable — it's a near-certain transitive dependency already, but Phase 2 declares it explicitly rather than relying on that assumption at runtime.
- Assumes `api-exception-handling` ships `ApiExceptionInterface` with exactly the `getErrorCode(): string` / `getStatusCode(): int` contract this plan expects — verify against that change's actual implementation before starting Phase 2.

## Success Criteria (Summary)

- A user with no prior relation to another user can send, and that user can accept, a friend request, after which both see each other on `/friends`.
- Self-requests, duplicate requests, already-friends, and cooldown-active resends are all rejected with clear, machine-readable error codes.
