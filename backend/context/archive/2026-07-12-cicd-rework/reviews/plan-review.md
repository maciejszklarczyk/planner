<!-- PLAN-REVIEW-REPORT -->
# Plan Review: CI/CD Rework Implementation Plan

- **Plan**: `context/changes/cicd-rework/plan.md`
- **Mode**: Deep
- **Date**: 2026-07-12
- **Verdict**: REVISE (all findings fixed during triage — see Decisions below)
- **Findings**: 0 critical, 2 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | WARNING |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding

3/3 paths ✓ (`backend/.gitlab-ci.yml`, `backend/docker-compose.prod.yaml`, `backend/.env.example`), 6/6 line-refs spot-checked ✓ (`.gitlab-ci.yml:126-127`, `:60`, `:83-84/95-96`, `:108-109/132-133` all match plan/research citations), brief↔plan ✓

## Findings

### F1 — Deploy cuts traffic to new containers before migration is verified

- **Severity**: ⚠️ WARNING
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Blind Spots
- **Location**: Phase 2, item 2 (`deploy-production`)
- **Detail**: Script order (`.gitlab-ci.yml:120-128`) is `pull` → `down` → `up -d` → `sleep 10` → `migrate` → `cache:clear`. Removing `|| true` makes migration failure fail the job, but by that point the new image is already serving production traffic. "Loud failure" happens after the damage, not before it — the plan's stated goal ("trustworthy deploy gate") is only partially met.
- **Fix A ⭐ Recommended**: Document the residual risk explicitly in Phase 2.
  - Strength: Cheap, matches plan's existing scope discipline (no rollback tooling was already an explicit non-goal).
  - Tradeoff: Doesn't reduce actual outage risk on a bad migration.
  - Confidence: HIGH.
  - Blind spot: None significant.
- **Fix B**: Reorder — run migration via one-off container before cutover.
  - Strength: Blocks cutover on migration failure, real safety improvement.
  - Tradeoff: `down` tears down the whole stack (php+db+redis); needs care to isolate the one-off run — a real script rewrite.
  - Confidence: MEDIUM — mechanism sound but current pattern unaudited for this.
  - Blind spot: Contention between one-off migration run and still-live db/redis during the window.
- **Decision**: FIXED via Fix A. Added residual-risk paragraph to Phase 2 Overview.

### F2 — Stale setup doc still tells operators to use `.env.prod`

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Completeness
- **Location**: `docs/DOCKER-EXECUTOR-SETUP.md`
- **Detail**: This doc (lines 69-86) instructs creating `/opt/plan-backend/.env.prod` from a `.env.prod.example` file that doesn't exist in the repo — the more detailed, more authoritative source of the exact mistake Phase 3 exists to prevent. Also has a stale `DEPLOY_DIR` (`/opt/plan-backend` vs. actual `/home/maciej/docker/apps/planner/backend`) and describes `deploy-production` as manual-trigger, which no longer matches the current fully-automatic job.
- **Fix A ⭐ Recommended**: Expand Phase 3 to also correct this doc.
  - Strength: Actually closes the confusion Phase 3 claims to close.
  - Tradeoff: Slightly larger Phase 3 diff.
  - Confidence: HIGH — all three facts independently verifiable against current `.gitlab-ci.yml`.
  - Blind spot: Doc may have other stale sections beyond these three; full audit not done.
- **Fix B**: Leave the doc, add to "What We're NOT Doing" as a known gap.
  - Strength: Keeps Phase 3 minimal.
  - Tradeoff: Operators following this doc still make the mistake Phase 3 exists to prevent.
  - Confidence: MEDIUM.
  - Blind spot: Unknown how actively this doc is consulted.
- **Decision**: FIXED via Fix A. Expanded Phase 3 (title, Overview, new "Changes Required" item 2, Success Criteria, Progress rows 3.1-3.4) to correct `.env.prod`/`.env.prod.example`, `DEPLOY_DIR`, and manual-trigger references in `docs/DOCKER-EXECUTOR-SETUP.md`.

### F3 — Tag push redundantly rebuilds/repushes `:latest` and `:sha`

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Lean Execution
- **Location**: Phase 2, item 1 (`docker-build`)
- **Detail**: `docker-build` unconditionally rebuilds+pushes `:latest` and `:$CI_COMMIT_SHORT_SHA` regardless of trigger; tagging an already-built main commit re-pushes both redundantly. Research's Open Questions flagged this and the plan left it unresolved.
- **Fix**: Keep the local `docker build` (still tags `:latest`/`:sha` locally, needed for the tag-push step), but make the `docker push` of `:latest`/`:sha` conditional on `$CI_COMMIT_TAG` being unset.
- **Decision**: FIXED. Updated Phase 2 item 1 contract with conditional push logic.

### F4 — `.dockerignore` gap from research isn't addressed or scoped out

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: "What We're NOT Doing"
- **Detail**: Research flagged that `.env.local`/`.env.test`/`.env.dev` are excluded from the Docker build context but base `.env` isn't (low risk in CI, real for local manual builds). Plan's "What We're NOT Doing" list didn't mention it — silently dropped rather than a deliberate scope call.
- **Fix**: Add a line to "What We're NOT Doing" acknowledging this known, separately-tracked gap.
- **Decision**: FIXED. Added bullet to "What We're NOT Doing".
