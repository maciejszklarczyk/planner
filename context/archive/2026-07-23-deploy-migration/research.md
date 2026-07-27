---
date: 2026-07-23T23:18:24+02:00
researcher: Maciej Szklarczyk
git_commit: 6b284ab2a04ff4d76b6b4bae78b8ab8edda60d51
branch: main
repository: maciejszklarczyk/planner
topic: "Migrate deployment (docker-build + deploy-production) from GitLab CI to GitHub Actions"
tags: [research, codebase, ci-cd, deployment, github-actions, ghcr, self-hosted-runner, homelab]
status: complete
last_updated: 2026-07-23
last_updated_by: Maciej Szklarczyk
last_updated_note: "Decided release-gating strategy: deploy on published GitHub Release, fully manual publishing"
---

# Research: Migrate deployment (docker-build + deploy-production) from GitLab CI to GitHub Actions

**Date**: 2026-07-23T23:18:24+02:00
**Researcher**: Maciej Szklarczyk
**Git Commit**: 6b284ab2a04ff4d76b6b4bae78b8ab8edda60d51
**Branch**: main
**Repository**: maciejszklarczyk/planner

## Research Question

Follow-up to `cicd-migration` (archived at `context/archive/2026-07-23-cicd-migration/`), which migrated build/test/lint CI to GitHub Actions but explicitly left deployment out of scope. This research covers migrating the remaining `docker-build`/`deploy-production` jobs (backend + frontend) to GitHub Actions, using a GitHub self-hosted runner on the same homelab server currently running a self-hosted GitLab Runner.

Scoping decisions made before research (via user Q&A):
- **Registry**: migrate to GHCR (ghcr.io), matching sibling project `car-repair-tracker`'s existing pattern.
- **Release gating**: DECIDED (post-research follow-up) — deploy triggers on a published GitHub Release (`release: published` + `workflow_dispatch`), matching `car-repair-tracker`'s pattern. Publishing the Release is fully manual — no tag-push auto-release automation. See "Release-gating" in Detailed Findings.

## Summary

The prior `cicd-migration` change (commit `651060d`) deleted all three `.gitlab-ci.yml` files and left deployment entirely out of scope — this is a genuine gap, not a continuation of stalled work. The exact former `docker-build`/`deploy-production` job content is recovered below from git history (pre-deletion commits) and from the archived change's own research.md.

Both backend and frontend currently deploy to the **same homelab server** via a self-hosted **GitLab Runner** (Docker executor, `backend/docs/DOCKER-EXECUTOR-SETUP.md`), reachable through Traefik on the `zbyszek-network` Docker network. Sibling project `car-repair-tracker` (a separate repo, same developer, same homelab, confirmed via matching `zbyszek-network` name and `msolve.it`-family domain) already runs a **GitHub Actions self-hosted runner** for its own deploy job — but critically, **this is only documented as a plan** (`car-repair-tracker/context/changes/deployment/deployment-plan.md`), not confirmed as an actually-completed, verified install. Treat "a working GitHub self-hosted runner already exists on this box" as **unverified** — check live (`ssh` + `systemctl status actions.runner.*`) before assuming reuse.

Architecturally, a GitHub self-hosted runner is simpler than GitLab's Docker-executor setup: it runs as a systemd service directly on the host (no `/var/run/docker.sock` bind-mount into a job container), executing `docker compose` commands as a normal host user with outbound-only polling (no inbound firewall changes needed). GHCR auth is simple (`docker/login-action@v3` + the built-in `GITHUB_TOKEN`, no PAT), directly reusable from `car-repair-tracker`.

Two real, unresolved deploy-design gaps predate this migration and aren't specific to the CI provider — worth carrying into planning:
1. **Deploy-then-migrate ordering**: `pull → down → up -d → migrate → cache:clear` means new containers already serve traffic *before* the DB migration runs — a known, never-fixed risk flagged in the archived `2026-07-12-cicd-rework` plan.
2. **Frontend's healthcheck is fake** (`node -v`, doesn't check the app is actually serving) — a real `/api/health` route was designed in an abandoned, never-implemented `frontend/context/changes/cicd-rework/plan.md` and could be picked up now.

## Detailed Findings

### Recovered deploy job content (deleted, from git history)

**`backend/.gitlab-ci.yml`** (as of the last commit before deletion, `651060d`'s parent):
```yaml
docker-build:
    stage: docker-build
    image: docker:latest
    tags: [docker]
    script:
        - cd backend
        - docker login -u $CI_REGISTRY_USER -p $CI_REGISTRY_PASSWORD $CI_REGISTRY
        - docker build -t $CI_REGISTRY_IMAGE:latest -t $CI_REGISTRY_IMAGE:$CI_COMMIT_SHORT_SHA -f docker/php/Dockerfile.prod .
        - if [ -z "$CI_COMMIT_TAG" ]; then docker push $CI_REGISTRY_IMAGE:latest && docker push $CI_REGISTRY_IMAGE:$CI_COMMIT_SHORT_SHA; fi
        - if [ -n "$CI_COMMIT_TAG" ]; then docker tag $CI_REGISTRY_IMAGE:$CI_COMMIT_SHORT_SHA $CI_REGISTRY_IMAGE:$CI_COMMIT_TAG && docker push $CI_REGISTRY_IMAGE:$CI_COMMIT_TAG; fi
    rules:
        - if: '$CI_COMMIT_BRANCH == "main"'
        - if: '$CI_COMMIT_TAG =~ /^v\d+\.\d+\.\d+$/'

deploy-production:
    stage: deploy
    image: docker:latest
    tags: [docker]
    before_script:
        - apk add --no-cache docker-compose curl
        - docker login -u $CI_REGISTRY_USER -p $CI_REGISTRY_PASSWORD $CI_REGISTRY
    script:
        - cd backend
        - export IMAGE_TAG=$CI_COMMIT_TAG
        - cd $DEPLOY_DIR
        - cp $CI_PROJECT_DIR/backend/docker-compose.prod.yaml $DEPLOY_DIR/
        - docker compose -f docker-compose.prod.yaml pull
        - docker compose -f docker-compose.prod.yaml down
        - docker compose -f docker-compose.prod.yaml up -d
        - sleep 10
        - docker compose -f docker-compose.prod.yaml exec -T php php bin/console doctrine:migrations:migrate --no-interaction
        - docker compose -f docker-compose.prod.yaml exec -T php php bin/console cache:clear --env=prod
        - docker compose -f docker-compose.prod.yaml ps
        - curl -f https://api-planner.msolve.it/health
    environment: { name: production, url: https://api-planner.msolve.it }
    rules:
        - if: '$CI_COMMIT_TAG =~ /^v\d+\.\d+\.\d+$/'
```

**`frontend/.gitlab-ci.yml`** (same recovery method) — same shape, no migration step, `sleep 5` instead of `10`, no `curl` healthcheck (only backend has one), `DEPLOY_DIR: /home/maciej/docker/apps/planner/frontend`.

### Current docker-compose.prod.yaml state

- `backend/docker-compose.prod.yaml:3` — `image: ${CI_REGISTRY_IMAGE:-registry.gitlab.com/planner6551704/backend}:${IMAGE_TAG:?IMAGE_TAG must be set}` — fail-fast on unset `IMAGE_TAG` (a prior impl-review caught and fixed a silent `:-latest` fallback here, per the archived cicd-rework review).
- `frontend/docker-compose.prod.yaml:3` — `image: registry.gitlab.com/planner6551704/frontend:latest` — **hardcoded `:latest`, no `IMAGE_TAG` var**, unlike backend. Worth aligning during this migration so frontend also deploys from immutable release tags.
- Both use the external `zbyszek-network` Docker network with Traefik labels for `api-planner.msolve.it` (backend) / `planner.msolve.it` (frontend).
- Backend healthcheck: `["CMD", "php", "-v"]` — checks the binary exists, not the app. Frontend healthcheck: `["CMD", "node", "-v"]` — same class of problem, explicitly flagged as fake in the abandoned frontend cicd-rework plan.

### Self-hosted runner: architecture comparison

- **Current (GitLab)**: Docker-executor runner, `/etc/gitlab-runner/config.toml`, `/var/run/docker.sock` bind-mounted into job containers, deploy dir also bind-mounted, tagged `docker` (`backend/docs/DOCKER-EXECUTOR-SETUP.md`).
- **GitHub self-hosted (car-repair-tracker's documented plan)**: installed as a systemd service directly on the host (`sudo ./svc.sh install && sudo ./svc.sh start`), polls GitHub outbound (no inbound firewall changes, no docker.sock mount needed — the runner process itself has host Docker access), labeled `self-hosted` (bare tag, not custom). Registration is **per-repository** — nothing indicates an org-level runner exists; planner would need its own registration (same box, distinct label e.g. `self-hosted, planner`, or promote to an org runner).
- **Not yet verified**: whether this runner is actually installed and running today, versus only planned. Confirm live before the plan assumes reuse.

### GHCR migration specifics

- Image names: `ghcr.io/maciejszklarczyk/planner-backend`, `ghcr.io/maciejszklarczyk/planner-frontend`.
- Auth: `docker/login-action@v3` with `username: ${{ github.actor }}`, `password: ${{ secrets.GITHUB_TOKEN }}` — no PAT needed, directly matches `car-repair-tracker/.github/workflows/deploy.yml`. Job needs `permissions: packages: write` (plus `contents: read`).
- Caveat: a new GHCR package defaults to **private**, not auto-linked to repo visibility — needs one manual step after first push (package settings → "Inherit access from source repository") if that matters; irrelevant for deploy itself since the runner authenticates via `docker login` regardless.
- Prefer `docker/build-push-action@v6` (car-repair-tracker's approach) over inline `docker build`/`docker push` — gives multi-tag support and GHA layer caching (`cache-from/cache-to: type=gha`) that's harder to hand-roll with plain script steps.
- `NEXT_PUBLIC_API_URL` (frontend build-arg) is not a secret — store as a repository **Variable**, not a Secret.

### Release-gating — DECIDED: deploy on published GitHub Release, fully manual publishing

Deploy triggers on `on: release: types: [published]` (+ `workflow_dispatch` for manual re-deploy without a new release), matching `car-repair-tracker`'s pattern exactly. Publishing the Release is a **fully manual** step — no tag-push automation. This is a deliberate behavior change from today's GitLab setup:

- Today: `git tag vX.Y.Z && git push --tags` deploys immediately (no separate publish step).
- Going forward: pushing a tag does **nothing** on its own. Deploy only fires when a Release is explicitly published — `gh release create vX.Y.Z --generate-notes` or via the GitHub UI's "Publish release" button. This is an intentional workflow change, not an oversight: it adds a deliberate, auditable gate between "code is tagged" and "code is live," at the cost of one extra manual step per deploy that didn't exist before.
- Draft/prerelease Releases do **not** trigger `release: published` — only an actual publish does.
- `release` events don't support `paths:` filtering (unlike `push`) — both backend and frontend would always deploy together on every published release regardless of which changed, which matches the *existing* intentional behavior (the monorepo migration unified both services on one shared tag rule, not a combined pipeline — two independent workflows/pipelines gated by the same trigger).
- `workflow_dispatch` is worth keeping alongside `release: published` so a bad deploy (or a runner hiccup) can be manually re-triggered without needing to cut a whole new release.

## Code References

- `backend/docs/DOCKER-EXECUTOR-SETUP.md` — full current GitLab Runner (Docker executor) setup: config.toml, docker.sock mount, DEPLOY_DIR, `zbyszek-network`, troubleshooting
- `backend/docker-compose.prod.yaml:1-85` — prod compose: php/database/redis services, Traefik labels, fail-fast `IMAGE_TAG`
- `frontend/docker-compose.prod.yaml:1-29` — prod compose: hardcoded `:latest`, no `IMAGE_TAG` var (inconsistent with backend)
- `../car-repair-tracker/.github/workflows/deploy.yml` — working reference: GHCR login/build/push, self-hosted deploy job
- `../car-repair-tracker/context/foundation/infrastructure.md` — homelab risk register, notes self-hosted runner setup as a real source of past ops pain ("required firewall changes that also broke the Cloudflare Tunnel")
- `../car-repair-tracker/context/changes/deployment/deployment-plan.md` — the *plan* for installing the GitHub self-hosted runner (not confirmed executed)

## Architecture Insights

- GitHub self-hosted runners are architecturally simpler than GitLab's Docker-executor pattern for this use case: no docker.sock passthrough, no inbound firewall changes, just a systemd-managed process polling outbound. This removes one of the two security tradeoffs `DOCKER-EXECUTOR-SETUP.md` documents (Docker Socket vs DinD) — the new model needs neither, since the runner isn't containerized itself.
- The monorepo's two deploy targets (backend, frontend) are intentionally kept as **separate pipelines/workflows sharing one trigger rule**, not merged into a single deploy job — this pattern should carry forward into the GitHub Actions design.
- `car-repair-tracker`'s deploy.yml already solves the GHCR+self-hosted-runner combination end-to-end for this exact developer/homelab — treat it as a working reference implementation, not just prior art, modulo the runner-installation caveat above.

## Historical Context (from prior changes)

- `context/archive/2026-07-23-cicd-migration/` (this project's own immediately-prior change) — migrated build/test/lint CI only; commit `651060d` deleted the `.gitlab-ci.yml` files and explicitly left `docker-build`/`deploy-production` out of scope. This research picks up exactly where that change stopped.
- `backend/context/archive/2026-07-12-cicd-rework/` — original GitLab deploy hardening (removed `|| true` from migrate/cache-clear so failures are loud) but explicitly did **not** fix the deploy-before-migrate ordering risk, and added no rollback tooling beyond "redeploy an older tag manually." A prior impl-review in this change also caught and fixed a silent `${IMAGE_TAG:-latest}` fallback (now `${IMAGE_TAG:?...}`).
- `frontend/context/changes/cicd-rework/` — unimplemented (status stuck at `plan_reviewed`, per the earlier `cicd-migration` research). Designed a real `/api/health` route and `docker compose up -d --wait` gating to replace the fake `node -v` healthcheck, but this was never built. Worth reviving as part of this deploy migration rather than carrying the fake healthcheck forward into GitHub Actions.
- `docs/superpowers/plans/2026-07-23-monorepo-migration.md` / design spec — confirms both projects were unified on the same tag rule (`^v\d+\.\d+\.\d+$`) for `deploy-production` during the monorepo merge, and states GitLab-remote continuity was "a separate decision for later" — no GitHub deploy intent was stated at that time; this migration is a fresh decision, same as the CI-only migration before it.

## Related Research

- `context/archive/2026-07-23-cicd-migration/research.md` — the CI-only migration this change follows up on; its "Follow-up Research" section already covers `car-repair-tracker`'s `ci.yml`/`quality-checks` pattern (not relevant here) and first flagged the self-hosted-runner-for-deploy angle this research expands on.

## Open Questions

- **Is a GitHub self-hosted runner actually installed and running on the homelab today?** `car-repair-tracker`'s repo only proves a *plan* exists, not a verified install — check live before the plan assumes it can just add a second repository's runner registration to existing infrastructure.
- **One shared runner or a dedicated one for planner?** No org-level runner evidence exists; decide between registering a second per-repo runner (with a distinct label) on the same box, or promoting to an org-level runner if `car-repair-tracker`'s is confirmed working.
- ~~**Release-gating strategy**~~ — DECIDED: deploy on published GitHub Release (`release: published` + `workflow_dispatch`), publishing kept fully manual (no tag-push auto-release automation). See "Release-gating" above.
- **Deploy-then-migrate ordering risk**: still unresolved from the GitLab era. Does this migration finally reorder to migrate-before-cutover, or is it explicitly carried forward as a known, accepted limitation (as it was in the prior GitLab rework)?
- **Frontend's fake healthcheck**: revive the abandoned `/api/health` + `--wait` design from `frontend/context/changes/cicd-rework/plan.md`, or keep `node -v` for now and scope it separately?
- **Frontend `docker-compose.prod.yaml`'s hardcoded `:latest`**: align it with backend's fail-fast `${IMAGE_TAG:?...}` pattern as part of this migration, or leave it as a known inconsistency?
- **GHCR package visibility**: confirm whether "Inherit access from source repository" needs to be set manually after first push, or whether it matters at all given the self-hosted runner always authenticates via `docker login`.
