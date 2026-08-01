---
change_id: friendship-requests
title: User sends, accepts, declines, and cancels friend requests (full-stack)
status: implemented
created: 2026-07-22
updated: 2026-08-01
archived_at: null
---

## Notes

Depends on `backend/context/archive/2026-07-22-api-exception-handling/` (roadmap Foundation
F-01), split out on 2026-07-22 during `/10x-plan` once the exception-
handling migration scope grew into a cross-cutting concern unrelated to
Friendship. That dependency has landed.

Promoted from backend-only to full-stack on 2026-08-01 (see
`context/foundation/roadmap.md`'s Baseline note) — the frontend already
had a fully-built, entirely mocked Friends UI
(`frontend/components/friends/FriendsView.tsx`) that this change wires to
the real backend. `plan.md` now has 6 phases (4 backend, 2 frontend); see
`plan-brief.md` for the full picture and `research.md`'s Follow-up
Research section for frontend implementation patterns.
