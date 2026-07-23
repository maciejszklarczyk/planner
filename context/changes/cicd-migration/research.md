---
date: 2026-07-23T22:15:54+02:00
researcher: Maciej Szklarczyk
git_commit: 382714f7296f4dc922a9eb766ee8a70fb4ae4f7c
branch: main
repository: maciejszklarczyk/planner
topic: "Migrate CI/CD from GitLab CI to GitHub Actions (backend + frontend, deployment out of scope)"
tags: [research, codebase, ci-cd, gitlab, github-actions, backend, frontend]
status: complete
last_updated: 2026-07-23
last_updated_by: Maciej Szklarczyk
last_updated_note: "Added cross-project reference from ../car-repair-tracker's existing GitHub Actions setup"
---

# Research: Migrate CI/CD from GitLab CI to GitHub Actions

**Date**: 2026-07-23T22:15:54+02:00
**Researcher**: Maciej Szklarczyk
**Git Commit**: [382714f](https://github.com/maciejszklarczyk/planner/blob/382714f7296f4dc922a9eb766ee8a70fb4ae4f7c)
**Branch**: main
**Repository**: maciejszklarczyk/planner

## Research Question

We need to migrate CI/CD from GitLab to GitHub. The old CI/CD configs live in `backend/` and `frontend/`. Deployment is entirely out of scope for this migration.

Scoping decisions made before research (via user Q&A):
- **Secret detection**: research a concrete GitHub replacement (not just note the gap).
- **Docker build/push**: out of scope — only migrate build/test/quality-check jobs, not `docker-build` or `deploy-production`.
- **Prior cicd-rework history**: read for context, explicitly flagged by the user as outdated (pre-GitHub-migration, pre-monorepo).

## Summary

The repo runs three GitLab CI files today: a root `.gitlab-ci.yml` that triggers two **child pipelines** via `trigger:`/`strategy: depend` (introduced today, 2026-07-23, during the monorepo migration — see Historical Context), and `backend/.gitlab-ci.yml` / `frontend/.gitlab-ci.yml`, each with their own `build/test/lint/docker-build/deploy` stages. There is **no path filtering** anywhere — both child pipelines run on every push, unconditionally.

For this migration, only the non-deploy stages are in scope:
- **Backend**: `composer` (install), `php-cs-fixer`, `phpstan`, `phpunit` (with Cobertura coverage), `composer-audit`, `lint` (yaml + container), plus GitLab's `Security/Secret-Detection.gitlab-ci.yml` template job.
- **Frontend**: single `quality-checks` job (`npm ci`, lint, `tsc --noEmit`, test, format:check).

Key finding that changes an assumption going in: **`phpunit` does not need Postgres/Redis service containers**. `backend/.env.test` is configured for SQLite + array/filesystem cache — "Redis is NOT used in tests" is stated explicitly in that file. A GHA `services:` block for Postgres/Redis would be pure overhead unless the test suite changes.

GitHub Actions has no 1:1 equivalent for GitLab's `Security/Secret-Detection.gitlab-ci.yml` template; the closest replacement is `gitleaks/gitleaks-action@v2`. There's also no native Cobertura-coverage MR-widget equivalent — closest options are PR-comment actions (`irongut/CodeCoverageSummary`, `5monkeys/cobertura-action`) that read the same `coverage.xml` already produced by `phpunit --coverage-cobertura`.

No document in the repo states an intent to move to GitHub Actions — this migration isn't a continuation of a previously-planned effort, it's a fresh decision.

## Detailed Findings

### Current GitLab CI structure

**Root `.gitlab-ci.yml`** ([.gitlab-ci.yml](https://github.com/maciejszklarczyk/planner/blob/382714f7296f4dc922a9eb766ee8a70fb4ae4f7c/.gitlab-ci.yml)):
```yaml
stages:
  - trigger

backend:
  stage: trigger
  trigger:
    include: [{ local: 'backend/.gitlab-ci.yml' }]
    strategy: depend

frontend:
  stage: trigger
  trigger:
    include: [{ local: 'frontend/.gitlab-ci.yml' }]
    strategy: depend
```
Both children are triggered unconditionally, no `rules:`/`only:`/path filtering. `strategy: depend` propagates child pipeline pass/fail status to the parent.

**`backend/.gitlab-ci.yml`** ([backend/.gitlab-ci.yml](https://github.com/maciejszklarczyk/planner/blob/382714f7296f4dc922a9eb766ee8a70fb4ae4f7c/backend/.gitlab-ci.yml)) — stages `build, test, secret-detection, lint, docker-build, deploy`:
- `composer` (build): `composer install --no-interaction --prefer-dist --ignore-platform-req=ext-gd`, caches/artifacts `backend/vendor/` keyed on `backend/composer.lock`.
- `php-cs-fixer`, `phpstan`, `phpunit`, `composer-audit` (test stage, `needs: [composer]` except audit).
  - `phpunit`: `vendor/bin/phpunit --coverage-cobertura=coverage.xml --coverage-text`, installs `pcov` at runtime via `pecl install pcov`, GitLab `coverage:` regex `Lines:\s{3,}(\d+\.\d+)%` parses the text summary for the MR badge.
- `secret_detection` (secret-detection stage): `include: template: Security/Secret-Detection.gitlab-ci.yml`, gated by `SECRET_DETECTION_ENABLED: 'true'`.
- `lint` (lint stage, `needs: [composer]`): `composer validate`, `php bin/console lint:yaml config/`, `php bin/console lint:container`.
- `docker-build` / `deploy-production` (out of scope, not migrated).

**`frontend/.gitlab-ci.yml`** ([frontend/.gitlab-ci.yml](https://github.com/maciejszklarczyk/planner/blob/382714f7296f4dc922a9eb766ee8a70fb4ae4f7c/frontend/.gitlab-ci.yml)) — stages `quality, docker-build, deploy`:
- `quality-checks` (quality stage, `image: node:20-alpine`): `npm ci`, `npm run lint`, `npx tsc --noEmit`, `npm run test`, `npm run format:check`.
- `docker-build` / `deploy-production` (out of scope, not migrated).

### Backend → GitHub Actions equivalents

- **PHP runtime**: `shivammathur/setup-php@v2`, pin `php-version: '8.4'` (matches `dunglas/frankenphp:1-php8.4` in prod and `composer.json`'s `>=8.4` requirement), `tools: composer:v2`.
- **Extensions**: `ext-gd` is genuinely used ([src/Controller/UserAvatarController.php](https://github.com/maciejszklarczyk/planner/blob/382714f7296f4dc922a9eb766ee8a70fb4ae4f7c/backend/src/Controller/UserAvatarController.php) calls `imagecreatefrompng/webp/gif/jpeg`) but no test currently exercises avatar upload — that's why GitLab's bare `composer:2.9.4` image gets away with `--ignore-platform-req=ext-gd`. On `setup-php` it's cheap to just install `gd` (`extensions: gd`) and drop the ignore flag, matching prod's `docker/php/Dockerfile`. `pcov` should only be requested (`coverage: pcov`) on the `phpunit` job — omit elsewhere for speed.
- **Composer install + caching**: no shared filesystem between GHA jobs like GitLab's artifact-passing. Preferred: `actions/cache@v4` keyed on `hashFiles('backend/composer.lock')`, re-run `composer install` in each job (fast on cache hit). Alternative (slower, not recommended): `actions/upload-artifact` + `download-artifact` for `backend/vendor/`.
- **php-cs-fixer / phpstan / composer-audit**: unchanged commands as plain `run:` steps — provider-agnostic.
- **phpunit**: no `services:` block needed — `backend/.env.test` uses SQLite and explicit array/filesystem cache, not Postgres/Redis. `APP_ENV=test` is already forced via `phpunit.dist.xml`'s `<server name="APP_ENV" value="test" force="true"/>`, no extra env export needed. Coverage: no native GitHub equivalent of GitLab's inline `coverage:` regex/MR-widget; closest replacements read the same Cobertura `coverage.xml`: `irongut/CodeCoverageSummary@v1.3.0` or `5monkeys/cobertura-action@v14` (PR-comment coverage table, optional fail-under threshold).
- **lint** (`composer validate`, `lint:yaml`, `lint:container`): unchanged commands, no extra services — GitLab job doesn't export special env either, relies on committed `.env`/`.env.local`.
- **Secret detection**: `gitleaks/gitleaks-action@v2` is the closest 1:1 replacement for GitLab's `Security/Secret-Detection.gitlab-ci.yml` template. Minimal config just needs `GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}`; verify current licensing terms for private-repo PR-comment features before adopting (this has changed over time). `trufflesecurity/trufflehog@main --only-verified` is a lower-noise alternative.

### Frontend → GitHub Actions equivalents

- **Node runtime**: `actions/setup-node@v4`, `node-version: '20'` — no `engines` field or `.nvmrc` in `frontend/package.json`, but `frontend/Dockerfile` pins `node:20-alpine` for the build stage, so matching 20 keeps CI/deploy-image parity. Node 22 (current Active LTS) is a future option, not forced by anything in-repo.
- **Caching**: `actions/setup-node@v4`'s built-in `cache: 'npm'` + `cache-dependency-path: frontend/package-lock.json` (explicit path required since the lockfile isn't at repo root) — simpler than manual `actions/cache`, sufficient since there's no `next build` step in scope (no `.next/cache` to worry about).
- **Steps**: `npm ci`, `npm run lint`, `npx tsc --noEmit`, `npm run test`, `npm run format:check` — all unchanged as plain `run:` steps, all should gate the job (no `continue-on-error`).
- **Vitest GitHub annotations**: vitest (installed per `frontend/CLAUDE.md` conventions) ships a built-in `--reporter=github-actions` for inline PR test-failure annotations — zero-config, worth adding: `npm run test -- --reporter=default --reporter=github-actions`.
- **ESLint annotations**: no equivalent zero-config annotation path; a lint failure just fails the job and surfaces in the log, same effective behavior as GitLab.
- **`NEXT_PUBLIC_API_URL`**: not needed for `quality-checks`. Only `lib/api.ts` and `SetPasswordForm.tsx` read it, `lib/api.ts` already falls back to `http://localhost:8000`, and per `frontend/CLAUDE.md` conventions all tests mock `lib/api.ts` at the module boundary rather than making real fetch calls — confirmed no test file references the env var. It's purely a Next.js build-time (`docker build --build-arg`) concern, irrelevant here.

### Monorepo path filtering (open question, not a decision)

Today, both `backend/` and `frontend/` child pipelines run on **every** push — the root `.gitlab-ci.yml` has no path-based conditioning. Two real options exist for GitHub Actions and neither is a "given":
1. **Preserve current behavior** — no `paths:` filter, both workflows always run. Matches existing semantics exactly.
2. **Introduce path filtering** — `on.push.paths: ['backend/**']` / `['frontend/**']` per workflow, or a single workflow using `dorny/paths-filter@v3` to conditionally fan out. This is a genuine behavior change (not free): with required-status-check branch protection, a path-filtered workflow that never triggers on an unrelated PR also never reports success, which can block merges unless configured carefully.

This should be an explicit decision in the plan, not an implicit side effect of the migration.

### Workflow file structure

Recommend mirroring the current split 1:1: `.github/workflows/backend-ci.yml` and `.github/workflows/frontend-ci.yml`, each with parallel jobs (`secret-detection`, `composer`/setup, `php-cs-fixer`, `phpstan`, `phpunit`, `composer-audit`, `lint` for backend; single `quality-checks` job for frontend, or split into per-step jobs if faster parallel feedback is wanted).

## Code References

- `.gitlab-ci.yml` — root parent pipeline, unconditional trigger of both children, no path filtering
- `backend/.gitlab-ci.yml:1-129` — full backend pipeline (build/test/secret-detection/lint/docker-build/deploy)
- `backend/.gitlab-ci.yml:38-53` — `phpunit` job, Cobertura coverage + regex-based coverage badge
- `backend/.gitlab-ci.yml:15-18` — `secret_detection` job including GitLab's `Security/Secret-Detection.gitlab-ci.yml` template
- `backend/.env.test` — SQLite DB, explicit "Redis is NOT used in tests" comment — the reason no `services:` block is needed for `phpunit` in GHA
- `backend/phpunit.dist.xml` — forces `APP_ENV=test`, no extra env export needed in CI
- `backend/src/Controller/UserAvatarController.php` — confirms `ext-gd` is genuinely used at runtime (image resizing), even though no test currently exercises it
- `backend/docker/php/Dockerfile` — prod image installs `gd`, `pdo_pgsql`, `redis`, `xdebug`; useful parity reference for `setup-php` extensions list
- `frontend/.gitlab-ci.yml:1-38` — full frontend pipeline (quality/docker-build/deploy)
- `frontend/.gitlab-ci.yml:12-23` — `quality-checks` job (lint, typecheck, test, format:check)
- `frontend/Dockerfile` — pins `node:20-alpine`, the basis for keeping Node 20 in CI
- `frontend/CLAUDE.md` — testing conventions: Vitest + RTL, `lib/api.ts` mocked at module boundary, no real network calls in tests

## Architecture Insights

- The monorepo's root pipeline uses **parent-child GitLab pipelines** (`trigger: include: local:`, `strategy: depend`) specifically to give backend and frontend separate job/stage/variable namespaces — a flat `include: local:` was tried and rejected earlier today because both subprojects define same-named jobs (`docker-build`, `deploy-production`) and top-level `stages:`/`variables:`, which silently collide (see Historical Context). This namespacing problem doesn't exist in GitHub Actions, since each `.github/workflows/*.yml` file is already its own independent workflow — no parent/child indirection is needed there.
- Both apps' current GitLab pipelines have **no path filtering** — CI cost/scoping was never a concern GitLab-side. GitHub Actions migration is the first natural point to introduce it, but doing so changes observable behavior (branch protection interaction) and should be called out explicitly rather than adopted for "consistency" reasons alone.
- Backend and frontend test suites are **fully self-contained** — no external service dependencies (SQLite + array cache backend-side, mocked API client frontend-side) — meaning GHA jobs can run on bare `ubuntu-latest` runners with no `services:` block for either app, keeping the migration comparatively simple relative to a typical Postgres+Redis-backed Symfony CI setup.

## Historical Context (from prior changes)

Two earlier GitLab CI/CD reworks exist in the repo's change history — both flagged by the user as **outdated** (pre-monorepo, pre-GitHub-migration) but useful for rationale:

- `backend/context/archive/2026-07-12-cicd-rework/research.md` — the original backend pipeline auto-deployed to production on *every push to `main`*, with a migration/cache-clear step that silently swallowed failures via `|| true` (a broken migration could leave prod half-updated while the pipeline still reported green). Also flagged: redundant `composer install` across jobs, legacy `only`/`except` syntax with no `rules:`, and the deployed image was always the floating `:latest` tag.
- `frontend/context/changes/cicd-rework/research.md` — frontend's pipeline gated every job on `only: [main]` (nothing validated code before merge), had no caching, no security scanning, no test/coverage artifacts, and deploy was hard-downtime with a fake healthcheck (`node -v` instead of an HTTP probe).
- Both reworks converged on the same fix: gate `deploy-production` on `$CI_COMMIT_TAG` instead of `main` pushes, using protected tags as a deliberate release gate. Backend's rework reached `status: archived` with a full impl-review; **frontend's is stuck at `status: plan_reviewed`, `archived_at: null`** — a thorough, reviewed plan (497 lines, 3 critical plan-review findings all triaged) that appears never implemented. `docs/superpowers/plans/2026-07-23-monorepo-migration.md` corroborates this, noting ten days later that "frontend deploys automatically on merge to main — inconsistent with backend." The monorepo migration (today) then unified both services' `deploy-production` on the tag-gate rule, effectively subsuming the frontend rework's intended outcome via a different, later change — the abandoned plan folder is stale but not actively misleading.
- **No document anywhere states an intent to move to GitHub Actions.** The monorepo-migration design doc explicitly says "Remote GitLab dla monorepo to osobna decyzja na później" (staying on GitLab remote was deferred as a separate future decision) — this migration is a fresh decision, not a continuation of previously-planned work.
- Parent-child pipelines (current root `.gitlab-ci.yml` structure) were introduced *today*, during the monorepo migration, not during either cicd-rework change — `docs/superpowers/plans/2026-07-23-monorepo-migration.md` records that a flat `include: local:` approach was tried first and rejected for the job/stage/variable namespace collision reason described above.

## Related Research

- `backend/context/archive/2026-07-12-cicd-rework/` — plan, plan-brief, research, plan-review, impl-review for the backend GitLab CI rework
- `frontend/context/changes/cicd-rework/` — plan, plan-brief, research, plan-review for the (unimplemented) frontend GitLab CI rework
- `docs/superpowers/plans/2026-07-23-monorepo-migration.md` / `docs/superpowers/specs/2026-07-23-monorepo-migration-design.md` — today's monorepo migration, source of the current parent-child pipeline structure

## Follow-up Research 2026-07-23 (evening)

User pointed at a sibling project, `../car-repair-tracker` (outside this repo, path `/Users/maciejszklarczyk/Projects/car-repair-tracker`), which already runs GitHub Actions in production, including a custom self-hosted runner on the same homelab server this project would eventually deploy to. Read its workflows for reusable patterns.

### What's there

- **`.github/workflows/ci.yml`** — PR-triggered (`on.pull_request.branches: [main]`), four parallel-ish jobs: `lint` (`astro sync` + `npm run lint` + `astro check`), `test` (unit), `build` (`needs: [lint, test]`), `e2e` (Playwright, `if: github.event_name == 'pull_request'`). All use `actions/setup-node@v4` with `cache: npm` — no manual `actions/cache` needed, confirming the same pattern our backend/frontend research already recommended.
- **Playwright caching pattern** (`ci.yml` e2e job): version-pins the cache key to the installed Playwright version (`npx playwright --version`) via `actions/cache@v4`, conditionally skips `playwright install --with-deps` on cache hit but still runs `playwright install-deps` (OS libs) — not directly applicable here (no Playwright/E2E in scope for this migration), but a reusable pattern if this repo ever adds E2E to CI.
- **`.github/workflows/deploy.yml`** — `runs-on: self-hosted` **only on the final `deploy` job** (`docker`/`migrate` jobs run on `ubuntu-latest`; only the last step that needs homelab filesystem/Docker access uses the self-hosted runner). Triggered by `workflow_dispatch` + `release: types: [published]`, i.e. gated on GitHub Releases, not tags directly — a different gating mechanism than this project's current GitLab tag-regex rule (`$CI_COMMIT_TAG =~ /^v\d+\.\d+\.\d+$/`), worth a note for whenever deployment migration happens, but **out of scope for this change**.
- **`.github/workflows/demo-cleanup.yml`** — scheduled cron job (`schedule: cron: "0 1 * * *"`), unrelated to CI/CD migration, app-specific (deletes stale demo accounts). Not relevant here.
- **`.github/workflows/ai-review.yml`** + **`.github/actions/ai-reviewer/action.yml`** — a custom composite action for AI-assisted PR review. Not investigated in depth (out of scope — this migration is about build/test/lint parity, not adding new review tooling), but flagged as existing prior art in this GitHub org if such a thing is ever wanted later.
- **`context/foundation/infrastructure.md`** (car-repair-tracker) confirms the self-hosted runner exists specifically to reach the homelab Docker host for deploys — it explicitly frames "no CI/CD pipeline → manual deploys" and "self-hosted runner requires firewall changes" as real risks that were worked through for that project already. Since **this migration explicitly skips deployment**, the self-hosted runner is not needed for planner's backend-ci/frontend-ci workflows — `ubuntu-latest` is sufficient for all in-scope jobs (composer/php-cs-fixer/phpstan/phpunit/composer-audit/lint, npm ci/lint/tsc/test/format:check). Noting its existence for when a future change adds deployment back.

### Answered open question

- **Node version pin**: car-repair-tracker uses `node-version: 22` (Active LTS) in all its workflows, not 20. This project's frontend Dockerfile pins `node:20-alpine` — the two projects disagree. No hard blocker either way; recommend staying at 20 for parity with this repo's own Dockerfile unless there's a reason to diverge, but flagging that org precedent (car-repair-tracker) leans toward 22.

### Nothing new for backend (PHP/Symfony)

car-repair-tracker is a pure Node/Astro project with no PHP — nothing there maps directly to the backend (composer/phpstan/phpunit/gitleaks) side of this migration.

## Open Questions

- **Path filtering**: adopt `paths:` scoping per workflow (behavior change) or preserve today's always-run-both semantics? Needs an explicit decision before `/10x-plan`.
- **Coverage reporting**: which Cobertura PR-comment action (`irongut/CodeCoverageSummary` vs `5monkeys/cobertura-action`), or is a `$GITHUB_STEP_SUMMARY` grep of the existing regex sufficient for now?
- **Gitleaks licensing**: confirm current `gitleaks/gitleaks-action@v2` licensing terms for this repo's visibility (public/private) before committing to it in the plan.
- **`ext-gd` in CI**: install it via `setup-php` (`extensions: gd`) even though no test currently exercises avatar upload, for parity with prod — or leave it out since nothing currently depends on it in CI? (Recommend: include it, cheap and avoids surprise when avatar tests are eventually added.)
- **Frontend's stale `plan_reviewed` change folder**: leave `frontend/context/changes/cicd-rework/` as-is (harmless dead weight) or archive/note it as superseded by the monorepo migration while this migration work is in flight? Not blocking, but worth a decision at some point.
- **Node version — 20 vs 22**: this repo's frontend Dockerfile pins Node 20; sibling project `car-repair-tracker` uses 22 across all its workflows. Stick with 20 (parity with this repo's own Dockerfile) or align org-wide on 22? Not a blocker either way.
