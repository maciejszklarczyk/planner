<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Health-Check Fixes Implementation Plan

- **Plan**: context/changes/health-check-fixes/plan.md
- **Mode**: Deep
- **Date**: 2026-07-12
- **Verdict**: REVISE
- **Findings**: 1 critical, 2 warnings, 0 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding

6/6 paths ✓ (package.json, .gitlab-ci.yml, Dockerfile, tsconfig.json, hooks/useDeleteGroup.ts, CLAUDE.md), 4/4 symbols ✓ (eslint-config-next pin, tsconfig `@/*` alias, `npm audit --json` fixAvailable data, `typescript@latest` = 7.0.2), brief↔plan ✓

## Findings

### F1 — CI job in Phase 4 has no runner tag, may never execute

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 4 — CI Quality Gate
- **Detail**: Both existing `.gitlab-ci.yml` jobs (`docker-build`, `deploy-production`) specify `tags: [docker]`. GitLab runners are typically tag-locked — a job without a matching tag can sit "pending" forever with no runner picking it up. The plan's `quality-checks` job spec omits `tags:` entirely. If the runner pool requires the `docker` tag (likely, given both existing jobs declare it), the new stage silently never runs.
- **Fix**: Add `tags: [docker]` to the `quality-checks` job spec in Phase 4's Contract, matching the existing jobs' pattern.
- **Decision**: FIXED

### F2 — Phase 2 references "frontend/CLAUDE.md" but cwd is already frontend/

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 — Changes Required #4 "Document convention"
- **Detail**: Plan says "add ... to `frontend/CLAUDE.md`". Verified: this project's working directory is already `.../plan/frontend/`, and the file lives at `./CLAUDE.md` (confirmed present) — `frontend/CLAUDE.md` does not exist from this cwd. The wrong path was inherited verbatim from `context/foundation/health-check.md` / `stack-assessment.md`, written from the monorepo root's perspective.
- **Fix**: Change the path in Phase 2 to `CLAUDE.md` (relative to this project's cwd).
- **Decision**: FIXED

### F3 — eslint-config-next stays pinned to 16.1.6 while next bumps to 16.2.10

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 1 — Next.js version bump
- **Detail**: `package.json:43` pins `"eslint-config-next": "16.1.6"` as an exact version, tracking `next` in lockstep by convention. Phase 1 bumps `next` to `16.2.10` but doesn't mention `eslint-config-next`. Left behind, `npm run lint` runs against a config generation older than the installed Next.js.
- **Fix**: In Phase 1's Contract, bump `eslint-config-next` to `16.2.10` alongside `next` in the same `npm install` command.
- **Decision**: FIXED
