# Migrate Deployment (GitLab CI → GitHub Actions) — Plan Brief

> Full plan: `context/changes/deploy-migration/plan.md`
> Research: `context/changes/deploy-migration/research.md`

## What & Why

Migrate the deleted `docker-build`/`deploy-production` GitLab CI jobs (backend + frontend) to GitHub Actions, closing the gap the prior `cicd-migration` change explicitly left open. Along the way: fix a real deploy-then-migrate ordering bug and revive frontend's fake healthcheck.

## Starting Point

`.github/workflows/backend-ci.yml` / `frontend-ci.yml` exist (lint/test/static-analysis only) — no deploy job. Both apps run on the same homelab server behind Traefik on `zbyszek-network`. A GitHub self-hosted runner for this project already exists (label `self-hosted, linux`), confirmed separate from sibling `car-repair-tracker`'s runner.

## Desired End State

Publishing a GitHub Release (manual, `vX.Y.Z`) triggers independent backend and frontend deploy workflows. Backend migrates before cutover (no more new-code-against-old-schema window). Frontend gets a real `/api/health` route backing both its Docker healthcheck and deploy-time readiness gate, and pins an immutable image tag instead of `:latest`.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
|---|---|---|---|
| Runner | Reuse existing dedicated `self-hosted, linux` runner | Already installed and separate from car-repair-tracker's — no install work needed | Plan |
| Registry / gating | GHCR, `release: published` + `workflow_dispatch`, fully manual publish | Matches car-repair-tracker's proven pattern; adds an auditable gate | Research |
| GHCR package visibility | Skip, leave as manual follow-up | Deploy doesn't depend on it (auth is `docker login` either way) | Plan |
| Deploy ordering | Migrate before cutover via `docker compose run --rm php` against new image while DB/redis stay up | Closes a known correctness gap, never fixed in the GitLab era | Plan |
| Migration compatibility | Add short explicit compatibility note, not a full process doc | Codifies the new ordering's implicit backward-compatibility requirement | Plan |
| Frontend healthcheck | Revive real `/api/health` + Dockerfile/compose probe | Closes a real gap while already touching deploy config | Plan |
| Frontend image tag | Align to fail-fast `${IMAGE_TAG:?...}` (matches backend) | Required for release-gating to mean anything for frontend | Plan |

## Scope

**In scope:** backend + frontend GitHub Actions deploy workflows, GHCR push, release-gating, migrate-before-cutover reorder, migration-compatibility note, frontend real healthcheck, frontend image-tag alignment.

**Out of scope:** runner install/registration (exists), GHCR package visibility linking, org-level runner promotion, updating stale `DOCKER-EXECUTOR-SETUP.md`, blue-green zero-downtime architecture.

## Architecture / Approach

Two independent workflows (`backend-deploy.yml`, `frontend-deploy.yml`), each `build` (GHCR push via `docker/build-push-action@v6`) → `deploy` (`runs-on: self-hosted, linux`), matching `car-repair-tracker`'s reference implementation and this repo's existing CI workflow style.

## Phases at a Glance

| Phase | What it delivers | Key risk |
|---|---|---|
| 1. Backend deploy workflow | GHCR build/push + migrate-before-cutover deploy job + compat note | Migration compatibility relies on discipline, not enforcement |
| 2. Frontend real healthcheck | `/api/health` route, Dockerfile + compose probe | Alpine lacks `curl` — must use `wget --spider` |
| 3. Frontend deploy workflow + tag alignment | GHCR build/push, `--wait`/curl-fallback deploy, immutable tag | Compose `--wait` needs ≥2.17, unverified on runner — fallback branch required |

**Prerequisites:** self-hosted runner confirmed live (per user); GHCR access via built-in `GITHUB_TOKEN`.
**Estimated effort:** ~1-2 sessions across 3 phases.

## Open Risks & Assumptions

- Runner's Compose version on the homelab is unverified — Phase 3's version-check branch handles either case, but should be confirmed manually during rollout.
- Migrate-before-cutover assumes all future migrations stay backward-compatible during the pre-cutover window; enforced only by convention (compat note), not tooling.
- A brief container-restart gap remains during backend's `down`/`up -d` — full zero-downtime was explicitly descoped.

## Success Criteria (Summary)

- Publishing a GitHub Release deploys both apps automatically via the self-hosted runner, no manual SSH steps
- Backend migration runs before traffic cutover on every deploy
- Frontend's healthcheck reflects real app health, and images are pinned to immutable release tags
