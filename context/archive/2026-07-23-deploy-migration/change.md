---
change_id: deploy-migration
title: Migrate deployment (docker-build + deploy-production) from GitLab CI to GitHub Actions
status: archived
created: 2026-07-23
updated: 2026-07-27
archived_at: 2026-07-27T20:11:50Z
---

## Notes

Follow-up to `cicd-migration` (archived at `context/archive/2026-07-23-cicd-migration/`), which explicitly skipped deployment. This change migrates the remaining `docker-build`/`deploy-production` jobs (backend + frontend) from the now-deleted `.gitlab-ci.yml` files to GitHub Actions.

Relevant context already gathered:
- `context/archive/2026-07-23-cicd-migration/research.md` has a "Follow-up Research" section on `../car-repair-tracker`'s existing GitHub Actions deploy setup — a sibling project already deploying to the same homelab server via a self-hosted GitHub Actions runner (`runs-on: self-hosted`), gated on GitHub Releases (`release: types: [published]`), with `docker/login-action` + `docker/build-push-action` pushing to GHCR.
- Current (deleted, but recoverable via git history) `backend/.gitlab-ci.yml` / `frontend/.gitlab-ci.yml` `docker-build`/`deploy-production` jobs: tag-gated (`$CI_COMMIT_TAG =~ /^v\d+\.\d+\.\d+$/`), push to `$CI_REGISTRY_IMAGE`, SSH-less deploy via `docker compose pull && down && up -d` directly on the runner (implying GitLab's runner already had homelab access — needs to be replicated with a GitHub self-hosted runner).
- Open questions likely needing research/planning: registry choice (GHCR vs current `$CI_REGISTRY`), whether to adopt car-repair-tracker's GitHub Releases-gated pattern or keep semver-tag gating, self-hosted runner setup/registration on the homelab server, migration DB step (`doctrine:migrations:migrate`) currently baked into backend's deploy job, and health-check verification (`curl -f https://api-planner.msolve.it/health`).
