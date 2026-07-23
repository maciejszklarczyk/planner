# CI/CD Rework Implementation Plan

## Overview

Rework `frontend/.gitlab-ci.yml` from a narrowly-scoped main-only deploy pipeline into a proper CI/CD flow: quality checks gate merge requests (not just `main`), security scanning and dependency/Docker caching are added, test results are surfaced as GitLab artifacts, and releases become deliberate (tag-gated) with a real health-checked, near-zero-downtime deploy plus a post-deploy smoke test — mirroring patterns already proven in `backend/.gitlab-ci.yml`.

## Current State Analysis

`frontend/.gitlab-ci.yml` has 3 stages (`quality`, `docker-build`, `deploy`), all gated `only: [main]`. This means:

- Nothing runs on merge requests or feature branches — the quality gate added in the prior `health-check-fixes` change only checks code already on `main`, never code about to land there.
- No caching anywhere (`npm ci` runs cold every time; no Docker layer cache).
- No security scanning (no `npm audit`, no secret detection) despite `backend/.gitlab-ci.yml` already having both.
- `deploy-production` runs `pull` → `down` → `up -d` → `sleep 5` → `ps`, causing a hard-downtime window with no real readiness check — the compose healthcheck (`docker-compose.prod.yaml:18-23`) only runs `node -v`, which can't detect a broken app.
- Both `:latest` and the commit-SHA tag are pushed, but `docker-compose.prod.yaml:3` pins `:latest` — every merge to `main` auto-deploys immediately with no deliberate release step.
- No test/coverage artifacts surfaced in GitLab's UI.

Full detail: `context/changes/cicd-rework/research.md`.

## Desired End State

- `quality-checks` runs on every merge request (and still on `main`), so broken code is caught before merge, not after.
- `npm audit --audit-level=high` and GitLab's Secret Detection template run as part of the quality stage.
- `node_modules` is cached across CI runs (keyed on `package-lock.json`); `docker-build` uses registry-based BuildKit layer caching.
- Vitest results and coverage are visible as GitLab JUnit/cobertura reports in the MR widget.
- `docker-build` runs on `main` and on version tags (`vX.Y.Z`); `deploy-production` runs **only** on version tags — merging to `main` no longer auto-deploys.
- Deploy replaces `down` + `sleep 5` with `docker compose up -d --wait`, gated on a real HTTP healthcheck (Dockerfile `HEALTHCHECK` + compose `healthcheck` both probe an actual endpoint, not `node -v`), followed by a `curl -f` smoke test against the live URL.

Verification: push a throwaway branch with a deliberately broken test/lint — confirm the MR pipeline fails before merge. Tag a release (`vX.Y.Z`) after merging real work — confirm `deploy-production` only fires on the tag push, `up -d --wait` blocks until healthy, and the final `curl -f` step passes against `https://planner.msolve.it`.

### Key Discoveries:

- Backend already has every pattern this plan ports: `Security/Secret-Detection.gitlab-ci.yml` include, `composer-audit` job, `vendor/` cache keyed on `composer.lock`, tag-gated `deploy-production` (`backend/.gitlab-ci.yml`, `rules: if: '$CI_COMMIT_TAG =~ /^v\d+\.\d+\.\d+$/'`), and a real `curl -f https://api-planner.msolve.it/health` post-deploy check.
- `docker-compose.prod.yaml:3` deploys `:latest`, not a pinned SHA — confirmed by direct read, resolving research's open question.
- No health-check route exists anywhere under `frontend/app/` — one must be added before the Dockerfile/compose healthchecks or the CI smoke test can probe anything real.
- `frontend/package.json:5-14` has no `typecheck` script; CI calls `npx tsc --noEmit` directly — left as-is, out of scope for this rework.
- `vitest.config.ts` has no `coverage` block configured — must be added for Phase 4's cobertura artifact.

## What We're NOT Doing

- No staging environment / second compose stack — out of scope per user decision; would need new DNS/Traefik config and roughly doubles deploy job count.
- No Slack/email/webhook pipeline-failure notifications — GitLab's own MR/pipeline status UI is the feedback loop for now.
- No automated rollback job — a bad deploy that passes the smoke test but is still functionally broken is handled by a documented manual runbook (redeploy the previous tag), not new CI automation.
- No blue-green / dual-container zero-downtime deploy — `up -d --wait` removes the dead-container window from the current `down`-then-`up` sequence but is still a single-container in-place swap, not true zero-downtime. Acceptable given the single-VPS/Traefik topology.
- No TypeScript major-version bump, no `typecheck` npm script addition, no lucide-react upgrade — unrelated to CI/CD structure, already covered (attempted/reverted) in the prior `health-check-fixes` change.
- No coverage threshold enforcement — Phase 4 adds coverage *reporting* only; it does not fail the build on low coverage.

## Implementation Approach

Five phases ordered so each is independently verifiable and later phases build on earlier ones without rework:

1. **Pipeline triggers** first — this is the structural fix everything else benefits from (MR-visible feedback for phases 2-4).
2. **Security scanning** and **3. Caching** are independent of each other and of triggers beyond needing the `rules:` structure from phase 1 to already exist — both slot into the same `quality-checks` job.
3. **Test reporting artifacts** builds on the same job, low risk, no behavioral change to what passes/fails.
4. **Release & deploy safety** last — it's the highest-risk phase (touches production deploy behavior) and depends on nothing else changing first, so it can be validated in isolation with a real deploy.

## Phase 1: Pipeline Triggers (MR-Gated Quality Checks)

### Overview

Migrate from legacy `only: [main]` to `rules:`, add `merge_request_event` triggering to `quality-checks`, and add top-level `workflow:rules` so a branch that has an open MR doesn't run duplicate pipelines (branch-push + MR-push).

### Changes Required:

#### 1. Top-level workflow rules

**File**: `frontend/.gitlab-ci.yml`

**Intent**: Prevent duplicate pipelines when a commit is pushed to a branch with an open MR (both a branch pipeline and an MR pipeline would otherwise fire for the same commit).

**Contract**: Add a `workflow: rules:` block above `stages:` — run for `merge_request_event`, or for pushes to `main`, or for tag pipelines; skip a plain branch-push pipeline when it would duplicate an MR pipeline. This is GitLab's documented dedup pattern:

```yaml
workflow:
  rules:
    - if: '$CI_PIPELINE_SOURCE == "merge_request_event"'
    - if: '$CI_COMMIT_BRANCH == "main"'
    - if: '$CI_COMMIT_TAG'
```

#### 2. `quality-checks` job rules

**File**: `frontend/.gitlab-ci.yml`

**Intent**: Run quality checks on merge requests as well as `main`, so broken code is caught pre-merge.

**Contract**: Replace `only: [main]` on `quality-checks` with:

```yaml
rules:
  - if: '$CI_PIPELINE_SOURCE == "merge_request_event"'
  - if: '$CI_COMMIT_BRANCH == "main"'
```

`docker-build` and `deploy-production` keep their existing trigger scope in this phase — they're touched again in Phase 5 for tag-gating; don't change them here.

### Success Criteria:

#### Automated Verification:

- `.gitlab-ci.yml` is valid: `gitlab-ci-local` or GitLab's CI Lint API accepts the file (`gh`/`glab` not required — use GitLab's `/ci/lint` UI page or `glab ci lint` if the `glab` CLI is available; otherwise a self-check pipeline run against a throwaway MR is the verification, see Manual below)
- `npm run lint`, `npx tsc --noEmit`, `npm run test`, `npm run format:check` still pass locally (`npm ci && npm run lint && npx tsc --noEmit && npm run test && npm run format:check`)

#### Manual Verification:

- Open a throwaway MR from a feature branch with no changes — confirm `quality-checks` runs on the MR pipeline (visible in GitLab's MR "Pipelines" tab), not just on `main`
- Push a second commit to that branch after the MR is open — confirm only one pipeline runs (the MR pipeline), not two

---

## Phase 2: Security Scanning

### Overview

Add `npm audit` and GitLab's Secret Detection template to the quality stage, mirroring `backend/.gitlab-ci.yml`'s `composer-audit` and `secret_detection` jobs.

### Changes Required:

#### 1. npm audit step

**File**: `frontend/.gitlab-ci.yml`

**Intent**: Fail the quality stage on HIGH-or-above dependency vulnerabilities, closing the exact gap health-check.md originally flagged (the Next.js CVE that was already fixed) so future regressions are caught automatically.

**Contract**: Add `npm audit --audit-level=high` as an additional `script:` line in `quality-checks`, after `npm ci` and before/after the other checks (order doesn't matter functionally; group with other read-only checks).

#### 2. Secret Detection stage

**File**: `frontend/.gitlab-ci.yml`

**Intent**: Catch committed secrets pre-merge, matching backend's existing coverage.

**Contract**: Add a `secret-detection` stage (or fold into an existing stage — backend uses a dedicated stage, follow that convention for consistency) and include the template:

```yaml
include:
  - template: Security/Secret-Detection.gitlab-ci.yml
```

Add `secret-detection` to the `stages:` list, positioned before `docker-build` (matches backend's stage ordering: `build, test, secret-detection, lint, docker-build, deploy`). The included template job runs automatically on the branches covered by `workflow:rules` from Phase 1 — no `rules:` override needed unless GitLab's default template scoping conflicts with this repo's `workflow:rules` (verify during manual testing).

### Success Criteria:

#### Automated Verification:

- `npm audit --audit-level=high` exits 0 locally against current `package-lock.json` (`npm audit --audit-level=high`)
- `.gitlab-ci.yml` still lints clean after the `include:` and new stage are added

#### Manual Verification:

- Push the throwaway MR again — confirm `npm audit` step output appears in the `quality-checks` job log
- Confirm the `secret_detection` job appears and runs in the MR pipeline (GitLab's template job name is `secret_detection`)
- Temporarily commit a fake high-entropy string (e.g. a dummy AWS-key-shaped string) on a scratch branch, confirm secret detection flags it, then remove it — do not push real secrets for this test

---

## Phase 3: Caching

### Overview

Add `node_modules` caching to `quality-checks` (keyed on `package-lock.json`, mirroring backend's `vendor/` cache) and registry-based Docker layer caching to `docker-build`.

### Changes Required:

#### 1. npm cache

**File**: `frontend/.gitlab-ci.yml`

**Intent**: Skip re-downloading/re-linking unchanged dependencies on every quality-stage run.

**Contract**: Add to `quality-checks`:

```yaml
cache:
  key:
    files:
      - package-lock.json
  paths:
    - node_modules/
```

#### 2. Docker layer cache

**File**: `frontend/.gitlab-ci.yml`

**Intent**: Reuse unchanged Docker layers (primarily the `deps` stage, which rarely changes) across `docker-build` runs instead of rebuilding from scratch every time.

**Contract**: Enable BuildKit and pass `--cache-from`/`--cache-to` against the registry image in `docker-build`'s script:

```yaml
variables:
  DOCKER_BUILDKIT: "1"
script:
  - docker login -u $CI_REGISTRY_USER -p $CI_REGISTRY_PASSWORD $CI_REGISTRY
  - docker build --build-arg BUILDKIT_INLINE_CACHE=1 --cache-from $CI_REGISTRY_IMAGE:latest --build-arg NEXT_PUBLIC_API_URL=$NEXT_PUBLIC_API_URL -t $CI_REGISTRY_IMAGE:latest -t $CI_REGISTRY_IMAGE:$CI_COMMIT_SHORT_SHA .
  - docker push $CI_REGISTRY_IMAGE:latest
  - docker push $CI_REGISTRY_IMAGE:$CI_COMMIT_SHORT_SHA
```

**Risk flagged during planning**: the runner's `docker:latest` + `DOCKER_TLS_CERTDIR: ""` setup was not verified to support BuildKit inline caching. If the first `docker-build` run after this change fails or shows no cache hits, fall back to plain `docker build` (drop `--cache-from`/`BUILDKIT_INLINE_CACHE`) and record the runner limitation as a follow-up rather than blocking this phase — everything else in this plan is independent of this working.

### Success Criteria:

#### Automated Verification:

- N/A (caching behavior can only be observed via actual pipeline runs, not locally)

#### Manual Verification:

- Run the MR pipeline twice in a row with no dependency changes — confirm the second `quality-checks` run's job log shows a cache hit (`Downloading cache...` / `Successfully extracted cache`) and completes faster than the first
- Merge to `main` twice with no Dockerfile changes between — confirm the second `docker-build` run's log shows `CACHED` layers, or (if the runner doesn't support BuildKit inline cache) confirm the fallback to plain `docker build` was applied and documented

---

## Phase 4: Test Reporting Artifacts

### Overview

Configure Vitest to emit JUnit and cobertura coverage reports, and wire them as GitLab `artifacts: reports:` on `quality-checks` so results surface in the MR widget — mirroring backend's `phpunit` job.

### Changes Required:

#### 1. Vitest coverage + JUnit config

**File**: `frontend/vitest.config.ts`

**Intent**: Emit coverage data and a JUnit-format test report on every `vitest run`.

**Contract**: Add a `coverage` block (provider `v8` or `istanbul`, `reporter: ['cobertura', 'text']`) to the `test:` config, and configure a JUnit reporter output path (via `--reporter=junit --outputFile=./junit.xml` on the CLI invocation, or `test.reporters`/`test.outputFile` in config — either is fine, keep it in `vitest.config.ts` for consistency with the rest of the test setup).

#### 2. CI artifacts wiring

**File**: `frontend/.gitlab-ci.yml`

**Intent**: Surface the JUnit and coverage reports GitLab-natively in the MR pipeline widget.

**Contract**: On `quality-checks`, add:

```yaml
artifacts:
  when: always
  reports:
    junit: junit.xml
    coverage_report:
      coverage_format: cobertura
      path: coverage/cobertura-coverage.xml
coverage: '/All files[^|]*\|[^|]*\s+([\d\.]+)/'
```

(`coverage:` regex format depends on the chosen reporter's text output — verify the exact match pattern against actual `vitest run --coverage` text output during implementation, adjust if the v8/istanbul text-summary format differs from the placeholder above.)

### Success Criteria:

#### Automated Verification:

- `npm run test -- --coverage` (or equivalent updated script) runs locally and produces `coverage/cobertura-coverage.xml` and `junit.xml`
- `.gitlab-ci.yml` lints clean with the new `artifacts:` block

#### Manual Verification:

- Push the throwaway MR — confirm the MR's "Tests" tab in GitLab shows the JUnit results, and the pipeline shows a coverage percentage
- Intentionally break the one existing smoke test (`hooks/useDeleteGroup.test.ts`) on a scratch commit, confirm the MR widget surfaces the failing test by name, then revert the break

---

## Phase 5: Release & Deploy Safety

### Overview

Tag-gate `deploy-production` (mirroring backend), keep `docker-build` running on `main` and tags, add a real health endpoint, fix the Dockerfile/compose healthchecks to probe it, replace `down`+`sleep 5` with `up -d --wait`, and add a post-deploy `curl -f` smoke test.

### Changes Required:

#### 0. Verify Compose `--wait` support on the runner

**File**: n/a (runner verification step, no repo file changed)

**Intent**: `docker compose up -d --wait` (item 6 below) requires Compose V2 ≥2.17. The deploy job's `before_script` runs `apk add --no-cache docker-compose` — Alpine's `docker-compose` package has historically been the legacy V1 Python CLI. No file in this repo confirms which version is actually resolved on the runner (`backend/docs/DOCKER-EXECUTOR-SETUP.md:162-169` poses this exact question as an unresolved manual test), and backend's own separate CI/CD rework explicitly deferred this cutover-safety problem as out-of-scope rather than attempting `--wait`. This must be confirmed before the rest of Phase 5 is implemented.

**Contract**: Before implementing item 6, run `docker compose version` on the actual GitLab runner (e.g. via a throwaway CI job with `script: [docker compose version]`, or SSH to the runner host). If it reports ≥2.17, proceed with `up -d --wait --wait-timeout 60` as specified in item 6. If it's unsupported or <2.17, replace item 6's `up -d --wait --wait-timeout 60` line with a curl-retry polling loop instead (matching backend's already-proven `curl -f https://api-planner.msolve.it/health` pattern):

```yaml
- docker compose -f docker-compose.prod.yaml up -d
- timeout 60 sh -c 'until curl -sf https://planner.msolve.it/api/health; do sleep 2; done'
```

Record whichever path was taken in this plan's Progress notes when Phase 5 is implemented.

#### 1. Health check endpoint

**File**: `frontend/app/api/health/route.ts` (new file)

**Intent**: Give the Dockerfile healthcheck, the compose healthcheck, and the CI smoke test a real, unauthenticated, cheap endpoint to probe — none exists today.

**Contract**: A GET route handler returning `200` with a minimal JSON body (e.g. `{ status: "ok" }`), with no auth/session dependency, no data fetching — it must reflect "the Next.js server process is up and routing," not downstream API health.

#### 2. Dockerfile healthcheck

**File**: `frontend/Dockerfile`

**Intent**: Let Docker itself know the container is actually serving, not just that the `node` binary exists.

**Contract**: Add a `HEALTHCHECK` instruction in the `runner` stage probing `http://localhost:3000/api/health` (alpine images lack `curl` by default — use `wget --spider` or Node's built-in `http` module via a one-line inline script, since adding `curl` would grow the final image unnecessarily).

#### 3. Compose healthcheck

**File**: `frontend/docker-compose.prod.yaml`

**Intent**: Replace the `node -v` check (which can't detect a broken app) with a real HTTP probe, so `up -d --wait` in Phase 5.5 has something meaningful to wait on.

**Contract**: Change the `healthcheck.test` at `docker-compose.prod.yaml:19` to hit `/api/health` (same mechanism as the Dockerfile's, `wget` or Node inline), keeping the existing `interval`/`timeout`/`retries`/`start_period` values unless the new probe needs different timing.

#### 4. Docker image tagging strategy

**File**: `frontend/.gitlab-ci.yml`

**Intent**: `docker-build` continues producing images on every `main` merge (so an image always exists to tag for release) and additionally tags+pushes the version-tag image when a release tag is pushed, matching backend's conditional tag-push logic.

**Contract**: Change `docker-build`'s trigger from the Phase-1-untouched `only: [main]` to:

```yaml
rules:
  - if: '$CI_COMMIT_BRANCH == "main"'
  - if: '$CI_COMMIT_TAG =~ /^v\d+\.\d+\.\d+$/'
```

Add conditional tag-push logic to the script (matching backend's pattern) so a `$CI_COMMIT_TAG` push also pushes `$CI_REGISTRY_IMAGE:$CI_COMMIT_TAG`:

```yaml
script:
  - docker login -u $CI_REGISTRY_USER -p $CI_REGISTRY_PASSWORD $CI_REGISTRY
  - docker build --build-arg BUILDKIT_INLINE_CACHE=1 --cache-from $CI_REGISTRY_IMAGE:latest --build-arg NEXT_PUBLIC_API_URL=$NEXT_PUBLIC_API_URL -t $CI_REGISTRY_IMAGE:latest -t $CI_REGISTRY_IMAGE:$CI_COMMIT_SHORT_SHA .
  - if [ -z "$CI_COMMIT_TAG" ]; then docker push $CI_REGISTRY_IMAGE:latest && docker push $CI_REGISTRY_IMAGE:$CI_COMMIT_SHORT_SHA; fi
  - if [ -n "$CI_COMMIT_TAG" ]; then docker tag $CI_REGISTRY_IMAGE:$CI_COMMIT_SHORT_SHA $CI_REGISTRY_IMAGE:$CI_COMMIT_TAG && docker push $CI_REGISTRY_IMAGE:$CI_COMMIT_TAG; fi
```

#### 5. Deploy gating and image reference

**File**: `frontend/.gitlab-ci.yml`, `frontend/docker-compose.prod.yaml`

**Intent**: Deploys only happen on a deliberate version-tag push, and pull the tag's specific image rather than the mutable `:latest`.

**Contract**: Change `deploy-production`'s trigger from `only: [main]` to:

```yaml
rules:
  - if: '$CI_COMMIT_TAG =~ /^v\d+\.\d+\.\d+$/'
```

Update `docker-compose.prod.yaml:3`'s `image:` to `registry.gitlab.com/planner6551704/frontend:${IMAGE_TAG:?IMAGE_TAG must be set}` — the `:?` form fails loudly if `IMAGE_TAG` is unset, rather than silently falling back to `:latest` (backend's archived `2026-07-12-cicd-rework` change shipped `${IMAGE_TAG:-latest}` first, then had to fix it to this fail-fast form after impl-review flagged it as silently reintroducing the floating tag the whole rework exists to kill — see `backend/context/archive/2026-07-12-cicd-rework/reviews/impl-review.md:23-40`). Export `IMAGE_TAG=$CI_COMMIT_TAG` in `deploy-production`'s script before `docker compose pull` — matching backend's `export IMAGE_TAG=$CI_COMMIT_TAG` pattern.

#### 6. Deploy sequence (downtime + smoke test)

**File**: `frontend/.gitlab-ci.yml`

**Intent**: Remove the dead-container window from `down`+`up`, gate on the real healthcheck via Compose's own wait mechanism, and verify the live app is actually serving traffic before the job succeeds.

**Contract**: Replace the `pull` → `down` → `up -d` → `sleep 5` → `ps` sequence in `deploy-production`'s script with:

```yaml
script:
  - export IMAGE_TAG=$CI_COMMIT_TAG
  - cd $DEPLOY_DIR
  - cp $CI_PROJECT_DIR/docker-compose.prod.yaml $DEPLOY_DIR/
  - docker compose -f docker-compose.prod.yaml pull
  - docker compose -f docker-compose.prod.yaml up -d --wait --wait-timeout 60
  - docker compose -f docker-compose.prod.yaml ps
  - curl -f https://planner.msolve.it/api/health
```

No `down` step — Compose recreates the container in place when the image changes. `--wait` (or the item-0 curl-retry fallback) blocks the job until the app reports healthy or the timeout is hit, so a broken deploy fails the CI job instead of silently leaving a dead container running.

#### 7. Update DEPLOYMENT.md

**File**: `frontend/DEPLOYMENT.md`

**Intent**: The current doc describes auto-deploy-on-main-push and a manual "edit the `image:` line to a SHA tag" rollback procedure — both now wrong. This is the operator-facing runbook consulted during real incidents; leaving it stale means wrong instructions at exactly the moment correctness matters most.

**Contract**: Update the "Automatic Build (on push to main)" / "Manual Deployment" section to describe the tag-gated release flow (`git tag vX.Y.Z && git push origin vX.Y.Z` triggers `deploy-production`; merging to `main` only builds/pushes the image, it no longer deploys). Update the "Rollback" section to replace the manual `image:` line edit with re-pushing (or re-triggering a pipeline against) an older version tag, reflecting the `${IMAGE_TAG:?...}` mechanism from item 5. Update "Verification" to mention the new `/api/health` endpoint alongside the existing `curl -I` check.

### Success Criteria:

#### Automated Verification:

- `curl -f http://localhost:3000/api/health` returns `200` when run against a local `npm run build && npm run start`
- Dockerfile builds successfully with the new `HEALTHCHECK` instruction: `docker build -t frontend-healthcheck-test .`
- `.gitlab-ci.yml` lints clean with all Phase 5 changes applied

#### Manual Verification:

- `docker compose version` confirmed on the runner (≥2.17 → `--wait` used as specified; otherwise the curl-retry fallback from item 0 is used instead) before implementing the rest of this phase
- Merge a real change to `main` — confirm `docker-build` runs and pushes `:latest`/SHA tags, and confirm `deploy-production` does **not** run (no tag pushed yet)
- Push a version tag (e.g. `git tag v0.1.0 && git push origin v0.1.0`) — confirm `docker-build` re-runs and pushes the `v0.1.0`-tagged image, then `deploy-production` runs, `up -d --wait` blocks until healthy, and the final `curl -f https://planner.msolve.it/api/health` step passes
- Manually visit `https://planner.msolve.it` in a browser after the tagged deploy completes — confirm the app is actually serving correctly (not just that the CI job reported success)
- Verify container availability during the deploy: watch `docker compose ps` / hit the site during a tagged deploy — confirm no extended "connection refused" window compared to the old `down`-then-`up` behavior
- Validate the manual rollback runbook (documented in `DEPLOYMENT.md`, updated per this phase) by redeploying an older tag once and confirming the site serves the older version
- `DEPLOYMENT.md`'s Automatic Build/Manual Deployment and Rollback sections accurately describe the tag-gated flow and `IMAGE_TAG` mechanism (reviewed by re-reading the doc against the implemented `.gitlab-ci.yml`)

---

## Testing Strategy

### Unit Tests:

- No new unit tests required by this plan — Phase 5's `/api/health` route is trivial enough (static 200 JSON, no logic) that a dedicated test isn't warranted; existing `hooks/useDeleteGroup.test.ts` remains the sole smoke test and is used in Phase 4 to verify JUnit reporting works.

### Integration Tests:

- End-to-end verification of this plan is inherently pipeline-level (GitLab CI behavior, Docker healthchecks, live deploy behavior) — covered by the Manual Verification steps in each phase, not by new automated integration tests in the app itself.

### Manual Testing Steps:

1. Open a throwaway MR early (Phase 1) and reuse it across Phases 1-4 to observe cumulative pipeline behavior (rules, audit, secret detection, caching, artifacts) without repeatedly re-triggering separate MRs.
2. For Phase 5, testing requires an actual tag push and real deploy to `planner.msolve.it` — coordinate timing so this doesn't collide with other in-flight deploys.
3. After Phase 5 lands, confirm the manual-rollback runbook works by intentionally deploying an older tag once (e.g. redeploy the tag prior to `v0.1.0`) and confirming the site serves the older version — validates the "documented manual rollback" approach from the scope decisions actually functions.

## Performance Considerations

- Phase 3's `node_modules` cache trades CI runner disk usage for reduced `npm ci` time — expect a measurable speedup on the second and subsequent `quality-checks` runs against an unchanged `package-lock.json`.
- Phase 3's Docker layer cache depends on runner BuildKit support (unverified) — if unsupported, `docker-build` time is unchanged from today; this is a soft-fail, not a blocker (see Phase 3's flagged risk).
- Phase 1 doubles pipeline invocations in the sense that an MR pipeline now runs quality-checks that previously only ran post-merge — this is the intended cost of the "MR gating" decision; `workflow:rules` prevents it from tripling via duplicate branch+MR runs.

## Migration Notes

- Existing `:latest`-tagged production deployment is unaffected by this plan until the first version tag is pushed post-merge — until then, `docker-compose.prod.yaml` still resolves `:latest` as today. The `IMAGE_TAG` env var change (Phase 5, item 5) requires the first tagged deploy to happen manually/deliberately; document the new `git tag vX.Y.Z && git push origin vX.Y.Z` release step for the team once this plan lands.
- No database or data migrations involved — this is CI/CD configuration only.

## References

- Related research: `context/changes/cicd-rework/research.md`
- Reference implementation (backend): `backend/.gitlab-ci.yml`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Pipeline Triggers (MR-Gated Quality Checks)

#### Automated

- [ ] 1.1 CI lint passes with workflow:rules + quality-checks rules changes
- [ ] 1.2 npm run lint, npx tsc --noEmit, npm run test, npm run format:check pass locally

#### Manual

- [ ] 1.3 quality-checks runs on a throwaway MR pipeline
- [ ] 1.4 Second commit on same MR branch triggers only one pipeline (no duplicate)

### Phase 2: Security Scanning

#### Automated

- [ ] 2.1 npm audit --audit-level=high exits 0 locally
- [ ] 2.2 CI lint passes with secret-detection include + stage added

#### Manual

- [ ] 2.3 npm audit step output visible in quality-checks job log
- [ ] 2.4 secret_detection job appears and runs in MR pipeline
- [ ] 2.5 Fake high-entropy string on scratch branch is flagged by secret detection

### Phase 3: Caching

#### Manual

- [ ] 3.1 Second consecutive quality-checks run shows npm cache hit and faster completion
- [ ] 3.2 Second consecutive docker-build run shows cached layers, or documented BuildKit fallback applied

### Phase 4: Test Reporting Artifacts

#### Automated

- [ ] 4.1 npm run test -- --coverage produces coverage/cobertura-coverage.xml and junit.xml locally
- [ ] 4.2 CI lint passes with artifacts: reports: block added

#### Manual

- [ ] 4.3 MR Tests tab shows JUnit results and pipeline shows coverage percentage
- [ ] 4.4 Intentionally broken test surfaces by name in MR widget, then reverted

### Phase 5: Release & Deploy Safety

#### Automated

- [ ] 5.1 curl -f http://localhost:3000/api/health returns 200 against local build+start
- [ ] 5.2 Dockerfile builds successfully with new HEALTHCHECK instruction
- [ ] 5.3 CI lint passes with all Phase 5 changes applied

#### Manual

- [ ] 5.4 docker compose version confirmed on runner (>=2.17 uses --wait; otherwise curl-retry fallback used) before implementing rest of phase
- [ ] 5.5 Merge to main runs docker-build (latest/SHA tags) without triggering deploy-production
- [ ] 5.6 Version tag push re-runs docker-build with tag image, then deploy-production runs and up -d --wait (or fallback) blocks until healthy, curl -f smoke test passes
- [ ] 5.7 Manual browser visit to https://planner.msolve.it confirms app serving correctly after tagged deploy
- [ ] 5.8 No extended downtime window observed during tagged deploy vs old down-then-up behavior
- [ ] 5.9 Manual rollback runbook validated by redeploying an older tag once
- [ ] 5.10 DEPLOYMENT.md sections accurately describe tag-gated flow and IMAGE_TAG mechanism
