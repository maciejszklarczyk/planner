# Friendship Requests — Plan Brief

> Full plan: `context/changes/friendship-requests/plan.md`
> Research: `context/changes/friendship-requests/research.md`
> Depends on: `backend/context/archive/2026-07-22-api-exception-handling/` (must land first)

## What & Why

Roadmap slice S-01: users can search for and send a friend request to another user, accept/decline/cancel it, and see a friends list — the prerequisite for S-02's friend-gated event invitations. Promoted from backend-only to full-stack (see `context/foundation/roadmap.md`'s Baseline note): the frontend already has a fully-built but entirely mocked Friends UI that this plan wires to the real backend.

## Starting Point

No Friendship domain exists on the backend today — no entity, controller, or tests. The Group↔User relation (`UserHasGroup`) and the invitation-token flow (`UserInvitationToken`) are the closest existing backend analogues. On the frontend, `components/friends/FriendsView.tsx` (560 lines) already has the full UI — tabs, cards, search box, Accept/Decline/Cancel/Add buttons — driven entirely by hardcoded `MOCK_*` arrays with no `onClick` handlers, plus an already-wired sidebar nav entry. This plan assumes `backend/context/archive/2026-07-22-api-exception-handling/` has already landed, providing `ApiExceptionInterface` and the project-wide `kernel.exception` listener that every backend exception here plugs into.

## Desired End State

Users can search for another user, send/accept/decline/cancel friend requests, and view their friends list — all enforced server-side (self-request, duplicate, already-friends, crossed-request auto-accept, 3-day cooldown-after-decline) — and see it all working in the browser at `/friends`, with code-specific error messages instead of a generic "something went wrong."

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
|---|---|---|---|
| Crossed requests (A→B pending, B tries B→A) | Auto-accept, no new row | User's explicit choice over the recommended "reject as duplicate" | Plan (user Q&A) |
| Request lifecycle data model | New row per send attempt (history preserved) | User's explicit choice over "single row mutated in place" — preserves decline history for cooldown math | Plan (user Q&A) |
| Pending-requests visibility | Add `GET /friend-requests` (incoming+outgoing) | Without it, FR-006 (accept/decline) has no way to discover a request id to act on | Plan (user Q&A) |
| Cooldown testing | Inject `ClockInterface`, use `MockClock` in tests | Deterministic, fast tests without waiting 3 real days; standard Symfony 7 component | Plan (user Q&A) |
| Unfriending | Explicitly out of scope, tracked as MVP follow-up; mock UI's button rendered disabled | Not requested by any PRD FR; user asked it be noted, not built now | Plan (user Q&A) — see roadmap `## Parked` |
| Friendship voter admin bypass | None (unlike `GroupVoter`) | Friendship is personal, not admin-manageable — PRD gives `ROLE_ADMIN` no capability over it | Plan (research-grounded) |
| Error handling | Reuse `ApiExceptionInterface` from a prerequisite change | Split out to `api-exception-handling` (Foundation F-01) since it turned out to be cross-cutting, unrelated to Friendship | Roadmap |
| "Suggestions" tab (mock UI) | Removed from UI entirely | No backend data source, no FR requests it | Plan (user Q&A) |
| "Send message" action (mock UI) | Kept, rendered disabled with tooltip | No messaging feature exists anywhere in the app, not in any PRD/roadmap item | Plan (user Q&A) |
| Cancel a sent (still-pending) request | Added as a new backend endpoint + `CANCELLED` status | Mock UI already had a "Cofnij" button; user chose to build it rather than disable it, kept distinct from `DECLINED` so it doesn't trigger the decline cooldown | Plan (user Q&A) |
| User search for "send request" | New non-admin `GET /users` endpoint (autocomplete), not a raw-email dialog | User chose richer UX over the simpler `InviteUserDialog`-style email field | Plan (user Q&A) |
| Search endpoint home | New action on the existing non-admin `UserController`, not a modified `Admin\UserController` | User's explicit call — keeps the admin controller fully admin-only, matches this codebase's existing Admin/ vs root Controller/ separation | Plan (user Q&A) |
| Search result fields for non-admins | Trimmed DTO (id/name/email/avatar only) | User's explicit call — avoids leaking `roles`/`status` of every user to any authenticated member | Plan (user Q&A) |
| Frontend error UX | Per-code Polish message via a shared `getApiErrorMessage()` helper | User's explicit call over the existing generic-toast convention — Friendship has 7 distinct, user-meaningful error codes | Plan (user Q&A) |
| Frontend test depth | Hook tests only, no `FriendsView` component test | User's explicit call — matches this codebase's one existing test pattern, keeps scope bounded | Plan (user Q&A) |

## Scope

**In scope:**
- Friendship entity/migration/repository/service/controller/voter/DTOs, including cancel
- Send/accept/decline/cancel/list-friends/list-pending endpoints, cooldown, crossed-request auto-accept
- New non-admin user-search endpoint (`GET /users`) with a trimmed response shape
- Full backend functional test coverage including a `MockClock`-based cooldown test
- Frontend: types, hooks, error-message mapping, hook tests, and wiring the existing mock UI to all of the above

**Out of scope:**
- Unfriending (tracked as a follow-up in the roadmap's Parked section, not built; mock UI button disabled)
- In-app messaging (mock UI button disabled — no such feature exists anywhere in this app)
- Friend suggestions (mock UI tab removed — no backend data source)
- Rate-limiting how many requests a user can send (PRD: no limit in MVP)
- Group self-service, Event ownership/invites (S-02, blocked on this slice)
- Building the exception-handling infrastructure itself (`api-exception-handling`, a prerequisite)
- Modifying the existing admin `/admin/users` endpoint, access control, or DTO
- Frontend component-level (`render()`) tests

## Architecture / Approach

Backend: Friendship mirrors the existing `UserHasGroup`/`GroupMembershipService`/`GroupVoter` pattern closely, with a partial unique DB index (`WHERE status IN ('pending','accepted')`) making the crossed-request/duplicate logic race-safe — a guarantee the codebase's one existing analogous relation (`user_has_group`) never had. A new `CANCELLED` status (distinct from `DECLINED`) lets the requester withdraw without triggering the decline cooldown. Frontend: no new design work — the UI shell already exists — just a data layer (types/hooks/error-mapping) wired into the existing component, following this codebase's established React Query + shadcn/ui conventions throughout.

## Phases at a Glance

| Phase | What it delivers | Key risk |
|---|---|---|
| 1. Friendship Data Model | Entity (incl. `CANCELLED` status), migration (partial unique index), repository, fixtures | Getting the partial-unique-index semantics right (must allow unlimited declined/cancelled history, block only active pairs) |
| 2. Friendship Service Layer | Business rules (self/duplicate/crossed/cooldown/cancel), Clock-injected | Check-ordering bug, or cancel accidentally triggering the cooldown |
| 3. Friendship HTTP Layer & Tests | Controller, voter (incl. cancel), DTOs, full functional coverage | `MockClock` container override must be wired correctly for the cooldown-expiry test |
| 4. User Search Endpoint | New `GET /users` on the non-admin `UserController`, trimmed DTO | Accidentally leaking `roles`/`status` to non-admins, or breaking the existing admin search |
| 5. Friendship Data Layer (Frontend) | Types, hooks, `getApiErrorMessage()` helper, hook tests | First-ever error-code-branching pattern in this codebase — must degrade gracefully (fallback message) for unmapped codes |
| 6. Friendship UI Wiring | Real data in `FriendsView.tsx`, Suggestions tab removed, disabled-action tooltips, send dialog, live nav badge | Regressing the existing mock UI's layout/styling while swapping its data source |

**Prerequisites:** `backend/context/archive/2026-07-22-api-exception-handling/` must be implemented and its tests green first (already landed) — this plan's Phase 2 exceptions implement `ApiExceptionInterface` from that change. Phases 5-6 depend on Phases 3-4 being deployed/available to develop and test against.
**Estimated effort:** ~5-6 sessions across 6 phases (4 backend, 2 frontend).

## Open Risks & Assumptions

- Assumes `symfony/clock` is safely addable — it's a near-certain transitive dependency already, but Phase 2 declares it explicitly rather than relying on that assumption at runtime.
- Assumes `api-exception-handling` ships `ApiExceptionInterface` with exactly the `getErrorCode(): string` / `getStatusCode(): int` contract this plan expects — already verified, since that change has landed.
- Frontend Phases 5-6 assume the backend Phases 1-4 are running and reachable in dev while implementing/testing the hooks — not just merged, but actually available to hit.

## Success Criteria (Summary)

- A user with no prior relation to another user can find them via search, send a request, and that user can accept it — both then see each other on `/friends` in the real UI, not mock data.
- Self-requests, duplicate requests, already-friends, cooldown-active resends, and non-owner accept/decline/cancel attempts are all rejected with clear, machine-readable error codes — and the frontend shows each one as a distinct, friendly message.
- Cancelling a sent request works and does not trigger the decline cooldown on a later resend.
