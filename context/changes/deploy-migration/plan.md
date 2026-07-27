# Migrate Deployment (GitLab CI → GitHub Actions) Implementation Plan

## Overview

Migrate the deleted `backend/.gitlab-ci.yml` / `frontend/.gitlab-ci.yml` `docker-build`/`deploy-production` jobs to GitHub Actions, running on the existing self-hosted runner (`self-hosted, linux`), pushing images to GHCR, gated on published GitHub Releases. Also fixes the long-standing deploy-then-migrate ordering risk and revives frontend's real healthcheck.

## Current State Analysis

Both `backend-ci.yml` and `frontend-ci.yml` already exist under `.github/workflows/` (from the prior `cicd-migration` change) but only cover lint/test/static-analysis — no deploy job exists yet. The former GitLab deploy jobs (recovered from git history, see `context/changes/deploy-migration/research.md` and `docs/superpowers/plans/2026-07-23-monorepo-migration.md:148-369`) pushed to `$CI_REGISTRY_IMAGE` and deployed via `pull → down → up -d → migrate → cache:clear → curl healthcheck`, tag-gated on `$CI_COMMIT_TAG =~ /^v\d+\.\d+\.\d+$/`, running directly on a GitLab Docker-executor runner with host Docker access.

A GitHub self-hosted runner for this project already exists on the homelab (label: `self-hosted, linux`), separate from sibling project `car-repair-tracker`'s runner — no runner install work is needed. `car-repair-tracker/.github/workflows/deploy.yml` is a confirmed working reference for the GHCR + self-hosted-runner + release-gating combination.

Both prod compose files route through Traefik on the external `zbyszek-network`:
- `backend/docker-compose.prod.yaml` (85 lines) — services `php`, `database` (postgres:16-alpine), `redis` (redis:7-alpine); image ref already fail-fast (`${IMAGE_TAG:?IMAGE_TAG must be set}`); fake healthcheck (`["CMD", "php", "-v"]`).
- `frontend/docker-compose.prod.yaml` (29 lines) — single `frontend` service; image ref **hardcoded `:latest`**, no `IMAGE_TAG` var; fake healthcheck (`["CMD", "node", "-v"]`).

Real `DEPLOY_DIR` paths, corrected after the first live `workflow_dispatch` run failed
(`docs/apps/planner/...` was itself a stale placeholder, same as `DOCKER-EXECUTOR-SETUP.md`'s):
- Backend: `/home/maciej/docker/stacks/planner/backend`
- Frontend: `/home/maciej/docker/stacks/planner/frontend`

## Desired End State

Publishing a GitHub Release (`vX.Y.Z`, manual publish, no tag-push automation) triggers two independent workflows — `backend-deploy.yml` and `frontend-deploy.yml` — each building + pushing a GHCR image and deploying to the homelab via the existing self-hosted runner. Backend's deploy runs DB migrations against the new image *before* cutting traffic over (closing the deploy-then-migrate ordering gap). Frontend gets a real `/api/health` route backing both its Docker healthcheck and its deploy-time readiness gate, and its compose file pins an immutable `IMAGE_TAG` like backend already does.

Verification: publish a real GitHub Release and confirm both workflows complete, both apps are reachable at their Traefik domains, and `docker compose ps` shows healthy containers on the runner.

### Key Discoveries:

- `car-repair-tracker/.github/workflows/deploy.yml` — working reference: `docker/login-action@v3` + `secrets.GITHUB_TOKEN` (no PAT), `docker/build-push-action@v6`, `runs-on: self-hosted`, `on: workflow_dispatch` + `release: types: [published]`.
- `frontend/context/changes/cicd-rework/plan.md` (498 lines, abandoned) — full `/api/health` design: route returns `200 {"status":"ok"}`, no auth/data-fetch dependency; Dockerfile `HEALTHCHECK` via `wget --spider` (alpine has no `curl`); `--wait --wait-timeout 60` gating with a curl-retry-loop fallback if the runner's Compose is `<2.17`.
- `backend/docker-compose.prod.yaml` services `php`/`database`/`redis` — migrate-before-cutover targets `docker compose run --rm php` (DB/redis stay up throughout, so no dead window for the one-off migration container).
- `.github/workflows/backend-ci.yml` / `frontend-ci.yml` — existing style to match: `permissions: contents: read`, `concurrency: group: ${{ github.workflow }}-${{ github.ref }}` + `cancel-in-progress: true`, `defaults: run: working-directory: backend|frontend`.

## What We're NOT Doing

- Installing or registering the self-hosted runner (already exists).
- Setting GHCR package "inherit access from source repository" (deploy doesn't need it; left as an unmanaged manual follow-up if it ever matters).
- Promoting to an org-level shared runner.
- Updating the stale `backend/docs/DOCKER-EXECUTOR-SETUP.md` (GitLab-specific; superseded but not this plan's scope).
- Blue-green / zero-downtime deploy architecture (port aliasing, Traefik swap) — migrate-before-cutover closes the schema-mismatch window but a brief container-restart gap during `down`/`up -d` remains, same as today.

## Implementation Approach

Two independent GitHub Actions workflows (one per app, matching the existing separate-pipelines convention), each with a build-and-push job followed by a deploy job on `runs-on: self-hosted, linux`. Backend's deploy job reorders to migrate-before-cutover; frontend's deploy job drops the old `down` step entirely (its abandoned redesign already established that `up -d --wait` recreates in place without one).

## Critical Implementation Details

### State sequencing (backend deploy job)

The pre-cutover migration step must run against the **new** image while the **old** app containers are still serving traffic on the same `database`/`redis` services. Correct order:

```
docker compose -f docker-compose.prod.yaml pull
docker compose -f docker-compose.prod.yaml run --rm php php bin/console doctrine:migrations:migrate --no-interaction
docker compose -f docker-compose.prod.yaml stop php
docker compose -f docker-compose.prod.yaml up -d --no-deps php
docker compose -f docker-compose.prod.yaml exec -T php php bin/console cache:clear --env=prod
docker compose -f docker-compose.prod.yaml ps
timeout 30 sh -c 'until curl -sf https://api-planner.msolve.it/health; do sleep 2; done'
```

> **Addendum (impl-review F1)**: cutover uses `stop php` / `up -d --no-deps php` instead of a full `down`/`up -d`, so `database`/`redis` stay running throughout the deploy — avoids an unnecessary restart of those services (and Redis cache drop) on every release.
>
> **Addendum (first live run)**: final healthcheck is a retry loop, not a single `curl -f`. First live deploy hit a real race — the `php` container was ~1.5s into starting when curl fired and got a 404 before Traefik/FrankenPHP were ready to serve. Matches the retry pattern frontend's curl-fallback branch already used.

This means every migration must be additive/backward-compatible with the currently-running (old) code for the duration of this window — see the compatibility checklist added in Phase 1.

### Compose version gating (frontend deploy job)

`--wait` requires Compose ≥2.17, unverified on the runner. Deploy step must branch:

```
if docker compose version --short | awk -F. '{exit !($1>2 || ($1==2 && $2>=17))}'; then
  docker compose -f docker-compose.prod.yaml up -d --wait --wait-timeout 60
else
  docker compose -f docker-compose.prod.yaml up -d
  timeout 60 sh -c 'until curl -sf https://planner.msolve.it/api/health; do sleep 2; done'
fi
```

## Phase 1: Backend Deploy Workflow

### Overview

New GitHub Actions workflow builds + pushes the backend image to GHCR and deploys it to the homelab with migration running before cutover.

### Changes Required:

#### 1. Backend deploy workflow

**File**: `.github/workflows/backend-deploy.yml`

**Intent**: Build backend's `Dockerfile.prod` image, push to GHCR, then deploy on the self-hosted runner with migrate-before-cutover ordering.

**Contract**: Two jobs — `build` (GHCR push, `permissions: contents: read, packages: write`) and `deploy` (`needs: build`, `runs-on: self-hosted, linux`, `environment: { name: production, url: https://api-planner.msolve.it }`). Trigger: `on: workflow_dispatch` + `release: types: [published]`. Image: `ghcr.io/maciejszklarczyk/planner-backend:${{ github.event.release.tag_name }}` (fall back to a manual input for `workflow_dispatch` re-runs). Deploy job body follows the sequencing in "Critical Implementation Details" above, using `DEPLOY_DIR=/home/maciej/docker/stacks/planner/backend` and copying `backend/docker-compose.prod.yaml` into it first (matching the old GitLab job's `cp` step).

#### 2. Migration compatibility checklist

**File**: `backend/docs/MIGRATION-COMPATIBILITY.md` (new)

**Intent**: Document explicitly that, because deploy now runs migrations before cutover, every migration must be safe to apply while the *previous* release's code is still serving traffic (additive/backward-compatible; no destructive column drops in the same release that stops reading them).

**Contract**: Short markdown note (not a full guide) — states the rule, gives one good/bad example pair (e.g. "add nullable column then backfill in a later release" vs "rename column in one migration").

### Success Criteria:

#### Automated Verification:

- Workflow YAML is valid: `actionlint .github/workflows/backend-deploy.yml` (or `gh workflow view` after push)
- Existing backend CI still passes: `.github/workflows/backend-ci.yml` unaffected

#### Manual Verification:

- Trigger `workflow_dispatch` manually (or publish a test pre-release) and confirm the `build` job pushes an image visible in GHCR
- Confirm `deploy` job runs on the correct self-hosted runner and completes without error
- SSH to homelab, confirm `docker compose -f docker-compose.prod.yaml ps` shows healthy `php`/`database`/`redis` containers
- Confirm `curl -f https://api-planner.msolve.it/health` succeeds post-deploy
- Confirm migration ran before old containers went down (check container logs/timestamps)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Frontend Real Healthcheck

### Overview

Replace frontend's fake `node -v` healthcheck with a real `/api/health` route, reviving the abandoned `cicd-rework` design.

### Changes Required:

#### 1. Health route

**File**: `frontend/app/api/health/route.ts` (new)

**Intent**: A GET handler returning `200` with `{ status: "ok" }`, no auth/session dependency, no downstream data fetching — reflects only "the Next.js server process is up and routing."

**Contract**: Next.js App Router route handler exporting `GET`.

#### 2. Dockerfile healthcheck

**File**: `frontend/Dockerfile`

**Intent**: Replace the placeholder `node -v` healthcheck with a real HTTP probe against `/api/health`.

**Contract**: `HEALTHCHECK` instruction in the runner stage using `wget --spider http://localhost:3000/api/health` (alpine base lacks `curl` by default — deliberate choice from the original design), preserving whatever `interval`/`timeout`/`retries`/`start_period` values currently exist on the corresponding compose healthcheck.

#### 3. Compose healthcheck

**File**: `frontend/docker-compose.prod.yaml`

**Intent**: Swap the fake `["CMD", "node", "-v"]` healthcheck for the real `/api/health` probe, keeping existing timing parameters.

**Contract**: `healthcheck.test` becomes `["CMD", "wget", "--spider", "-q", "http://localhost:3000/api/health"]` (or equivalent), matching the Dockerfile's mechanism.

### Success Criteria:

#### Automated Verification:

- Type checking passes: `npm run typecheck` (frontend)
- Lint passes: `npm run lint` (frontend)
- Frontend CI still passes: `.github/workflows/frontend-ci.yml`

#### Manual Verification:

- `docker build` the frontend image locally and confirm `docker inspect --format='{{json .State.Health}}'` reports `healthy` after startup
- Hit `/api/health` directly in a running container and confirm `200 {"status":"ok"}`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Frontend Deploy Workflow + Image Tag Alignment

### Overview

Align frontend's compose image ref to an immutable `IMAGE_TAG` (matching backend) and add the frontend deploy workflow, using `--wait`-with-fallback gating instead of a `down`/`up` cycle.

### Changes Required:

#### 1. Frontend compose image tag

**File**: `frontend/docker-compose.prod.yaml`

**Intent**: Replace the hardcoded `:latest` image ref with a fail-fast `IMAGE_TAG` variable, so release-gated deploys actually pin an immutable image the way backend already does.

**Contract**: `image: ghcr.io/maciejszklarczyk/planner-frontend:${IMAGE_TAG:?IMAGE_TAG must be set}`.

#### 2. Frontend deploy workflow

**File**: `.github/workflows/frontend-deploy.yml`

**Intent**: Build + push frontend's image to GHCR, then deploy on the self-hosted runner without a `down` step, gated by the Compose-version check from "Critical Implementation Details."

**Contract**: Same two-job shape as backend's workflow (`build` → `deploy`, `runs-on: self-hosted, linux`, same trigger), but deploy job has no migration step and uses the `--wait`/curl-fallback branch instead of `down`/`up -d`/`sleep`. `environment: { name: production, url: https://planner.msolve.it }`, `DEPLOY_DIR=/home/maciej/docker/stacks/planner/frontend`.

### Success Criteria:

#### Automated Verification:

- Workflow YAML is valid: `actionlint .github/workflows/frontend-deploy.yml`
- Frontend CI still passes: `.github/workflows/frontend-ci.yml`

#### Manual Verification:

- Trigger `workflow_dispatch` and confirm image pushes to GHCR with the release tag (not `:latest`)
- Confirm deploy job's Compose-version branch takes the correct path on the actual runner (`docker compose version --short` on homelab)
- Confirm `https://planner.msolve.it` serves correctly post-deploy and `/api/health` returns `200`
- Confirm no `down` step caused a longer outage than backend's deploy

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- Frontend: no new unit tests needed for a trivial health route (covered by manual + type-check verification)

### Integration Tests:

- None new — deploy workflows are verified end-to-end manually (below), not via automated integration suites

### Manual Testing Steps:

1. Publish a real GitHub Release (`vX.Y.Z`) and confirm both `backend-deploy.yml` and `frontend-deploy.yml` trigger and complete
2. Confirm both apps reachable at their production domains post-deploy
3. Confirm backend migration ran before cutover (no window of new code + old schema)
4. Confirm frontend healthcheck reports real app status, not just process liveness
5. Re-trigger via `workflow_dispatch` (no new release) to confirm manual re-deploy works

## Performance Considerations

Migrate-before-cutover adds one extra `docker compose run --rm php` startup (container boot + migration run) to backend's deploy time before the old containers go down — a few extra seconds per deploy, acceptable for a manual-release-gated flow.

## Migration Notes

No data migration needed — this only changes CI/deploy tooling. Existing production data and running containers are untouched until the next Release is published.

## References

- Related research: `context/changes/deploy-migration/research.md`
- Reference implementation: `../car-repair-tracker/.github/workflows/deploy.yml`
- Abandoned healthcheck design: `frontend/context/changes/cicd-rework/plan.md`
- Current-state deploy YAML (recovered): `docs/superpowers/plans/2026-07-23-monorepo-migration.md:148-369`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Backend Deploy Workflow

#### Automated

- [x] 1.1 Workflow YAML is valid: `actionlint .github/workflows/backend-deploy.yml` — 379ca66
- [x] 1.2 Existing backend CI still passes — 379ca66

#### Manual

- [ ] 1.3 `workflow_dispatch` build job pushes image visible in GHCR
- [ ] 1.4 Deploy job runs on correct self-hosted runner and completes
- [ ] 1.5 `docker compose ps` shows healthy php/database/redis containers
- [ ] 1.6 `curl -f https://api-planner.msolve.it/health` succeeds post-deploy
- [ ] 1.7 Migration ran before old containers went down

### Phase 2: Frontend Real Healthcheck

#### Automated

- [x] 2.1 Type checking passes: `npx tsc --noEmit` (adapted — no `typecheck` script exists; matches `frontend-ci.yml`'s own check) — 661c385
- [x] 2.2 Lint passes: `npm run lint` — 661c385
- [x] 2.3 Frontend CI still passes — 661c385

#### Manual

- [ ] 2.4 `docker inspect` reports `healthy` after startup
- [ ] 2.5 `/api/health` returns `200 {"status":"ok"}`

### Phase 3: Frontend Deploy Workflow + Image Tag Alignment

#### Automated

- [x] 3.1 Workflow YAML is valid: `actionlint .github/workflows/frontend-deploy.yml` — adc2b7b
- [x] 3.2 Frontend CI still passes — adc2b7b

#### Manual

- [ ] 3.3 Image pushes to GHCR with release tag (not `:latest`)
- [ ] 3.4 Compose-version branch takes correct path on runner
- [ ] 3.5 `https://planner.msolve.it` serves correctly, `/api/health` returns 200
- [ ] 3.6 No longer outage than backend's deploy
