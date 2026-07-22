---
change_id: api-exception-handling
title: Global API exception-handling infrastructure
status: implementing
created: 2026-07-22
updated: 2026-07-22
archived_at: null
---

## Notes

Split out of `friendship-requests` during `/10x-plan` on 2026-07-22: while
planning the Friendship domain, the user decided error-handling should be
unified project-wide (not just for the new domain), which turned Phases 1-2
of that plan into a genuine cross-cutting enabler unrelated to Friendship
itself. Promoted to roadmap Foundation `F-01` and split into its own change
so `friendship-requests` stays scoped to the Friendship domain.

See `context/changes/api-exception-handling/plan.md` (former Phases 1-2 of
`friendship-requests/plan.md`) and `research.md` (former "migration
inventory" research, originally gathered inline during that planning
session).

`friendship-requests` now depends on this change landing first — its plan
assumes `ApiExceptionInterface` and the `kernel.exception` listener already
exist.
