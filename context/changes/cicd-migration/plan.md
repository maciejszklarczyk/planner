# CI/CD Migration: GitLab CI → GitHub Actions Implementation Plan

## Overview

Replace the project's GitLab CI pipelines with GitHub Actions workflows for backend (Symfony/PHP) and frontend (Next.js) build/test/quality checks. Deployment (`docker-build`, `deploy-production`) is explicitly out of scope — only the non-deploy stages migrate. Cutover happens in this same change: GitLab CI files are deleted once the GitHub Actions workflows are in place.

## Current State Analysis

Three GitLab CI files exist today:
- `.gitlab-ci.yml` (root) — triggers two child pipelines (`backend`, `frontend`) unconditionally via `trigger: include: local:` / `strategy: depend`, introduced during today's monorepo migration to give each subproject its own job/stage/variable namespace (a flat `include: local:` was tried first and rejected — it collided both subprojects' same-named jobs).
- `backend/.gitlab-ci.yml` — stages `build, test, secret-detection, lint, docker-build, deploy`. In-scope jobs: `composer` (install), `php-cs-fixer`, `phpstan`, `phpunit` (Cobertura coverage), `composer-audit`, `lint` (yaml + container), `secret_detection` (GitLab's `Security/Secret-Detection.gitlab-ci.yml` template).
- `frontend/.gitlab-ci.yml` — stages `quality, docker-build, deploy`. In-scope job: `quality-checks` (`npm ci`, lint, `tsc --noEmit`, test, format:check).

Neither file has path-based filtering — both pipelines run on every push today.

## Desired End State

Two GitHub Actions workflow files (`.github/workflows/backend-ci.yml`, `.github/workflows/frontend-ci.yml`) run on every push and pull request, replicating all in-scope GitLab jobs with no regression in coverage. All three `.gitlab-ci.yml` files are removed. `backend/CLAUDE.md`'s stale reference to `.gitlab-ci.yml` is updated to point at the new workflow file.

**Verification**: open a PR touching both `backend/` and `frontend/` — both new workflows run and all jobs (including the intentionally-failing scenarios below) behave as expected; no `.gitlab-ci.yml` file remains in the repo.

### Key Discoveries:

- `backend/.env.test` uses SQLite + explicit array/filesystem cache — **no Postgres/Redis `services:` block is needed** for the `phpunit` job (research.md, backend/.env.test).
- `ext-gd` is genuinely used by `src/Controller/UserAvatarController.php` (image resizing) but untested today — install it via `setup-php` rather than carrying forward GitLab's `--ignore-platform-req=ext-gd`.
- `phpunit.dist.xml` already forces `APP_ENV=test` via `<server name="APP_ENV" value="test" force="true"/>` — no env export needed for `phpunit` or `lint` jobs.
- `frontend/Dockerfile` pins `node:20-alpine` — CI should match with Node 20 to avoid CI/deploy-image drift.
- `gitleaks-action@v2`'s GitHub Marketplace license only exempts personal-user-account repos (this repo: `maciejszklarczyk/planner`, a personal account, not an org) from a paid license — but the action is scheduled to break entirely once GitHub removes Node 20 from hosted runners on 2026-09-16 (~2 months out). Running `gitleaks` directly via its Docker image sidesteps both the licensing question and that deprecation.
- `backend/CLAUDE.md:36` explicitly names `.gitlab-ci.yml` in its composer-audit convention note — this needs updating as part of cutover, not left stale.

## What We're NOT Doing

- Not migrating `docker-build` or `deploy-production` jobs (either project) — deployment stays on GitLab CI's existing setup, or undeployed, until a future change addresses it.
- Not adding `paths:` filtering — both new workflows always run on every push/PR, preserving today's behavior exactly.
- Not adding a PR-comment coverage action (e.g. `irongut/CodeCoverageSummary`) — `--coverage-text` output remains visible in job logs only.
- Not touching `frontend/DEPLOYMENT.md`'s GitLab CI/CD mention — that doc describes the (out-of-scope) deployment pipeline.
- Not running GitLab CI and GitHub Actions in parallel — this is an immediate cutover, not a phased trial.
- Not reconfiguring GitHub branch protection / required status checks — GitHub's API/UI for this isn't reachable via repo file changes; call out as a manual follow-up in Phase 3.
- Not touching the now-stale `frontend/context/changes/cicd-rework/` change folder (pre-monorepo, unimplemented) — out of scope, noted in research only.

## Implementation Approach

Mirror the current 1:1 split: one workflow file per subproject, each with parallel jobs matching the existing GitLab job names/behavior as closely as possible, substituting GitHub-native equivalents (`shivammathur/setup-php`, `actions/setup-node`, `actions/cache`) for GitLab's Docker-image-per-job model. Land both workflows and verify green before deleting the GitLab files, so the repo is never without working CI.

## Critical Implementation Details

**Secret scanning without the marketplace action**: use a plain step that runs the `zricethezav/gitleaks` Docker image directly (`docker run --rm -v "$PWD:/repo" zricethezav/gitleaks:latest detect --source /repo --no-git -v`, or a pinned digest) rather than `gitleaks/gitleaks-action@v2`. This avoids both the org-vs-personal-account license branch and the action's dependency on Node 20 (which GitHub is removing from hosted runners on 2026-09-16). The step should fail the job (non-zero exit) on any detected secret, matching GitLab's `secret_detection` job semantics.

## Phase 1: Backend CI Workflow

### Overview

Create `.github/workflows/backend-ci.yml` replicating `backend/.gitlab-ci.yml`'s in-scope jobs (composer install, php-cs-fixer, phpstan, phpunit, composer-audit, lint, secret scanning), running on every push and pull request.

### Changes Required:

#### 1. Backend CI workflow file

**File**: `.github/workflows/backend-ci.yml`

**Intent**: Define a GitHub Actions workflow with parallel jobs for backend build/quality/security checks, each installing PHP 8.4 via `shivammathur/setup-php` and dependencies via cached `composer install`, working out of the `backend/` subdirectory.

**Contract**:
- Triggers: `on: [push, pull_request]` — no `paths:` filter, matching current always-run behavior.
- `jobs.<name>.defaults.run.working-directory: backend` on every job (or `cd backend &&` prefix per step) so commands run relative to the subproject, matching the `cd backend` pattern already used in the GitLab jobs.
- Every job: `actions/checkout@v4` → `shivammathur/setup-php@v2` with `php-version: '8.4'`, `tools: composer:v2`, `extensions: ctype, iconv, gd, pcov` (only request `coverage: pcov` on the `phpunit` job; omit elsewhere) → `actions/cache@v4` keyed on `hashFiles('backend/composer.lock')` for `backend/vendor` → `composer install --no-interaction --prefer-dist` (no `--ignore-platform-req=ext-gd`, since `gd` is now installed).
- `php-cs-fixer` job: `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff --verbose` — unchanged command.
- `phpstan` job: `vendor/bin/phpstan analyse --memory-limit 1G` — unchanged command.
- `phpunit` job: no `services:` block (SQLite/array cache per Key Discoveries). `vendor/bin/phpunit --coverage-cobertura=coverage.xml --coverage-text` — unchanged command; upload `backend/coverage.xml` via `actions/upload-artifact@v4` for later inspection (no PR-comment action, per scope decision).
- `composer-audit` job: `composer audit --locked` — unchanged command, no `setup-php` coverage flag needed.
- `lint` job: `composer validate && php bin/console lint:yaml config/ && php bin/console lint:container` — unchanged commands, no extra env needed (relies on committed `.env`/`.env.local` as today).
- `secret-scan` job: runs at the repo root (not `working-directory: backend`) since it should cover the whole checkout, per the Critical Implementation Details step above.

### Success Criteria:

#### Automated Verification:

- Workflow YAML is valid: `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/backend-ci.yml'))"`
- On a test PR, all backend-ci jobs (`composer`/setup, `php-cs-fixer`, `phpstan`, `phpunit`, `composer-audit`, `lint`, `secret-scan`) complete and report status to GitHub
- `phpunit` job produces `backend/coverage.xml` as an uploaded artifact
- Introducing a deliberate CS violation, a PHPStan error, a failing test, a known-bad composer advisory (temporarily), a YAML syntax error, and a fake secret each independently fail their respective job

#### Manual Verification:

- PR check list in the GitHub UI shows all backend jobs with clear, distinguishable names
- Job logs show `--coverage-text` output for manual coverage inspection

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Frontend CI Workflow

### Overview

Create `.github/workflows/frontend-ci.yml` replicating `frontend/.gitlab-ci.yml`'s `quality-checks` job, running on every push and pull request.

### Changes Required:

#### 1. Frontend CI workflow file

**File**: `.github/workflows/frontend-ci.yml`

**Intent**: Define a single GitHub Actions job that installs Node 20 and runs the same four quality commands GitLab ran, working out of the `frontend/` subdirectory.

**Contract**:
- Triggers: `on: [push, pull_request]` — no `paths:` filter.
- `jobs.quality-checks.defaults.run.working-directory: frontend`.
- Steps: `actions/checkout@v4` → `actions/setup-node@v4` with `node-version: '20'`, `cache: 'npm'`, `cache-dependency-path: frontend/package-lock.json` → `npm ci` → `npm run lint` → `npx tsc --noEmit` → `npm run test -- --reporter=default --reporter=github-actions` → `npm run format:check`.
- No `NEXT_PUBLIC_API_URL` or other env vars needed (confirmed unused by lint/typecheck/test/format:check).

### Success Criteria:

#### Automated Verification:

- Workflow YAML is valid: `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/frontend-ci.yml'))"`
- On a test PR, `quality-checks` completes and reports status to GitHub
- Introducing a deliberate ESLint violation, a TypeScript type error, a failing Vitest test, and a Prettier-formatting violation each independently fail the job

#### Manual Verification:

- Failing Vitest tests show inline PR annotations (via `--reporter=github-actions`)
- PR check shows `quality-checks` with a clear name in the GitHub UI

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Cutover and Documentation Cleanup

### Overview

Remove the GitLab CI files now that GitHub Actions workflows are verified working, and update the one doc that names `.gitlab-ci.yml` explicitly.

### Changes Required:

#### 1. Remove GitLab CI files

**Files**: `.gitlab-ci.yml`, `backend/.gitlab-ci.yml`, `frontend/.gitlab-ci.yml`

**Intent**: Delete all three now-superseded GitLab CI configuration files — the parent-child pipeline structure and both subproject pipelines are fully replaced by the two GitHub Actions workflows from Phases 1–2.

**Contract**: File deletion; no other files reference these paths for build/test/lint (deployment tooling like `docker-compose.prod.yaml` is untouched and unrelated).

#### 2. Update backend CLAUDE.md's CI convention note

**File**: `backend/CLAUDE.md`

**Intent**: The composer-audit convention bullet (line 36) explicitly tells future contributors "don't disable the job in `.gitlab-ci.yml`" — this must point at the new workflow file instead, or it'll send people looking for a file that no longer exists.

**Contract**: Replace the `.gitlab-ci.yml` reference in that bullet with `.github/workflows/backend-ci.yml`.

### Success Criteria:

#### Automated Verification:

- No `.gitlab-ci.yml` file exists anywhere in the repo: `! find . -name '.gitlab-ci.yml' -not -path '*/vendor/*' -not -path '*/node_modules/*' | grep .`
- `grep -c gitlab-ci backend/CLAUDE.md` returns `0`
- Both `.github/workflows/*.yml` files still validate as YAML after the deletion (no accidental cross-file breakage)

#### Manual Verification:

- A fresh PR against `main` shows only GitHub Actions checks (no GitLab pipeline reference remains anywhere a contributor would look)
- Confirm GitHub repository settings → Branch protection rules for `main` are manually updated to require the new `backend-ci` / `frontend-ci` job names as status checks (cannot be verified by an automated command — flagged here as a required manual action, not a code change)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful.

---

## Testing Strategy

### Unit Tests:

- No new unit tests are introduced by this change — existing backend (PHPUnit) and frontend (Vitest) suites continue to run unchanged, now under GitHub Actions instead of GitLab CI.

### Integration Tests:

- End-to-end validation is the CI workflows themselves running green on a real PR — there's no separate integration-test layer for CI configuration.

### Manual Testing Steps:

1. Open a PR touching both `backend/` and `frontend/` files; confirm both new workflows trigger and all jobs pass.
2. Temporarily introduce one failure per job type (CS violation, PHPStan error, failing PHPUnit test, composer audit advisory, YAML syntax error, planted secret, ESLint violation, TS error, failing Vitest test, Prettier violation) to confirm each job fails as expected, then revert.
3. After Phase 3, confirm branch protection required-status-checks are updated in GitHub repository settings to reference the new job names.

## Performance Considerations

`actions/cache@v4` for both `backend/vendor` (keyed on `composer.lock`) and npm (via `setup-node`'s built-in `cache: 'npm'`) keeps job runtimes comparable to GitLab's artifact-based caching. No `services:` containers are started for backend tests, keeping the `phpunit` job lightweight (no Postgres/Redis startup overhead).

## Migration Notes

This is a CI-configuration-only migration — no application code, database schema, or runtime behavior changes. Rollback, if ever needed, is restoring the three deleted `.gitlab-ci.yml` files from git history; no data migration is involved.

## References

- Related research: `context/changes/cicd-migration/research.md`
- Current backend pipeline: `backend/.gitlab-ci.yml` (pre-deletion, Phase 3)
- Current frontend pipeline: `frontend/.gitlab-ci.yml` (pre-deletion, Phase 3)
- Backend CI convention note to update: `backend/CLAUDE.md:36`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Backend CI Workflow

#### Automated

- [x] 1.1 Workflow YAML is valid
- [x] 1.2 All backend-ci jobs complete and report status on a test PR
- [x] 1.3 phpunit job produces backend/coverage.xml as an uploaded artifact
- [x] 1.4 Deliberate failures (CS violation, PHPStan error, failing test, bad advisory, YAML error, planted secret) each independently fail their job

#### Manual

- [ ] 1.5 PR check list shows all backend jobs with clear, distinguishable names
- [ ] 1.6 Job logs show --coverage-text output for manual coverage inspection

### Phase 2: Frontend CI Workflow

#### Automated

- [ ] 2.1 Workflow YAML is valid
- [ ] 2.2 quality-checks completes and reports status on a test PR
- [ ] 2.3 Deliberate failures (ESLint violation, TS error, failing test, Prettier violation) each independently fail the job

#### Manual

- [ ] 2.4 Failing Vitest tests show inline PR annotations
- [ ] 2.5 PR check shows quality-checks with a clear name

### Phase 3: Cutover and Documentation Cleanup

#### Automated

- [ ] 3.1 No .gitlab-ci.yml file exists anywhere in the repo
- [ ] 3.2 backend/CLAUDE.md no longer references gitlab-ci
- [ ] 3.3 Both workflow files still validate as YAML after deletion

#### Manual

- [ ] 3.4 Fresh PR against main shows only GitHub Actions checks
- [ ] 3.5 Branch protection rules for main updated to require new job names as status checks
