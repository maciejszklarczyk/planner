<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Migrate Deployment (GitLab CI → GitHub Actions)

- **Plan**: context/changes/deploy-migration/plan.md
- **Scope**: Full plan (Phase 3 of 3)
- **Date**: 2026-07-27
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Findings

### F1 — Backend deploy restarts database/redis unnecessarily

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: .github/workflows/backend-deploy.yml:63-64
- **Detail**: `docker compose down` before `up -d` stops ALL backend services (php, database, redis), not just the app container. Volumes persist (no data loss), but every deploy briefly takes Postgres and Redis offline too, and drops the Redis cache. Plan-faithful (matches plan's literal command list) but a real recurring operational cost.
- **Fix A ⭐ Recommended**: Scope cutover to the app service only — `docker compose stop php && docker compose up -d --no-deps php`
  - Strength: Database/redis stay up throughout, matching the plan's own rationale for keeping them alive during migration ("no dead window").
  - Tradeoff: Diverges from the plan's literal command list; needs a plan addendum.
  - Confidence: HIGH — docker compose supports scoped stop/up on individual services.
  - Blind spot: Haven't verified `--no-deps` + `up -d` combo on the runner's exact Compose version (should be fine on any modern Compose).
- **Fix B**: Keep `down`/`up -d` as planned, accept the wider restart
  - Strength: Zero extra work; matches plan and old GitLab job exactly.
  - Tradeoff: Recurring unnecessary outage window on every deploy.
  - Confidence: MEDIUM — acceptable for low-traffic homelab, but an ongoing cost.
  - Blind spot: None significant.
- **Decision**: FIXED via Fix A — `backend-deploy.yml` now uses `stop php` / `up -d --no-deps php`; plan.md updated with an addendum.

### F2 — No rollback on late-stage deploy failure

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: .github/workflows/backend-deploy.yml:58-67, .github/workflows/frontend-deploy.yml:58-67
- **Detail**: GitHub Actions `run:` steps default to `bash -eo pipefail`, so an early failure (e.g. migration) halts safely before `down` — old containers keep serving. But if `up -d` succeeds and only the final healthcheck fails, the job goes red with new, unverified containers already live — no automatic revert. Plan's "What We're NOT Doing" already excludes blue-green/zero-downtime architecture, so this gap is implicitly accepted scope, just undocumented.
- **Fix**: Add a one-line note (e.g. to `backend/docs/MIGRATION-COMPATIBILITY.md` or a new short doc) stating that a failed post-cutover healthcheck requires manual rollback (`docker compose down` + redeploy previous release tag).
- **Decision**: FIXED — added a "Rollback" section to `backend/docs/MIGRATION-COMPATIBILITY.md`.

### F3 — Healthcheck curls the public hostname, not the container directly

- **Severity**: OBSERVATION
- **Dimension**: Safety & Quality
- **Location**: .github/workflows/backend-deploy.yml:67, .github/workflows/frontend-deploy.yml curl-fallback branch
- **Detail**: Both post-deploy healthchecks go through Traefik + public DNS/TLS rather than hitting the container directly. A Traefik/DNS hiccup unrelated to the app could false-negative an otherwise-good deploy. Matches old GitLab job's behavior; not a regression.
- **Decision**: SKIPPED

### F4 — `packages: write` inherited by the deploy job unnecessarily

- **Severity**: OBSERVATION
- **Dimension**: Safety & Quality
- **Location**: .github/workflows/backend-deploy.yml:12-14, .github/workflows/frontend-deploy.yml:12-14
- **Detail**: Workflow-level `permissions: packages: write` is inherited by both `build` and `deploy`, though only `build` pushes to GHCR. Minor least-privilege nit; low real risk since `deploy` already has full host Docker access as a self-hosted runner job.
- **Decision**: FIXED — `packages: write` moved into a per-job `permissions` block on `build` only, in both workflows.

### F5 — `environment: production` protection rules unverifiable from code

- **Severity**: OBSERVATION
- **Dimension**: Safety & Quality
- **Location**: .github/workflows/backend-deploy.yml:45-47, .github/workflows/frontend-deploy.yml:45-47
- **Detail**: `environment: production` can enforce required reviewers/wait timers, but that's configured in repo settings, not visible in these files. Worth confirming in GitHub repo settings that this actually gates something.
- **Decision**: SKIPPED — not a code fix; manual follow-up to check in GitHub repo settings.
