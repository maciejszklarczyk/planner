---
date: 2026-07-13T18:50:47+02:00
researcher: Claude
git_commit: 4a0574f50d93eb3f3856eda2c89ffec9d255a788
branch: feature/cicd-rework
repository: planner6551704/frontend
topic: "Current CI/CD config: what's chaotic, what's missing"
tags: [research, codebase, ci-cd, gitlab-ci, docker, deployment]
status: complete
last_updated: 2026-07-13
last_updated_by: Claude
---

# Research: Current CI/CD config — what's chaotic, what's missing

**Date**: 2026-07-13T18:50:47+02:00
**Researcher**: Claude
**Git Commit**: 4a0574f50d93eb3f3856eda2c89ffec9d255a788
**Branch**: feature/cicd-rework
**Repository**: planner6551704/frontend

## Research Question

Research current CI/CD config, what's chaotic, what's missing.

## Summary

The frontend's `.gitlab-ci.yml` is a **3-stage, main-only deploy pipeline** (`quality` → `docker-build` → `deploy`), added recently (2026-07-13, health-check-fixes plan) to close a "no quality gates" health-check finding. It now runs lint/typecheck/test/format:check before building and auto-deploying. That part works, but the pipeline is narrowly scoped and several structural gaps remain:

- **Nothing runs on merge requests or feature branches** — every job is gated `only: [main]`. Code is unvalidated until it lands on `main`.
- **No caching** — every run does a cold `npm ci`; no Docker layer caching either.
- **No security scanning** — no `npm audit`, no GitLab SAST/dependency-scanning/container-scanning templates, no secret detection (the **backend** project already has secret detection — frontend doesn't).
- **Deploy is a hard-downtime, no-rollback operation**: `docker compose down` then `up`, a fixed `sleep 5`, then a status check that doesn't verify the app is actually healthy. `:latest` (mutable tag) is what gets deployed conceptually alongside the SHA tag, no staging environment, no manual approval gate, no rollback path.
- **No artifacts/reports** — test/lint/coverage output isn't surfaced in GitLab's UI; nothing to inspect after a run besides raw logs.
- The backend's `.gitlab-ci.yml` is meaningfully more mature (secret detection, PHPUnit coverage reports, composer audit, tag-gated deploy with DB migrations + real health-check curl) — a useful in-repo reference for patterns to port over.
- This isn't accidental neglect: the prior `health-check-fixes` plan **explicitly deferred** caching, coverage tooling, and a full CI restructure as out-of-scope, planning for it "as a future change." This change is that follow-up.

## Detailed Findings

### Current pipeline structure (`frontend/.gitlab-ci.yml`)

- 3 stages: `quality`, `docker-build`, `deploy` ([.gitlab-ci.yml:7-10](../../.gitlab-ci.yml)).
- `quality-checks` (stage `quality`, [.gitlab-ci.yml:13-25](../../.gitlab-ci.yml)): `node:20-alpine`, runs `npm ci`, `npm run lint`, `npx tsc --noEmit`, `npm run test`, `npm run format:check`. Gated `only: [main]`.
- `docker-build` ([.gitlab-ci.yml:28-39](../../.gitlab-ci.yml)): builds and pushes `$CI_REGISTRY_IMAGE:latest` and `:$CI_COMMIT_SHORT_SHA` to GitLab Container Registry. `only: [main]`.
- `deploy-production` ([.gitlab-ci.yml:42-62](../../.gitlab-ci.yml)): copies `docker-compose.prod.yaml` to `$DEPLOY_DIR`, runs `pull` → `down` → `up -d` → `sleep 5` → `ps`. `environment: production`, `only: [main]`.
- No `typecheck` npm script exists — CI invokes `npx tsc --noEmit` directly rather than through `package.json`.
- All npm scripts that exist ARE wired into CI (lint, test, format:check); the only scripts not run in CI are dev-only ones (`dev`, `test:watch`, `format` write-mode) — appropriately excluded. `build` is exercised only implicitly inside the Dockerfile, not standalone in `quality-checks`, so a build-breaking change surfaces late (in `docker-build`, after quality already passed).

### Branch/trigger strategy — the core structural gap

- Every job uses legacy `only: [main]` syntax (not `rules:`). Confirmed at [.gitlab-ci.yml:24-25](../../.gitlab-ci.yml), [:38-39](../../.gitlab-ci.yml), [:61-62](../../.gitlab-ci.yml).
- **Nothing in this file executes on the current branch** (`feature/cicd-rework`) or on any merge request — the entire pipeline is dormant until merge to `main`. This means broken code can be merged with zero automated feedback pre-merge; the "quality gate" only gates what's already on `main`, not what's about to land there.
- No `workflow:rules` to dedupe branch-push vs MR-triggered pipelines, no path-based `rules: changes:` to skip pipeline runs for docs-only changes.

### Caching & performance — absent entirely

- Zero `cache:` keys anywhere in the file (confirmed via grep). Every `quality-checks` run does a cold `npm ci`.
- No Docker layer caching (`--cache-from`, BuildKit inline cache) on `docker-build` — full image rebuild every run.
- No Next.js `.next/cache` persistence between builds.
- This was a **known, explicitly deferred** gap: the prior plan noted *"No caching precedent exists in `.gitlab-ci.yml` — first run establishes baseline; a caching follow-up (node_modules cache key) is out of scope here but worth a future change if pipeline time becomes a concern"* (`context/archive/2026-07-12-health-check-fixes/plan.md:279`).

### Security — no scanning of any kind

- No `npm audit` step in `quality-checks`.
- No GitLab security templates included (`SAST.gitlab-ci.yml`, `Dependency-Scanning.gitlab-ci.yml`, `Container-Scanning.gitlab-ci.yml`, `Secret-Detection.gitlab-ci.yml`).
- Contrast: **`backend/.gitlab-ci.yml`** already includes `Security/Secret-Detection.gitlab-ci.yml` and runs `composer-audit` as a dedicated job — the pattern exists in-repo, just not ported to frontend.

### Deployment safety — hard downtime, no rollback, weak health verification

- `deploy-production` ([.gitlab-ci.yml:50-57](../../.gitlab-ci.yml)) runs `docker compose down` **before** `up -d` — the app is fully unavailable for the pull+recreate window. No rolling/blue-green strategy.
- `sleep 5` is a fixed wait, not a real readiness probe, before the final `docker compose ps` status check — `ps` only confirms the container is running, not that the app inside is serving traffic correctly.
- `docker-compose.prod.yaml`'s `healthcheck` (lines 18-23, found by inventory agent) runs `node -v` — this checks that the `node` binary exists, not that the Next.js server is responding. It does not catch a broken app.
- No manual approval gate before deploy — `deploy-production` auto-fires on every push to `main`. No staging environment exists; it's a single `production` environment, direct from merge to live.
- Both `:latest` (mutable) and the immutable SHA tag are pushed, but the deploy step pulls via `docker-compose.prod.yaml`'s pinned image reference — worth confirming which tag that file actually references (not read in this pass) since mutable-tag deploys undermine reproducibility.
- No rollback job/path: if the new container fails, the old one is already gone (`down` ran first), and there's no automated revert to the last-known-good SHA.
- No post-deploy smoke test (e.g., `curl -f https://planner.msolve.it`) beyond the container-status check.
- Contrast: backend's deploy job runs DB migrations, cache clear, **and a real curl health check**, and is gated on version tags (`v\d+\.\d+\.\d+`) rather than every push to `main` — a materially safer release gate than frontend's "every main push auto-deploys."

### Artifacts & visibility — nothing surfaced

- No `artifacts: reports: junit:` for Vitest output — test results aren't visible in GitLab's MR/pipeline UI, only in raw job logs.
- No coverage reporting — Vitest has no `coverage` block configured in `vitest.config.ts`, and no `coverage_report` artifact exists to wire up even if it did. Backend, by contrast, already produces PHPUnit cobertura coverage reports.
- No build artifacts (e.g. `.next/`) retained for debugging a failed deploy.

### Docker/image hygiene — mostly fine, some gaps

- `Dockerfile` is a proper 3-stage build (`deps` → `builder` → `runner`), uses Next.js `standalone` output, alpine base, and runs as a **non-root** `nextjs` user — this part is solid, contrary to a generic "Next.js containers commonly run as root" assumption (verified false for this repo).
- No `HEALTHCHECK` instruction in the Dockerfile itself (health checking is deferred entirely to `docker-compose.prod.yaml`'s weak `node -v` check).
- No image vulnerability scanning gate.
- `.dockerignore` presence/correctness wasn't verified in this pass — worth a quick check before build-context changes.

### Notifications/observability — none configured

- No failure notification integration (Slack/email/webhook) on pipeline or deploy failure.
- No structured deploy-step logging retained as an artifact for postmortem — only ephemeral job console output.

## Code References

- `frontend/.gitlab-ci.yml:1-63` — full pipeline definition (3 stages, all `only: [main]`)
- `frontend/.gitlab-ci.yml:19-23` — quality-checks script (lint, tsc, test, format:check)
- `frontend/.gitlab-ci.yml:34-37` — docker-build push (both `:latest` and `:$CI_COMMIT_SHORT_SHA` tags)
- `frontend/.gitlab-ci.yml:50-57` — deploy sequence (`pull` → `down` → `up -d` → `sleep 5` → `ps`)
- `frontend/Dockerfile:1-56` — 3-stage build; non-root user setup at lines 38-49; no `HEALTHCHECK`
- `frontend/docker-compose.prod.yaml:18-23` — `healthcheck: node -v` (doesn't probe the HTTP server)
- `frontend/docker-compose.prod.yaml:12-17` — Traefik routing labels for `planner.msolve.it`
- `frontend/package.json:5-14` — npm scripts; no dedicated `typecheck` script
- `frontend/.env.example:4-7` — notes production `NEXT_PUBLIC_API_URL` is set via CI build-arg
- `backend/.gitlab-ci.yml` — reference pattern: secret detection, coverage, composer-audit, tag-gated deploy with real health-check curl

## Architecture Insights

- The frontend pipeline is young (added 2026-07-13) and was scoped narrowly on purpose — it closed a specific health-check finding ("no quality gates") rather than being designed as a general-purpose CI/CD system. Its gaps are mostly *scope-deferred*, not accidental.
- The backend project, in the same monorepo-adjacent structure, already demonstrates several of the missing patterns (secret detection, coverage reporting, safer tag-based deploy gating) — porting those patterns is lower-risk than inventing new ones, since they're already proven in this environment.
- Deploy topology is a single VPS via Docker Compose + Traefik, not an orchestrator (no k8s) — so "zero-downtime deploy" and "rollback" solutions need to fit that constraint (e.g., `docker compose up -d --wait`, keeping the previous image tag available for a manual revert command) rather than assuming cloud-native primitives.
- `only: [main]` on every job is the single highest-leverage structural gap: it means the "quality gate" added in the prior change never actually gates anything before merge — it only gates what's already merged. Any MR-pipeline work should be considered foundational to the rest of this rework, since caching/security/artifacts additions would ideally apply to both MR and main pipelines.

## Historical Context (from prior changes)

- `context/archive/2026-07-12-health-check-fixes/change.md` — the change that added the current `quality-checks` stage; addressed a health-check finding of "CI pipeline has no quality gates."
- `context/archive/2026-07-12-health-check-fixes/plan-brief.md` — explicitly scoped **out**: "CI caching strategy," "no `.git-blame-ignore-revs` setup," and generally "no caching strategy overhaul, no parallel job splitting" for `.gitlab-ci.yml`. Confirms this rework picks up exactly where that plan intentionally stopped.
- `context/archive/2026-07-12-health-check-fixes/plan.md:279` — flags the caching gap as a known follow-up.
- `context/archive/2026-07-12-health-check-fixes/plan.md:353` — a TypeScript 7.x major bump was attempted in that change and reverted (`eslint-config-next`'s bundled `typescript-eslint` crashed on TS7 internals) — not CI-related directly, but relevant if TS tooling changes are considered as part of this rework.
- `context/foundation/health-check.md:76-91,171-176` — original health-check finding that triggered the quality-gate addition; already resolved by the prior change, but the surrounding recommendation ("add a stage... before docker-build") was satisfied minimally, not comprehensively.

## Related Research

- None yet under `context/changes/**/research.md` — this is the first research artifact for this topic.

## Open Questions

- Which image tag does `docker-compose.prod.yaml` actually pull for production (`:latest` or a pinned SHA)? Not confirmed in this pass — affects how "immutable deploys" recommendation should be scoped.
- Is a GitLab Runner available with Docker-in-Docker/BuildKit support for layer caching, or is the `docker:latest` + `DOCKER_TLS_CERTDIR: ""` setup a constraint that limits caching options?
- Does the team want MR pipelines to run the full `quality` stage, or a lighter subset (e.g. skip `docker-build` on MRs, only run on main)?
- Is a staging environment feasible given the single-VPS deploy topology, or should "safety" instead focus on manual-approval + fast-rollback rather than a full staging tier?
- Should `.dockerignore` be audited as part of this rework (not verified in this research pass)?
