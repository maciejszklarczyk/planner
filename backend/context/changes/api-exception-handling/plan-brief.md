# Global API Exception-Handling Infrastructure — Plan Brief

> Full plan: `context/changes/api-exception-handling/plan.md`
> Research: `context/changes/api-exception-handling/research.md`

## What & Why

Split out of `friendship-requests` planning: the user decided error handling should be unified across the *entire* API, not just the new Friendship domain. Today the codebase has four incompatible JSON error shapes across six controllers, no `kernel.exception` listener, and two Security handlers that already independently invented the target shape. This change builds one consistent envelope and migrates everything onto it before Friendship (or any future domain) is built.

## Starting Point

`Admin\GroupMembershipController` uses try/catch → `{error, message}`. `Admin\UserController` uses manual `if` → `{status, message}`. `InvitationController` uses `{valid, message}`. `UserAvatarController`/`UserController` use bare `{message}`. `AuthController` has its own one-off `{error: <message text>}`. `JsonAccessDeniedHandler`/`JsonAuthenticationEntryPoint` (Security firewall layer) already independently use `{error, message}` with the right codes. No global listener exists anywhere.

## Desired End State

Every API error response — regardless of which controller or which mechanism raised it — returns `{error, message, timestamp, path}` (plus `violations` for validation failures). All 6 controllers plus the 2 Security handlers are migrated; no controller contains a manual error-array branch anymore.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
|---|---|---|---|
| Migration scope | Migrate ALL existing controllers, not just future new domains | User explicitly chose full migration over a straddling period with two systems | friendship-requests plan (user Q&A) |
| Error-code assignment | Exceptions implement `ApiExceptionInterface` | Self-documenting, no central map to keep in sync as new exceptions are added | friendship-requests plan (user Q&A) |
| Error envelope shape | `{error, message, timestamp, path, violations?}` | User chose the fuller envelope over a minimal `{error, message, violations?}` option | friendship-requests plan (user Q&A) |
| Existing HTTP status codes | Left unchanged | Standardizes shape/codes without an unbounded blast radius on an already cross-cutting change | Plan |
| Security handlers (`JsonAccessDeniedHandler`/`EntryPoint`) | Reuse shared envelope factory, keep existing codes | They already have the right `error`/`message` values — only need the new `timestamp`/`path` fields | Plan (research-grounded) |

## Scope

**In scope:**
- `ApiExceptionInterface`, shared `ApiErrorEnvelopeFactory`, `kernel.exception` listener
- Migrating all 6 controllers + 2 Security handlers onto the new system
- ~13 new/migrated exception classes carrying today's exact codes and status codes
- Updating all tests that assert on old shapes

**Out of scope:**
- Any change to existing HTTP status codes
- The Friendship domain itself (`context/changes/friendship-requests/`, which depends on this)
- New business-rule validation beyond what today's manual checks already express

## Architecture / Approach

Prove the mechanism against the one controller with the strictest existing test coverage first (`Admin\GroupMembershipController`, Phase 1), then migrate the remaining five controllers and the two Security handlers in one pass (Phase 2) so the codebase is never left half-migrated with two competing error systems.

## Phases at a Glance

| Phase | What it delivers | Key risk |
|---|---|---|
| 1. Global API Exception Infrastructure | Interface, listener, envelope factory, first controller + Security handlers migrated | Must not break `GroupMembershipControllerTest`'s 8 exact error-code assertions |
| 2. Migrate Remaining Controllers | All 5 remaining controllers on the new system, old shapes fully removed | Largest blast radius — ~10 new exception classes, several test files updated |

**Prerequisites:** None — this is the first change in the dependency chain (roadmap Foundation `F-01`).
**Estimated effort:** ~2 sessions across 2 phases.

## Open Risks & Assumptions

- Assumes no functional test outside `GroupMembershipControllerTest`/`UserControllerTest`/`AuthControllerTest` asserts on an old error shape — Phase 2 explicitly re-verifies this via grep before finalizing.

## Success Criteria (Summary)

- Every error response across the entire API returns one consistent JSON shape with a machine-readable code.
- All previously-passing tests still pass (either unchanged, for the 8 `GroupMembershipControllerTest` assertions, or deliberately updated, for `UserControllerTest`/`AuthControllerTest`).
- `friendship-requests` can build its own domain exceptions directly on `ApiExceptionInterface` with no further infrastructure work.
