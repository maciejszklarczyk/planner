---
date: 2026-07-12T14:12:21Z
researcher: Claude
git_commit: df88b4005e98087d440af12b48b2e98de5f1eb8c
branch: main
repository: backend
topic: "CI/CD rework — chaos points and gating deploy on release/tag instead of auto on main"
tags: [research, codebase, gitlab-ci, deploy, docker]
status: complete
last_updated: 2026-07-12
last_updated_by: Claude
---

# Research: CI/CD rework

**Date**: 2026-07-12T14:12:21Z
**Researcher**: Claude
**Git Commit**: df88b4005e98087d440af12b48b2e98de5f1eb8c
**Branch**: main
**Repository**: backend

## Research Question

Current CI/CD flow is chaotic — inspect it, find improvements. Deploy should not fire automatically on every push; gate it to release only. Scope: backend repo only. Deploy trigger: git tag/release. Focus: everything (stages, jobs, deploy mechanics, secrets, migrations).

## Summary

`backend/.gitlab-ci.yml` (6 stages: test → secret-detection → build → lint → docker-build → deploy) auto-builds and auto-deploys to production on **every push to `main`**, with no manual gate, no tag/release concept, and a migration step that silently swallows failures (`|| true`). Several jobs duplicate `composer install` instead of reusing the `composer` job's `vendor/` artifact via `needs:`. Secrets on the prod host are managed entirely out-of-band (CI never writes `.env`), and there's a naming mismatch between `.env.example`'s instructions (`.env.prod`) and what `docker-compose.prod.yaml` actually loads (`.env`). No prior CI/CD research or deploy-gating decision exists in `context/` — the only related history is the recent `fix/twig-symfony-cve-patch` change that added the `composer-audit` job itself and left one low-priority finding (redundant `composer install` in that job) unresolved.

GitLab's recommended mechanism for tag-gated deploy is `rules: - if: $CI_COMMIT_TAG` (replacing legacy `only: main`), optionally paired with **protected tags** (Settings → Repository) so only authorized roles can even create a release tag — a real authorization gate, not just a pipeline condition. `docker-build` should keep building on every `main` push (for staging/testing), but additionally tag the image with `$CI_COMMIT_TAG` when a tag triggers the pipeline, so `deploy-production` pulls a stable, reproducible reference instead of `:latest`.

## Detailed Findings

### Pipeline structure (`.gitlab-ci.yml`)

- **No deploy gate at all** — `docker-build` (:98-109) and `deploy-production` (:111-133) both use `only: main`; every main push auto-builds and auto-deploys, including running migrations, with zero human checkpoint.
- **Migration/cache-clear failures are silently swallowed** — `doctrine:migrations:migrate --no-interaction || true` (:126) and `cache:clear --env=prod || true` (:127): pipeline reports green even if migrations broke. No rollback story exists anywhere in the file.
- **Redundant `composer install`** — `phpunit` (:35-54, install at :41), `php-cs-fixer` (:24), and `composer-audit` (:60) each reinstall dependencies via their own cache instead of consuming the `composer` job's `vendor/` artifact (:74-76) through `needs:`. Only `lint` (:86-96) does this correctly (`needs: composer`, :89-90). This was already flagged as a known low-priority gap (F3) in the CVE-patch review and left unresolved — see Historical Context below.
- **Inconsistent `except: main`** — `php-cs-fixer`, `composer`, `lint` skip `main` (:32-33, 83-84, 95-96); `phpunit` and `composer-audit` do not, so they rerun redundantly on the main pipeline that triggers deploy. No `rules:` block exists anywhere in the file — everything is legacy branch-pipeline based; there is no MR-pipeline-specific configuration.
- **Hardcoded deploy path** — `DEPLOY_DIR: '/home/maciej/docker/apps/planner/backend'` (:11) is a plaintext CI variable tied to one named user's home directory on what appears to be a single self-hosted runner.

### Docker build (`docker/php/Dockerfile.prod`)

- Referenced correctly by `docker-build` (`-f docker/php/Dockerfile.prod`, `.gitlab-ci.yml:105`).
- `COPY . .` (Dockerfile.prod:36) copies the full build context; `.dockerignore` excludes `.env.local`, `.env.test`, `.env.dev` (lines 37-39) but **not the base `.env`** — a developer's local `.env` present in the git context would be baked into the production image.
- `cache:clear`/`cache:warmup --env=prod` run at build time (Dockerfile.prod:39-41) with no `APP_SECRET`/`DATABASE_URL` passed to `docker build` — cache warmers that touch DB/secrets may warm against dummy/missing config.

### Runtime config (`docker-compose.prod.yaml`)

- Services: `php`, `database` (postgres, bound to `127.0.0.1:5433`), `redis`, wired via Traefik labels. CI copies the file to `$DEPLOY_DIR` verbatim (`.gitlab-ci.yml:121`) — straightforward.
- All services load secrets via `env_file: .env` (lines 6-7, 38-39), referring to a file that must **already exist** on the host — CI never creates or updates it; prod secret rotation is entirely manual and undocumented in the pipeline.
- **Naming mismatch**: `.env.example` instructs the operator to copy it to `.env.prod` on the server, but `docker-compose.prod.yaml` actually loads `.env`.

### Advisory-ignore mechanism (`composer.json`)

- `config.audit.ignore` — documented in `backend/CLAUDE.md:87` as the sanctioned bypass for advisories without an upstream fix — **does not exist** in `composer.json` (config block spans lines 44-52: only `allow-plugins`, `bump-after-update`, `sort-packages`). Currently moot (audit is clean, per Historical Context below) but the escape hatch is unused/untested.

## Code References

- `backend/.gitlab-ci.yml:1-133` — full pipeline (stages, all jobs)
- `backend/.gitlab-ci.yml:98-109` — `docker-build` job, `only: main`
- `backend/.gitlab-ci.yml:111-133` — `deploy-production` job, `only: main`, migration `|| true`
- `backend/.gitlab-ci.yml:35-54` — `phpunit`, redundant `composer install`
- `backend/.gitlab-ci.yml:69-96` — `composer` + `lint` jobs, correct `needs:`/artifact pattern
- `backend/docker/php/Dockerfile.prod:36` — `COPY . .`
- `backend/docker/php/Dockerfile.prod:39-41` — build-time `cache:clear`/`cache:warmup`
- `backend/.dockerignore:37-39` — excludes `.env.local`/`.env.test`/`.env.dev`, not `.env`
- `backend/docker-compose.prod.yaml:6-7,38-39` — `env_file: .env`
- `backend/.env.example:1-30` — instructs `.env.prod`, mismatched with compose
- `backend/composer.json:44-52` — `config` block, no `audit.ignore` key
- `backend/CLAUDE.md:87` — documents `config.audit.ignore` policy

## Architecture Insights

- Both backend and frontend repos follow the same pattern (separate `.gitlab-ci.yml`, own `docker-compose.prod.yaml`, single self-hosted Docker runner tagged `docker`) — this research was scoped to backend only per user decision, but the same deploy-gating rework would apply symmetrically to frontend if desired later.
- The pipeline is entirely `only`/`except` (legacy syntax) with no `rules:` or `workflow:rules` anywhere — a full migration to `rules:` would be needed to introduce tag-based conditions cleanly alongside existing branch conditions.
- Secrets management for the prod host is fully out-of-band (manual `.env` file on server) — any deploy rework should not assume CI can inject secrets without addressing this gap separately.

## Historical Context (from prior changes)

- `context/archive/2026-07-12-fix/` (branch `fix/twig-symfony-cve-patch`, GitLab issue `work_items/17`) is the change that **added the `composer-audit` job** to the `test` stage (previously only `php-cs-fixer` + `phpunit` existed).
- `context/archive/2026-07-12-fix/reviews/impl-review.md` — verdict APPROVED, 0 critical / 2 warnings / 1 observation:
  - **F1 (fixed, commit `9569cee`)**: added explicit `cache.key.files: [composer.lock]` to shared `vendor/` cache jobs.
  - **F2 (fixed, commit `9569cee`)**: documented `config.audit.ignore` as the sanctioned escape hatch for un-fixable advisories in `backend/CLAUDE.md` — this is the direct source of the policy note referenced above, though the key itself is still unused.
  - **F3 (skipped, low priority, still open)**: `composer-audit` runs its own redundant `composer install` rather than reusing the `build`-stage artifact — flagged as infeasible without reordering `stages:` (`test → secret-detection → build`). Directly relevant to this rework: any stage-reordering done for deploy gating should also resolve F3.
- No prior research or decision exists anywhere in `context/changes/` or `context/archive/` about deploy gating, releases, or tags — this is greenfield for the project.
- `composer.json` currently has no advisory-ignore entries (audit is clean as of `df88b40`).

## Related Research

None — first research artifact for CI/CD deploy gating in this project.

## Open Questions

- Should `docker-build` continue running (and pushing `:latest`) on every `main` push, or should it too be restricted to reduce registry noise, given `deploy-production` will now only ever pull tag-pinned images?
- Should frontend's `.gitlab-ci.yml` receive the same tag-gating rework in a follow-up change, given it has the identical auto-deploy-on-main pattern (its `deploy-production` comment even says "manual trigger like backend" despite having no `when: manual`)?
- Should the `.env` vs `.env.prod` naming mismatch and out-of-band secret provisioning be addressed as part of this change, or tracked separately?
- Should F3 (redundant `composer install` in `composer-audit`) be folded into this rework's stage-reordering, since deploy gating likely already touches `stages:` ordering?
