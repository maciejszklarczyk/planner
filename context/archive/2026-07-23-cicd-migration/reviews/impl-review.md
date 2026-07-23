<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: CI/CD Migration — GitLab CI → GitHub Actions

- **Plan**: context/changes/cicd-migration/plan.md
- **Scope**: Phase 1-3 of 3 (full plan)
- **Date**: 2026-07-23
- **Verdict**: NEEDS ATTENTION → all findings resolved during triage (see Decisions below); PR #1 confirms all 7 backend-ci + frontend-ci jobs pass on GitHub
- **Findings**: 0 critical, 5 warnings (F1, F2, F3, F4, F6 — F6 discovered mid-triage), 1 observation (F5) — all FIXED

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Findings

### F1 — Unpinned `:latest` tag on gitleaks Docker image

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: .github/workflows/backend-ci.yml:113
- **Detail**: `docker run ... zricethezav/gitleaks:latest detect ...` floats on `latest`. A new gitleaks release could silently change detection rules or behavior between runs, making the secret-scan job non-reproducible — the exact class of problem this plan's own Critical Implementation Details section called out when rejecting `gitleaks-action@v2` for its other risks.
- **Fix**: Pin to a specific released tag, e.g. `zricethezav/gitleaks:v8.21.2`.
- **Decision**: FIXED — pinned to `zricethezav/gitleaks:v8.30.1` (resolved current release)

### F2 — No `permissions:` block in either workflow

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: .github/workflows/backend-ci.yml, .github/workflows/frontend-ci.yml (workflow root)
- **Detail**: Neither file declares `permissions:`, so both inherit the default `GITHUB_TOKEN` scope (broad read/write on same-repo pushes). No step currently uses the token, so nothing is exploitable today — but it's more privilege than these jobs need, and the gap is easy to close now versus after a future job starts using the token.
- **Fix**: Add `permissions: contents: read` at the root of both workflow files.
- **Decision**: FIXED — added to both workflows

### F3 — Automated criteria 1.2/2.2 checked as done via local proxy, not a real GitHub Actions run

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: context/changes/cicd-migration/plan.md (Progress, rows 1.2 and 2.2)
- **Detail**: During implementation, "on a test PR, jobs complete and report status to GitHub" was substituted with running each job's exact commands locally (documented at the time as an adaptation, not hidden) — pushing a branch and opening a PR was treated as a shared-system action requiring explicit go-ahead. The Progress rows are checked `[x]`, but the literal criterion — GitHub Actions actually executing these workflows and reporting real check status — has never been exercised.
- **Fix A ⭐ Recommended**: Push a branch and open a real PR now to exercise both workflows end-to-end
  - Strength: Closes the actual gap — confirms the YAML runs on GitHub's hosted runners, `docker` is available for the secret-scan job there, and both workflows report status correctly, which local execution cannot prove.
  - Tradeoff: Requires explicit go-ahead to push/open a PR (a shared-system action) and costs a few minutes of CI time.
  - Confidence: HIGH — this is the only way to close criteria 1.2/2.2 as literally written.
  - Blind spot: None significant — this was already the plan's own manual-verification step (1.5, 2.4, 2.5, 3.4), just surfaced here as a Success Criteria gap too.
- **Fix B**: Leave as-is, rely on the pending Manual rows (1.5, 1.6, 2.4, 2.5, 3.4, 3.5) to catch this
  - Strength: No immediate action needed; the plan already has manual rows tracking exactly this.
  - Tradeoff: The Automated rows overstate what's actually verified until someone does open a PR — a future reader of plan.md could mistake "1.2 [x]" for "confirmed on GitHub."
  - Confidence: MED — relies on the Manual checklist actually being worked through.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A) — pushed branch `ci-migration-verify`, opened https://github.com/maciejszklarczyk/planner/pull/1. First run surfaced a real, previously-unverifiable issue (see F6 below); after fixing it, all 7 jobs pass on both `push` and `pull_request` events.

### F6 — (discovered via live PR run) secret-scan failed on pre-existing false positives

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality / Success Criteria
- **Location**: .github/workflows/backend-ci.yml (secret-scan job); backend/.env.example:6, backend/docs/DOCKER-EXECUTOR-SETUP.md:81, backend/requests/users.http:60,68, backend/docs/codebase/.codebase-scan.txt:386
- **Detail**: The real GitHub Actions run of PR #1 (https://github.com/maciejszklarczyk/planner/pull/1) found 5 gitleaks matches on a fresh 11.37MB checkout — the `.env.example` placeholder `APP_SECRET=ZMIEN_NA_LOSOWY_SECRET_64_ZNAKI` (also echoed in a doc and a stale codebase-scan artifact), and two example invitation-token strings in a manual-testing `.http` request file. None are real secrets, but as configured the job would fail on every run. This was only discoverable by actually running gitleaks against the real repo content on GitHub — exactly the gap F3 was about.
- **Fix**: Add `.gitleaks.toml` with `[extend] useDefault = true` plus a regex allowlist for the two specific known-safe strings, and point `secret-scan`'s `docker run` at it via `--config /repo/.gitleaks.toml`.
- **Decision**: FIXED — added `.gitleaks.toml`, verified 0 leaks against a clean archive of `main` (matching CI's fresh-checkout scan exactly), then confirmed green on the live PR (all 7 jobs pass on both push and pull_request events).

### F4 — `composer-audit` job omits the extensions/vendor setup the plan's "every job" wording implies

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: .github/workflows/backend-ci.yml (composer-audit job)
- **Detail**: Plan's Phase 1 contract says "Every job: ... → `actions/cache@v4` ... → `composer install`..." The `composer-audit` job skips the cache step and `composer install` entirely, running `composer audit --locked` against just the checked-out `composer.lock`. This is functionally correct (audit only reads the lockfile, doesn't need vendor/ or PHP extensions) and arguably a sensible simplification, but it's a literal deviation from the plan's stated contract.
- **Fix**: Either accept as an intentional simplification (update the plan's contract wording retroactively to note the exception), or add the same setup-php/cache/install steps for strict consistency even though they're unused.
- **Decision**: FIXED (Fix differently) — added full setup-php/cache/composer-install steps to composer-audit, matching every other job

### F5 — No `concurrency:` group defined in either workflow

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: .github/workflows/backend-ci.yml, .github/workflows/frontend-ci.yml (workflow root)
- **Detail**: Rapid successive pushes to the same branch/PR queue redundant runs instead of canceling stale ones — not a regression from GitLab (which didn't have this either), just an easy improvement now that we're here.
- **Fix**: Add `concurrency: { group: ${{ github.workflow }}-${{ github.ref }}, cancel-in-progress: true }`.
- **Decision**: FIXED — added to both workflows
