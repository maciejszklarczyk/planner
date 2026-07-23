# CI/CD Migration: GitLab → GitHub Actions — Plan Brief

> Full plan: `context/changes/cicd-migration/plan.md`
> Research: `context/changes/cicd-migration/research.md`

## What & Why

Migrate CI from GitLab to GitHub Actions for both the backend (Symfony/PHP) and frontend (Next.js) projects. Deployment (`docker-build`, `deploy-production`) is explicitly out of scope — this covers build/test/quality checks only.

## Starting Point

Three GitLab CI files exist: a root `.gitlab-ci.yml` (parent-child pipeline trigger, added today during the monorepo migration) plus `backend/.gitlab-ci.yml` and `frontend/.gitlab-ci.yml`, each with their own build/test/lint/docker-build/deploy stages. Neither has path filtering — both always run.

## Desired End State

Two GitHub Actions workflows (`.github/workflows/backend-ci.yml`, `.github/workflows/frontend-ci.yml`) run on every push/PR and replicate all in-scope GitLab jobs. All three `.gitlab-ci.yml` files are deleted in the same change, and the one doc that names `.gitlab-ci.yml` explicitly (`backend/CLAUDE.md:36`) is updated.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
|---|---|---|---|
| Path filtering | Always run both workflows, no `paths:` filter | Preserves today's exact behavior — avoids branch-protection surprises during migration | Plan |
| Coverage reporting | Keep `--coverage-text` in logs, no PR-comment action | Ships faster, avoids a new third-party action dependency | Plan |
| Secret scanning | Include now, but via direct `gitleaks` Docker CLI, not `gitleaks-action` | Avoids org-license ambiguity and the action's looming Sept 2026 Node-20-runner deprecation | Plan |
| `ext-gd` | Install via `setup-php`, drop `--ignore-platform-req` | Matches prod Dockerfile; avoids a silent gap for when avatar tests are added | Plan |
| Node version | 20 (matches `frontend/Dockerfile`) | Avoids CI/deploy-image drift; sibling project's Node 22 precedent isn't binding here | Plan |
| Cutover strategy | Immediate — delete GitLab files in this change | Clean, forces branch-protection update now instead of it being forgotten later | Plan |
| Postgres/Redis services | None — omit `services:` block entirely | `backend/.env.test` uses SQLite + array cache; a DB/cache container would be pure overhead | Research |

## Scope

**In scope:**
- `backend-ci.yml`: composer install+cache, php-cs-fixer, phpstan, phpunit (Cobertura, no PR comment), composer-audit, lint, secret scan (gitleaks CLI)
- `frontend-ci.yml`: quality-checks (npm ci, lint, tsc, test, format:check)
- Deleting all three `.gitlab-ci.yml` files
- Updating `backend/CLAUDE.md`'s stale `.gitlab-ci.yml` reference

**Out of scope:**
- `docker-build` / `deploy-production` jobs (either project)
- Path-based workflow filtering
- PR-comment coverage reporting
- Running GitLab CI and GitHub Actions in parallel
- GitHub branch-protection reconfiguration (flagged as a required manual step, not a code change)
- `frontend/DEPLOYMENT.md` and the stale `frontend/context/changes/cicd-rework/` folder

## Architecture / Approach

One workflow file per subproject, mirroring the current GitLab split 1:1. Each job installs its own toolchain (`shivammathur/setup-php` for backend, `actions/setup-node` for frontend) with dependency caching (`actions/cache` / `setup-node`'s built-in npm cache), since GitHub Actions jobs don't share a filesystem the way GitLab's artifact-passing did.

## Phases at a Glance

| Phase | What it delivers | Key risk |
|---|---|---|
| 1. Backend CI Workflow | `.github/workflows/backend-ci.yml` with all 6 backend checks + secret scan | Secret-scan step reimplemented via raw Docker CLI instead of the marketplace action — needs care to match exit-code semantics |
| 2. Frontend CI Workflow | `.github/workflows/frontend-ci.yml` with quality-checks | Low risk — near-identical command set to GitLab |
| 3. Cutover & Doc Cleanup | GitLab files removed, CLAUDE.md updated | Branch protection required-checks must be updated manually or PRs could get stuck unable to merge |

**Prerequisites:** Phases 1–2 should both be verified green on a real PR before Phase 3 deletes the GitLab files.
**Estimated effort:** ~1 session, 3 phases.

## Open Risks & Assumptions

- Assumes this repo (`maciejszklarczyk/planner`) stays a personal-account repo, not a GitHub org — moot anyway since we're avoiding `gitleaks-action`'s licensing question entirely by using the CLI directly.
- Branch protection required-status-checks must be updated manually in GitHub repo settings after Phase 3 — not something a file change can do; flagged as a manual verification step.
- No PR-comment coverage badge — coverage % is now log-only, a minor visibility regression vs. GitLab's MR widget, accepted as a scope tradeoff.

## Success Criteria (Summary)

- A PR touching both `backend/` and `frontend/` shows GitHub Actions checks only, all green, with equivalent enforcement to the old GitLab pipeline (each deliberately-broken check fails its job).
- No `.gitlab-ci.yml` file remains anywhere in the repo.
- `backend/CLAUDE.md` points contributors at the correct (GitHub Actions) file for the composer-audit convention.
