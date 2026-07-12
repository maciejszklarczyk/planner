# CI/CD Rework Implementation Plan

## Overview

Rework `backend/.gitlab-ci.yml` so production deploys are gated on release tags (`vX.Y.Z`) instead of firing automatically on every push to `main`, while cleaning up the pipeline chaos surfaced in research: legacy `only`/`except` syntax, redundant `composer install` calls, and a migration step that silently swallows failures.

## Current State Analysis

`backend/.gitlab-ci.yml` (6 stages: test → secret-detection → build → lint → docker-build → deploy) currently:
- Auto-builds and auto-deploys to production on every `main` push (`docker-build` and `deploy-production` both `only: main`, no gate).
- Swallows migration and cache-clear failures via `|| true` (`.gitlab-ci.yml:126-127`) — pipeline reports green even when a migration breaks.
- Duplicates `composer install` across `phpunit`, `php-cs-fixer`, and `composer-audit` instead of reusing the `composer` job's `vendor/` artifact (only `lint` does this correctly via `needs: composer`).
- Inconsistently excludes `main` (`php-cs-fixer`, `composer`, `lint` skip it; `phpunit`, `composer-audit` don't), entirely via legacy `only`/`except` with no `rules:` anywhere.
- Deploys a floating `:latest` image with no reproducible link back to the release that triggered it.

See `context/changes/cicd-rework/research.md` for full findings and file:line references.

## Desired End State

Pushing to `main` still builds and pushes a Docker image (`:latest` + `:$CI_COMMIT_SHORT_SHA`) for staging/testing, and still runs tests/lint/audit — but **production deploy only fires when a tag matching `vX.Y.Z` is pushed**. That tag push also causes the image to be additionally tagged `$CI_REGISTRY_IMAGE:$CI_COMMIT_TAG`, and `deploy-production` pulls that exact tag-pinned image rather than `:latest`. A failed migration or cache-clear now fails the pipeline instead of being silently ignored. Verification: push to `main` → no deploy job appears in the pipeline; push a `v1.2.3` tag → `deploy-production` appears, runs, and pulls the tag-pinned image.

### Key Discoveries:

- `composer audit --locked` (`.gitlab-ci.yml:61`) reads `composer.lock` directly and does not require an installed `vendor/` — the `composer install` step in `composer-audit` (`.gitlab-ci.yml:60`) is not just redundant with the `composer` job, it's unnecessary entirely.
- `docker-compose.prod.yaml`'s `php` service hardcodes `image: registry.gitlab.com/planner6551704/backend:latest` — there is no existing indirection to pull a specific tag; this must be parameterized for tag-pinned deploys to work.
- `needs:` in GitLab CI creates a DAG that can cross stage boundaries — `phpunit` (stage `test`) can `needs: [composer]` (stage `build`) without a stage reorder, exactly as the last review's F3 finding anticipated.
- Making `phpunit`/`php-cs-fixer` depend on the `composer` job via `needs:` requires `composer` to run on every pipeline the dependent job runs on (GitLab errors at pipeline-creation time if a `needs` target is excluded by rules while the dependent job is included) — this is the mechanism that resolves the "inconsistent `except: main`" finding: all test/lint/build-prep jobs must run uniformly across branch and main pipelines.

## What We're NOT Doing

- Not touching `frontend/.gitlab-ci.yml` — it has the identical chaos pattern but wasn't researched; a follow-up change should apply this same template there.
- Not fixing prod secrets provisioning (the out-of-band `.env` file on the deploy host) beyond the one-line `.env.example` doc correction — secret injection/rotation is a separate concern.
- Not configuring GitLab's protected-tags UI setting via code (it's not code) — this plan documents it as a required manual step instead.
- Not adding automated rollback tooling — rollback remains "deploy an older tag manually," which is already an improvement over today (today there's no tag to roll back to at all).
- Not changing the `composer.json` `config.audit.ignore` mechanism — research found it currently unused and the audit clean; nothing to fix there.
- Not fixing the `.dockerignore` gap (excludes `.env.local`/`.env.test`/`.env.dev` but not base `.env`) — real risk is low in CI (fresh checkout has no `.env`) but real for local manual `docker build`; tracked as a known, separately-addressable gap rather than fixed here.

## Implementation Approach

Three phases, ordered so each stays independently deployable and testable:

1. **Mechanical modernization** — convert `only`/`except` to `rules:` uniformly, eliminate redundant installs. Zero behavior change to when jobs run, except making test/lint jobs run on `main` too (closing the inconsistency and unblocking `needs:` chaining).
2. **Deploy gating** — the actual behavior change: tag-triggered deploy, tag-pinned images, real migration failure handling, documented protected-tags prerequisite.
3. **Doc fix** — trivial one-line correction to `.env.example`, kept as its own phase since it's an unrelated file and trivially verifiable in isolation.

## Critical Implementation Details

**GitLab `needs:` and rules interaction**: When `phpunit` and `php-cs-fixer` gain `needs: [composer]`, the `composer` job must not be excluded by `rules:` on any pipeline where `phpunit`/`php-cs-fixer` run — otherwise GitLab fails pipeline creation with a "needs job not present" error. Phase 1 removes `composer`'s main-exclusion specifically to satisfy this, not for its own sake.

**Image tag propagation into `docker-compose.prod.yaml`**: `docker compose` substitutes `${VAR}` references in the compose file from the shell environment (or a `.env` file in the working directory) at the time `docker compose` commands run — not at CI-file-parse time. So `IMAGE_TAG` must be `export`ed as a real environment variable in the `deploy-production` job's `script:` before `docker compose ... pull` runs, and the compose file's `image:` line must reference `${IMAGE_TAG:-latest}` so local/manual `docker compose up` still defaults sanely without CI.

## Phase 1: Modernize pipeline syntax and eliminate redundant installs

### Overview

Convert legacy `only`/`except` to `rules:` across `php-cs-fixer`, `composer`, `lint`, `docker-build`, and `deploy-production` (behavior-preserving for now). Make `composer`, `php-cs-fixer`, and `lint` run on every pipeline including `main` (removing their `except: main`). Eliminate `composer-audit`'s unnecessary `composer install`. Chain `phpunit` and `php-cs-fixer` to the `composer` job's `vendor/` artifact via `needs:` instead of reinstalling.

### Changes Required:

#### 1. `composer-audit` job — drop the unnecessary install

**File**: `backend/.gitlab-ci.yml`

**Intent**: `composer audit --locked` reads `composer.lock` directly; installing dependencies first is dead work. Remove the install step, the pcov/apk lines are not present here so only the `composer install` line and its cache block need to go.

**Contract**: Job keeps `stage: test`, `image: composer:2.9.4`, script becomes just `composer audit --locked`. No `cache:` block needed since nothing is installed.

#### 2. `phpunit` and `php-cs-fixer` jobs — consume `composer`'s artifact

**File**: `backend/.gitlab-ci.yml`

**Intent**: Stop reinstalling dependencies that the `composer` job already installed and published as an artifact; reuse it via `needs:`, matching the pattern `lint` already uses.

**Contract**: Both jobs add `needs: [composer]`, remove their own `composer install --no-interaction --prefer-dist --ignore-platform-req=ext-gd` line, and drop their own `cache:` block (the artifact from `composer` supplies `vendor/`; caching is now only needed on the `composer` job itself). `phpunit` keeps its `pecl install pcov` extension-install lines unchanged — those are unrelated to dependency installation.

#### 3. `composer`, `php-cs-fixer`, `lint` jobs — remove main-exclusion, migrate to `rules:`

**File**: `backend/.gitlab-ci.yml`

**Intent**: These three jobs currently skip `main` via `except: [main]`. Removing that makes them run on every pipeline uniformly (branches, `main`, and future tag pipelines), which is required so `phpunit`/`php-cs-fixer`'s new `needs: [composer]` doesn't break on `main` pipelines, and directly resolves the "inconsistent except: main" finding from research.

**Contract**: Delete the `except:` key entirely from all three jobs (no replacement `rules:` needed — omitting both `rules:` and `only`/`except` means "run on branch and tag pipelines," which is the desired uniform behavior).

#### 4. `phpunit`, `composer-audit` jobs — no rules change needed

**File**: `backend/.gitlab-ci.yml`

**Intent**: These already have no `except:`/`only:` restriction and should keep running on every pipeline unchanged.

**Contract**: No change beyond what's specified in items 1–2 above.

### Success Criteria:

#### Automated Verification:

- `.gitlab-ci.yml` passes GitLab's CI Lint (`php bin/console lint:yaml` locally is insufficient for GitLab-specific schema — use the project's CI Lint page or `glab ci lint` if available; at minimum, YAML syntax check: `python3 -c "import yaml; yaml.safe_load(open('.gitlab-ci.yml'))"`)
- A test-branch pipeline runs `php-cs-fixer`, `phpunit`, `composer-audit`, `composer`, `lint` and all pass
- `phpunit` and `php-cs-fixer` job logs show no `composer install` step executing (confirm via pipeline job log inspection)
- `composer-audit` job log shows no `composer install` step executing

#### Manual Verification:

- Push a throwaway commit to a non-main branch and confirm the pipeline graph shows `phpunit`/`php-cs-fixer` waiting on/consuming `composer`'s artifact (visible in GitLab's pipeline DAG view)
- Push a throwaway commit directly simulating a `main`-pipeline (or merge a small no-op change) and confirm `composer`, `php-cs-fixer`, `lint` now run (previously skipped) and pass

---

## Phase 2: Gate production deploy on release tag

### Overview

Replace `docker-build` and `deploy-production`'s `only: main` with tag-aware `rules:`. `docker-build` keeps running on every `main` push (unchanged) and additionally pushes a `$CI_COMMIT_TAG`-tagged image when triggered by a tag push. `deploy-production` now only runs when a `vX.Y.Z` tag is pushed, pulls the tag-pinned image, and no longer swallows migration/cache-clear failures.

**Residual risk (accepted, not fixed by this phase)**: the deploy script's existing order is `pull` → `down` → `up -d` → `migrate` → `cache:clear` — new containers already serve production traffic by the time migration runs. Removing `|| true` makes a broken migration fail the pipeline *loudly*, but doesn't prevent the broken app from being live in the window between cutover and failure detection. This phase only fixes visibility, not pre-cutover safety; reordering to migrate-before-cutover is out of scope here and can be a follow-up.

### Changes Required:

#### 1. `docker-build` job — build on main, additionally tag on release

**File**: `backend/.gitlab-ci.yml`

**Intent**: Preserve continuous image builds on every `main` push for staging/testing, while also producing a stable, reproducible `$CI_COMMIT_TAG`-tagged image when a release tag triggers the pipeline.

**Contract**: Replace `only: [main]` with:
```yaml
rules:
  - if: '$CI_COMMIT_BRANCH == "main"'
  - if: '$CI_COMMIT_TAG =~ /^v\d+\.\d+\.\d+$/'
```
`docker build` still runs unconditionally (still tags the image locally as `:latest` and `:$CI_COMMIT_SHORT_SHA` — needed as the source for the tag-push step below), but the two unconditional `docker push` lines for `:latest` and `:$CI_COMMIT_SHORT_SHA` become conditional on `$CI_COMMIT_TAG` being unset, so a tag-triggered pipeline (whose commit already got these pushed by the preceding `main` pipeline) doesn't redundantly re-push them:
```yaml
    - if [ -z "$CI_COMMIT_TAG" ]; then docker push $CI_REGISTRY_IMAGE:latest && docker push $CI_REGISTRY_IMAGE:$CI_COMMIT_SHORT_SHA; fi
    - if [ -n "$CI_COMMIT_TAG" ]; then docker tag $CI_REGISTRY_IMAGE:$CI_COMMIT_SHORT_SHA $CI_REGISTRY_IMAGE:$CI_COMMIT_TAG && docker push $CI_REGISTRY_IMAGE:$CI_COMMIT_TAG; fi
```

#### 2. `deploy-production` job — tag-only trigger, no silent failures

**File**: `backend/.gitlab-ci.yml`

**Intent**: Deploy only fires on a deliberate release tag push, pulls the exact image built for that tag, and a broken migration or cache-clear now fails the pipeline instead of being masked.

**Contract**: Replace `only: [main]` with:
```yaml
rules:
  - if: '$CI_COMMIT_TAG =~ /^v\d+\.\d+\.\d+$/'
```
Add `export IMAGE_TAG=$CI_COMMIT_TAG` as the first `script:` line (before `cd $DEPLOY_DIR`) so `docker compose` picks it up via environment substitution. Remove ` || true` from both the `doctrine:migrations:migrate` and `cache:clear` lines so failures propagate.

#### 3. `docker-compose.prod.yaml` — parameterize the image reference

**File**: `backend/docker-compose.prod.yaml`

**Intent**: Allow CI to pin the deployed image to the release tag while keeping a sane default for local/manual `docker compose up`.

**Contract**: Change the `php` service's `image:` line from the hardcoded `registry.gitlab.com/planner6551704/backend:latest` to `${CI_REGISTRY_IMAGE:-registry.gitlab.com/planner6551704/backend}:${IMAGE_TAG:-latest}`.

#### 4. Protected tags — manual GitLab configuration (documented, not coded)

**Intent**: A `rules:` condition alone only decides whether the pipeline runs — it doesn't restrict *who* can create a `v*` tag. Without a protected-tag restriction, tag-based gating is not a real authorization boundary, just a pipeline condition anyone with push access can trigger.

**Contract**: No file change. This is captured as a Manual Verification item below — configure Settings → Repository → Protected tags with pattern `v*` restricted to the Maintainer role (or higher) before treating the tag-triggered deploy as a trusted release gate.

### Success Criteria:

#### Automated Verification:

- `.gitlab-ci.yml` YAML syntax check passes: `python3 -c "import yaml; yaml.safe_load(open('.gitlab-ci.yml'))"`
- A push to `main` (no tag) triggers `docker-build` but the pipeline shows no `deploy-production` job at all
- A push of a tag matching `v1.2.3` triggers both `docker-build` (with the additional tag-push step) and `deploy-production`
- `deploy-production` job log shows `export IMAGE_TAG=` executing before `docker compose ... pull`
- Intentionally breaking a migration (e.g. temporarily pointing at a bad migration in a test tag) causes `deploy-production` to fail rather than report success — verify in a disposable/staging context, not against real production data

#### Manual Verification:

- Configure GitLab Settings → Repository → Protected tags: pattern `v*`, allowed to create = Maintainer (or the project's equivalent restricted role)
- Push a real `vX.Y.Z` tag from a maintainer account and confirm `deploy-production` runs, pulls the tag-pinned image (`docker compose -f docker-compose.prod.yaml images` on the host shows the tag, not `latest`), and the app responds correctly at `https://api-planner.msolve.it`
- Confirm a non-maintainer (or a tag not matching `v*`) cannot trigger a production deploy

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Fix `.env.example` and `DOCKER-EXECUTOR-SETUP.md` documentation mismatches

### Overview

Correct `.env.example`'s instruction, which currently tells the operator to copy it to `.env.prod` on the server, while `docker-compose.prod.yaml` actually loads `.env`. Also correct `docs/DOCKER-EXECUTOR-SETUP.md`, which is the more detailed and authoritative source of the same `.env.prod` mistake (and references a `.env.prod.example` file that doesn't exist in the repo), plus two other facts in that doc that have gone stale relative to the current `.gitlab-ci.yml`.

### Changes Required:

#### 1. `.env.example` — correct the copy-target comment

**File**: `backend/.env.example`

**Intent**: Prevent a future server provisioning from creating a `.env.prod` file that `docker-compose.prod.yaml` will never actually read.

**Contract**: Change the comment on line 2 from `# Skopiuj do .env.prod na serwerze` to `# Skopiuj do .env na serwerze (docker-compose.prod.yaml wczytuje .env)`.

#### 2. `docs/DOCKER-EXECUTOR-SETUP.md` — correct `.env.prod`, `DEPLOY_DIR`, and manual-trigger references

**File**: `backend/docs/DOCKER-EXECUTOR-SETUP.md`

**Intent**: This doc is a full server-provisioning walkthrough and a more detailed, more likely-to-be-followed source of the `.env.prod` mistake than `.env.example`'s one-line comment. It has also drifted from the current `.gitlab-ci.yml` in two other ways: it references `/opt/plan-backend` as the deploy directory (actual value is `DEPLOY_DIR: '/home/maciej/docker/apps/planner/backend'`, `.gitlab-ci.yml:11`), and it describes `deploy-production` as a manual-trigger job ("Kliknij ▶️ Play"), but the current job has no `when: manual` and runs automatically.

**Contract**:
- Section "4. Skopiuj pliki konfiguracyjne" (lines ~65-86): rename the `.env.prod` file/section to `.env`, update the `nano` path accordingly, and update "Wklej zawartość (z .env.prod.example)" to reference `.env.example` (the file that actually exists) instead of the non-existent `.env.prod.example`.
- Line 171 (`git add .gitlab-ci.yml docker-compose.prod.yaml .env.prod.example`): change `.env.prod.example` to `.env.example`.
- Line 254 (`Zły \`.env.prod\` (hasło do bazy, APP_SECRET)`): change `.env.prod` to `.env`.
- Wherever `/opt/plan-backend` is used as the deploy directory path (config.toml volumes example, `mkdir`/`chmod` commands, docker run examples in section 8, log-tailing commands in section 9): either update to the actual `DEPLOY_DIR` value or add a note that this doc uses a placeholder path and the operator must match it to the CI variable.
- Section "9. Pierwszy deploy", step "Krok 2": remove or correct the "Stage deploy-production czeka na manual trigger... Kliknij ▶️ Play" instruction to reflect that the job now runs automatically on tag push (per Phase 2 of this plan), not on every `main` push and not via manual trigger.

### Success Criteria:

#### Automated Verification:

- `grep -n "env.prod" backend/.env.example` returns no matches
- `grep -n "env.prod" backend/docs/DOCKER-EXECUTOR-SETUP.md` returns no matches

#### Manual Verification:

- Visual review confirms `.env.example`'s comment matches what `docker-compose.prod.yaml`'s `env_file:` directive actually loads
- Visual review confirms `docs/DOCKER-EXECUTOR-SETUP.md`'s deploy-directory path and deploy-trigger description match the current `.gitlab-ci.yml`

---

## Testing Strategy

### Unit Tests:

- No new unit tests — this is a CI configuration change, not application code.

### Integration Tests:

- Existing `phpunit` suite continues to run and pass under the new `needs: [composer]` artifact-reuse pattern (Phase 1) — this is the regression check that dependency reuse didn't break anything.

### Manual Testing Steps:

1. Open a throwaway branch, push a trivial commit, confirm Phase 1's pipeline DAG and job outcomes.
2. Merge to `main`, confirm `docker-build` fires and `deploy-production` does not appear.
3. Configure protected tags per Phase 2.
4. Push `v0.0.1-test` (or similar) from a maintainer account against a disposable/staging deploy target if available, confirm `deploy-production` runs and pulls the tag-pinned image.
5. Confirm `.env.example`'s comment reads correctly.

## Performance Considerations

None — this changes pipeline control flow and one documentation line, not application runtime behavior. `composer install` deduplication in Phase 1 modestly reduces total CI minutes per pipeline.

## Migration Notes

No data migration. Operationally, once this lands, deploys require an explicit `git tag vX.Y.Z && git push origin vX.Y.Z` — document this workflow change for whoever currently expects "merge to main = deployed."

## References

- Related research: `context/changes/cicd-rework/research.md`
- Prior review with F3 finding: `context/archive/2026-07-12-fix/reviews/impl-review.md`
- Existing correct `needs:` pattern to follow: `backend/.gitlab-ci.yml:86-96` (`lint` job)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Modernize pipeline syntax and eliminate redundant installs

#### Automated

- [x] 1.1 `.gitlab-ci.yml` YAML syntax check passes — ddf4c5a
- [x] 1.2 Test-branch pipeline runs php-cs-fixer, phpunit, composer-audit, composer, lint — all pass — verified MR!39 pipeline 2670801026 (SHA 75e0c61), all 6 jobs success
- [x] 1.3 phpunit/php-cs-fixer job logs show no composer install step — verified via job trace API (jobs 15304378497, 15304378496), no match for "composer install"
- [x] 1.4 composer-audit job log shows no composer install step — verified via job trace API (job 15304378498), no match for "composer install"

#### Manual

- [x] 1.5 Non-main branch pipeline DAG shows phpunit/php-cs-fixer consuming composer's artifact — verified via job timestamps: composer finished 14:51:32.161Z, php-cs-fixer started 14:51:36.893Z, phpunit started 14:51:59.840Z (both after composer, consistent with needs:)
- [x] 1.6 Main pipeline now runs composer/php-cs-fixer/lint (previously skipped) and passes — verified pipeline 2670805711 (main, SHA 6bcb959), all jobs success

### Phase 2: Gate production deploy on release tag

#### Automated

- [x] 2.1 `.gitlab-ci.yml` YAML syntax check passes
- [x] 2.2 Push to main triggers docker-build only, no deploy-production job appears — verified pipeline 2670805711 (main, SHA 6bcb959): docker-build ran and succeeded, no deploy-production job present
- [x] 2.3 Push of v1.2.3-style tag triggers docker-build (with tag-push step) and deploy-production — verified pipeline 2670809154 (tag v0.0.1, SHA 8aeeb46): docker-build and deploy-production both ran and succeeded; docker-build trace shows `docker tag ...$CI_COMMIT_TAG && docker push ...$CI_COMMIT_TAG`
- [x] 2.4 deploy-production log shows `export IMAGE_TAG=` before docker compose pull — confirmed via job trace (job 15304425370): `$ export IMAGE_TAG=$CI_COMMIT_TAG` runs immediately before `docker compose ... pull`
- [ ] 2.5 Intentionally broken migration causes deploy-production to fail — not tested against real production (v0.0.1 deploy ran a clean migration, `[OK] Already at the latest version`); deliberately not simulated on prod, needs a disposable/staging deploy target

#### Manual

- [x] 2.6 Protected tags configured in GitLab Settings (pattern v*, Maintainer role) — confirmed via API: protected_tags shows name "v*", create_access_levels: Maintainers only
- [x] 2.7 Real vX.Y.Z tag push deploys, pulls tag-pinned image, app responds correctly in production — verified: `docker compose ps` shows planner-php on image `backend:v0.0.1` (not latest), healthy; `curl https://api-planner.msolve.it/api/doc` returns HTTP 200
- [ ] 2.8 Non-maintainer or non-matching tag cannot trigger production deploy — not tested (would require a non-maintainer account attempting a tag push)

### Phase 3: Fix .env.example and DOCKER-EXECUTOR-SETUP.md documentation mismatches

#### Automated

- [ ] 3.1 `grep -n "env.prod" backend/.env.example` returns no matches
- [ ] 3.2 `grep -n "env.prod" backend/docs/DOCKER-EXECUTOR-SETUP.md` returns no matches

#### Manual

- [ ] 3.3 .env.example comment matches what docker-compose.prod.yaml's env_file actually loads
- [ ] 3.4 DOCKER-EXECUTOR-SETUP.md's deploy-directory path and deploy-trigger description match the current .gitlab-ci.yml
