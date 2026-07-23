<!-- PLAN-REVIEW-REPORT -->
# Plan Review: CI/CD Rework Implementation Plan

- **Plan**: `context/changes/cicd-rework/plan.md`
- **Mode**: Deep
- **Date**: 2026-07-13
- **Verdict**: REVISE (all findings fixed during triage — see Decisions below)
- **Findings**: 3 critical, 1 warning, 0 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | FAIL |
| Plan Completeness | FAIL |

## Grounding

5/5 paths verified (`.gitlab-ci.yml`, `vitest.config.ts`, `Dockerfile`, `docker-compose.prod.yaml`, `package.json`), symbols confirmed (`useDeleteGroup.test.ts`), brief↔plan consistent. Deep-mode sub-agent additionally verified: Compose `--wait` support unproven in-repo, `docker-compose.prod.yaml` image-field blast radius (only `DEPLOYMENT.md`, no herald.sh reference), GitLab Secret Detection template runs on its own default image/tags (not `docker:latest`/`tags:[docker]`), and `proxy.ts`'s matcher explicitly excludes `/api/*` (no interception risk for the new health route).

## Findings

### F1 — Compose `--wait` support is unverified, and there's no fallback

- **Severity**: ❌ CRITICAL
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Blind Spots / End-State Alignment
- **Location**: Phase 5, item 6 (Deploy sequence)
- **Detail**: `up -d --wait --wait-timeout 60` requires Compose V2 ≥2.17. The deploy job's `before_script` runs `apk add --no-cache docker-compose` — Alpine's package is historically the legacy V1 Python CLI. `backend/docs/DOCKER-EXECUTOR-SETUP.md:162-169` poses this exact "what Compose version is on the runner" question as an unresolved manual test with no recorded result anywhere in the repo. Backend's own separate CI/CD rework (`backend/context/archive/2026-07-12-cicd-rework/plan.md:114`) hit this exact down→up cutover-safety problem and explicitly deferred it as "residual risk... out of scope" rather than attempting `--wait` — no in-repo precedent proves this flag works here. Unlike Phase 3's BuildKit caching, Phase 5's `--wait` had no documented fallback.
- **Fix A ⭐ Recommended**: Verify Compose version on the runner before Phase 5 starts; proceed with `--wait` only if confirmed ≥2.17; otherwise fall back to a curl-retry polling loop (matches backend's proven pattern).
  - Strength: Cheap, fast confirmation before touching production deploy behavior.
  - Tradeoff: Adds a small manual verification step before Phase 5 can start.
  - Confidence: HIGH — risk directly evidenced by the repo's own unresolved test note.
  - Blind spot: Can't verify remotely from this session; must be checked against the actual runner.
- **Fix B**: Skip `--wait` entirely, use a curl-retry polling loop unconditionally.
  - Strength: Works regardless of Compose version.
  - Tradeoff: Slightly more script complexity than a single flag.
  - Confidence: HIGH — well-precedented in this repo.
  - Blind spot: None significant.
- **Decision**: FIXED (via Fix A — added item 0 "Verify Compose `--wait` support on the runner" to Phase 5 Changes Required, with the curl-retry loop specified as the fallback path; added matching Success Criteria + Progress 5.4)

### F2 — IMAGE_TAG substitution has no specified fallback — backend already made and fixed this exact mistake

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 5, item 5 (Deploy gating and image reference)
- **Detail**: Plan said to reference `${IMAGE_TAG}` with no fallback syntax specified. Backend's archived `2026-07-12-cicd-rework` change first shipped `${IMAGE_TAG:-latest}` (`backend/.../plan.md:155`), then impl-review flagged it (`backend/.../reviews/impl-review.md:29`) as silently reintroducing the floating `:latest` tag the whole rework exists to kill, and fixed it to `${IMAGE_TAG:?IMAGE_TAG must be set}`.
- **Fix**: Specify `${IMAGE_TAG:?IMAGE_TAG must be set}` explicitly in Phase 5 item 5's contract, matching backend's corrected syntax.
- **Decision**: FIXED (Phase 5 item 5's contract now specifies `registry.gitlab.com/planner6551704/frontend:${IMAGE_TAG:?IMAGE_TAG must be set}` with the backend precedent cited inline)

### F3 — Progress 5.8 has no corresponding Phase 5 Success Criteria bullet

- **Severity**: ❌ CRITICAL (mechanical — `/10x-implement` contract)
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Progress → Phase 5 / Phase 5 Manual Verification
- **Detail**: `## Progress` listed 5.8 "Manual rollback runbook validated by redeploying an older tag once" but Phase 5's own Manual Verification list had no matching bullet — it was pulled from the separate "Manual Testing Steps" section instead.
- **Fix**: Add a matching bullet to Phase 5's Manual Verification list.
- **Decision**: FIXED (bullet added to Phase 5 Manual Verification; Progress renumbered as 5.9 after F1's insertion added 5.4)

### F4 — DEPLOYMENT.md not updated, will actively mislead an operator

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Completeness
- **Location**: Phase 5 (no changes-required item for this file)
- **Detail**: `frontend/DEPLOYMENT.md` documents the current deploy/rollback flow (auto-deploy on main push; manually editing the `image:` line for rollback) — both now wrong post-Phase-5. An operator following the stale doc during a real incident gets wrong instructions, worst-case during rollback.
- **Fix**: Add a Phase 5 changes-required item updating `DEPLOYMENT.md`'s Automatic Build/Manual Deployment and Rollback sections to describe the tag-gated flow and `IMAGE_TAG` mechanism.
- **Decision**: FIXED (added item 7 "Update DEPLOYMENT.md" to Phase 5 Changes Required; added matching Success Criteria + Progress 5.10)
