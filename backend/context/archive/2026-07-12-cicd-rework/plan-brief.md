# CI/CD Rework — Plan Brief

> Full plan: `context/changes/cicd-rework/plan.md`
> Research: `context/changes/cicd-rework/research.md`

## What & Why

`backend/.gitlab-ci.yml` currently auto-builds and auto-deploys to production on every push to `main`, with a migration step that silently swallows failures (`|| true`) and no gate whatsoever. This rework makes production deploy fire only on a deliberate release tag (`vX.Y.Z`), while cleaning up the redundant `composer install` calls and inconsistent legacy `only`/`except` syntax found across the pipeline.

## Starting Point

6-stage GitLab pipeline (test → secret-detection → build → lint → docker-build → deploy). `docker-build` and `deploy-production` both use `only: main`. `phpunit`, `php-cs-fixer`, `composer-audit` each reinstall Composer dependencies independently instead of reusing the `composer` job's `vendor/` artifact — only `lint` does this correctly. Deployed image is always the floating `:latest` tag.

## Desired End State

Pushing to `main` still builds a staging-ready image and runs all checks. Pushing a tag matching `vX.Y.Z` is the only thing that triggers `deploy-production`, which pulls an image pinned to that exact tag and fails loudly (not silently) if migrations break. GitLab protected tags (`v*`, Maintainer-only) back this with a real authorization boundary, not just a pipeline condition.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
|---|---|---|---|
| Deploy trigger | Git tag `vX.Y.Z` via `rules: if: $CI_COMMIT_TAG` | Reproducible, deliberate, matches GitLab's recommended pattern over legacy `only`/`except` | Research |
| `docker-build` on main | Keep unconditional on every main push | Preserves existing staging/testing image flow, only deploy changes | Plan Q&A |
| Migration `\|\| true` | Remove it, let failures fail the pipeline | The whole point of this rework is a trustworthy deploy gate | Plan Q&A |
| F3 (redundant composer install) | Fixed now via `needs: [composer]` DAG pattern | File's already being restructured; no stage reorder actually required | Plan Q&A |
| `.env.example` mismatch | Fixed — 1-line doc correction | Cheap, removes a real landmine for next server provisioning | Plan Q&A |
| Frontend pipeline | Out of scope, follow-up change | Wasn't researched; keep blast radius small | Research + Plan Q&A |
| Protected tags | Documented as required manual GitLab setting | Plan can't apply UI config via commit; without it, tag gating isn't a real auth boundary | Plan Q&A |

## Scope

**In scope:**
- `backend/.gitlab-ci.yml` — rules migration, dedup, tag-based deploy gating
- `backend/docker-compose.prod.yaml` — parameterize image tag
- `backend/.env.example` — doc correction

**Out of scope:**
- `frontend/.gitlab-ci.yml` (identical pattern, separate follow-up)
- Prod secrets provisioning/rotation mechanism
- Automated rollback tooling
- `composer.json` `config.audit.ignore` (currently unused, audit clean)

## Architecture / Approach

Phase 1 is a behavior-preserving mechanical refactor (legacy syntax → `rules:`, dependency dedup via GitLab's cross-stage `needs:` DAG). Phase 2 is the actual behavior change: `docker-build` gains a conditional tag-push step, `deploy-production`'s trigger flips from branch to tag, and `docker-compose.prod.yaml`'s image reference becomes parameterized via an `IMAGE_TAG` env var exported in the deploy script. Phase 3 is an isolated one-line doc fix.

## Phases at a Glance

| Phase | What it delivers | Key risk |
|---|---|---|
| 1. Modernize syntax + dedup | `rules:` everywhere, no redundant installs, consistent main behavior | `needs:` misconfiguration could break pipeline creation on main if `composer` job is still excluded there |
| 2. Gate deploy on tag | Tag-triggered deploy, tag-pinned images, real migration failure handling, protected-tags requirement | `docker-compose.prod.yaml` env-var substitution must be verified working end-to-end before trusting it in production |
| 3. Fix `.env.example` | Doc matches reality | None — trivial, isolated |

**Prerequisites:** Access to configure GitLab project Settings → Repository → Protected tags (Maintainer or Owner role). A disposable/staging deploy target is ideal for verifying Phase 2's migration-failure behavior without risking real production data.

**Estimated effort:** ~1 session, 3 phases, single file plus two small companions.

## Open Risks & Assumptions

- Assumes `docker compose` on the deploy host substitutes `${IMAGE_TAG}` from the CI job's exported environment variable at the time the `docker compose ... pull` command runs (not from a `.env` file) — this is standard `docker compose` behavior but should be verified on first real tag deploy.
- Assumes tags will be cut from already-tested `main` commits; the plan doesn't add a check enforcing that a tag's commit passed CI on `main` first.
- Protected tags configuration is a manual step outside this plan's code changes — until configured, the tag-based gate is not a real authorization boundary.

## Success Criteria (Summary)

- A push to `main` no longer deploys to production — only a `vX.Y.Z` tag push does.
- A broken migration during deploy now fails the pipeline visibly instead of silently leaving production half-updated.
- CI pipeline runs faster/cleaner with no duplicate `composer install` calls, and behaves consistently across branch and main pipelines.
