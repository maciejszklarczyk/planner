# Health-Check Fixes — Plan Brief

> Full plan: `context/changes/health-check-fixes/plan.md`

## What & Why

Close the findings from `context/foundation/health-check.md` (verdict: `critical-issues`): a HIGH-severity Next.js security advisory and a complete absence of a test runner are the two compounding gaps driving that verdict. This plan fixes both plus four supporting hygiene items (audit findings, formatter, CI gates, .editorconfig) and attempts one risky major-version bump under an explicit revert path.

## Starting Point

`next@16.1.6` is vulnerable (fixed in `16.2.10`). No test runner, no `test`/`typecheck`/`format` scripts exist. `.gitlab-ci.yml` has two stages (`docker-build`, `deploy`) with zero quality checks. No Prettier/`.editorconfig`. `typescript` is pinned `^5` (2 majors behind latest); `@types/node@^20` is correctly pinned to the Dockerfile's Node 20 runtime and is NOT being bumped.

## Desired End State

`npm audit` clean of HIGH findings. `npm run test` runs a passing Vitest smoke test. A single enforced Prettier style repo-wide. GitLab CI blocks `docker-build` on lint/typecheck/test/format failures. TypeScript is either upgraded to 7.x (if `tsc --noEmit` stays clean) or explicitly reverted with the attempt logged.

## Key Decisions Made

| Decision | Choice | Why | Source |
|---|---|---|---|
| Major-version bumps scope | Include TS 7.x as final phase; exclude lucide-react | TS is foundational and dev-only risk; lucide-react needs dedicated icon-API review | Plan (user) |
| Vitest coverage | Infra + 1 smoke test only (`useDeleteGroup`) | Unblocks TDD fast without over-scoping; matches health-check's "significant effort" estimate | Plan (user) |
| Prettier style | Prettier defaults (double quotes, semicolons) | Codebase is actually mixed; `components/`/`app/` (majority) already lean double-quote — confirmed via research, overriding initial single-quote assumption | Plan (research-corrected) |
| CI gate strictness | Hard gate from day one | Matches project's "no half-finished implementations" ethos; prevents broken code reaching prod | Plan (user) |
| @types/node | Deferred, not bumped | Dockerfile pins Node 20; verified via `Dockerfile:2` — 26.x would be a types/runtime mismatch | Plan (user + verified) |
| CI stage placement | New `quality` stage before `docker-build` | Fails fast, avoids wasted Docker build time on broken code | Plan (user) |
| Priority | All 6 in-scope fixes are must-have (TS bump is separately risk-flagged, not "must keep") | User wants health-check.md fully closed in one pass | Plan (user) |

## Scope

**In scope:** Next.js security bump, `npm audit fix`, Vitest + RTL + 1 smoke test, Prettier + `.editorconfig`, `.gitlab-ci.yml` quality gate, TypeScript major bump attempt (with revert path).

**Out of scope:** lucide-react 0.x→1.x bump, `@types/node` bump, full `hooks/` test coverage, CI caching strategy, `.git-blame-ignore-revs` setup.

## Architecture / Approach

Five sequential phases ordered by risk/dependency: security fixes first (independent, lowest risk) → test infra (needed before CI can gate on it) → formatting (isolated single commit, kept separate from logic diffs) → CI gating (depends on the scripts from phases 2-3 existing) → TypeScript major bump last (highest uncertainty, doesn't block anything else, has an explicit revert path).

## Phases at a Glance

| Phase | What it delivers | Key risk |
|---|---|---|
| 1. Security & dependency hygiene | `next@16.2.10`, `npm audit fix` | Low — non-major bump, transitive-only audit findings |
| 2. Test infrastructure | Vitest + RTL + 1 passing smoke test + CLAUDE.md docs | Medium — first-time setup, path alias config |
| 3. Formatting baseline | Prettier + `.editorconfig` + one repo-wide format commit | Medium — large diff in `lib/`/`hooks/` (currently single-quote) |
| 4. CI quality gate | New `quality` stage, hard-blocking | Low-medium — first run may surface pre-existing lint/type issues |
| 5. TypeScript major bump | `typescript@latest` (7.x) or explicit revert | High — likely the native-compiler rewrite; revert path defined |

**Prerequisites:** none blocking — can start immediately.
**Estimated effort:** ~1 session, phases mostly sequential (Phase 4 depends on 2+3's scripts existing).

## Open Risks & Assumptions

- TypeScript 7.x may not be a clean drop-in even with a simple `tsconfig.json` — Phase 5 has a defined bail-out, so worst case is "reverted, logged as follow-up."
- No GitLab CI caching precedent exists — the new `quality` stage runs `npm ci` cold every time, adding ~1-2 min per pipeline run; acceptable for now per user's priority call, but flagged as a future optimization.
- Prettier's repo-wide format commit assumes no in-flight branches will conflict badly — best merged when no other feature branches are open against `lib/`/`hooks/`.

## Success Criteria (Summary)

- `npm audit` shows 0 HIGH findings and `npm run test` passes in CI
- Every push to `main` is blocked by the `quality` stage on lint/typecheck/test/format failures
- `frontend/CLAUDE.md` documents the testing convention so future hook work follows it without re-asking
