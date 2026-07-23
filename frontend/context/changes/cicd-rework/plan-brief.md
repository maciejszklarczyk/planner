# CI/CD Rework — Plan Brief

> Full plan: `context/changes/cicd-rework/plan.md`
> Research: `context/changes/cicd-rework/research.md`

## What & Why

The frontend's `.gitlab-ci.yml` is a narrow, main-only deploy pipeline that only gates code already merged — nothing runs on MRs, there's no caching, no security scanning, no test-result visibility, and deploys are a hard-downtime `down`/`up` with a fake healthcheck (`node -v`) and no deliberate release step. This plan closes those gaps, largely by porting patterns already proven in `backend/.gitlab-ci.yml`.

## Starting Point

3 stages (`quality`, `docker-build`, `deploy`), all `only: [main]`. Quality stage (lint/tsc/test/format) was added 2026-07-13 to close a health-check finding but was deliberately scoped narrow — caching, security scanning, and CI restructuring were explicitly deferred as follow-up work. `docker-compose.prod.yaml` deploys the mutable `:latest` tag on every `main` push.

## Desired End State

Quality checks run on every MR, catching broken code before merge. `npm audit` + GitLab Secret Detection run automatically. `node_modules` and Docker layers are cached. Test/coverage results show up in the MR widget. Releases require a deliberate version tag (`vX.Y.Z`) — merging to `main` no longer auto-deploys. The deploy step uses `up -d --wait` against a real HTTP healthcheck plus a post-deploy `curl -f` smoke test, replacing the current dead-container window and fake healthcheck.

## Key Decisions Made

| Decision | Choice | Why | Source |
|---|---|---|---|
| MR gating | Full quality stage on MRs; build/deploy stay main/tag-only | Catches broken code pre-merge without wasting build/registry resources on unmerged images | Plan (user) |
| Security scanning | `npm audit --audit-level=high` + GitLab Secret Detection template | Ports backend's already-proven pattern; directly covers the CVE class health-check.md flagged | Plan (user) |
| Caching | `node_modules` cache keyed on `package-lock.json` | Cuts cold `npm ci` time every run; mirrors backend's `vendor/` cache | Plan (user) |
| Docker caching | Registry-based `--cache-from`/BuildKit inline cache | No new infra, reuses existing registry; risk-flagged pending runner BuildKit support | Plan (user) |
| Deploy sequence | `up -d --wait` (drop `down`), real HTTP healthcheck | Removes dead-container window using Compose's own readiness gate, no new infra | Plan (user) |
| Release gate | Deploy only on version tags; `docker-build` on main+tags | Decouples merge from release, matches backend's exact pattern | Plan (user) |
| Health check + smoke test | New `/api/health` route + Dockerfile/compose healthcheck + CI `curl -f` | Closes the biggest deploy-safety gap — nothing today verifies the app actually serves traffic post-deploy | Plan (user) |
| Test reporting | JUnit + cobertura coverage artifacts, report-only (no threshold gate) | Matches backend's `phpunit` job; coverage is thin today so a threshold would be premature | Plan (user) |
| Rollback | Manual, documented tag-redeploy runbook — no automated rollback job | `up -d --wait` already prevents the worst failure mode (dead container); automation is overkill for a low-frequency single-VPS deploy | Plan (user) |
| Out of scope | No staging environment, no failure notifications | Keeps scope to structural gaps research flagged; staging needs its own DNS/Traefik setup, deserves a separate change | Plan (user) |

## Scope

**In scope:** MR-triggered quality gate, `npm audit` + secret detection, `node_modules`/Docker caching, JUnit + coverage artifacts, tag-gated release flow, real health endpoint + healthcheck fix, `up -d --wait` deploy sequence, post-deploy smoke test.

**Out of scope:** Staging environment, Slack/email notifications, automated rollback job, blue-green/zero-downtime deploy, TypeScript major bump, `typecheck` npm script, coverage threshold enforcement.

## Architecture / Approach

Five phases, each independently verifiable: (1) trigger rules first since it's the foundational MR-gating fix everything else benefits from, (2) security scanning and (3) caching slot into the same `quality-checks` job without touching triggers further, (4) test artifacts are a low-risk addition to the same job, and (5) release/deploy safety — the highest-risk, production-touching phase — comes last so it can be validated in isolation via a real tagged deploy.

## Phases at a Glance

| Phase | What it delivers | Key risk |
|---|---|---|
| 1. Pipeline triggers | `quality-checks` runs on MRs + main via `rules:`, deduped via `workflow:rules` | Low — no behavior change to what checks run, only when |
| 2. Security scanning | `npm audit --audit-level=high` + GitLab Secret Detection | Low — ports proven backend pattern |
| 3. Caching | `node_modules` cache + Docker registry layer cache | Medium — Docker cache depends on unverified runner BuildKit support; has a documented fallback |
| 4. Test reporting artifacts | JUnit + cobertura coverage surfaced in MR widget | Low — report-only, no gating behavior added |
| 5. Release & deploy safety | Tag-gated deploy, new `/api/health` route, real healthchecks, `up -d --wait`, smoke test | High — touches production deploy behavior directly; requires a real tagged deploy to validate |

**Prerequisites:** none blocking — can start immediately on the current `feature/cicd-rework` branch.
**Estimated effort:** ~1-2 sessions across 5 phases; Phase 5 requires coordinating a real deploy window.

## Open Risks & Assumptions

- Docker BuildKit inline caching support on the GitLab runner (`docker:latest` + `DOCKER_TLS_CERTDIR: ""`) is unverified — Phase 3 has an explicit fallback (drop cache flags) if it doesn't work.
- Phase 5's `up -d --wait` still means a brief single-container restart blip, not true zero-downtime — accepted given the single-VPS/Traefik topology; a blue-green setup was explicitly ruled out as disproportionate scope.
- The team needs to adopt a `git tag vX.Y.Z && git push origin vX.Y.Z` release habit once Phase 5 lands — deploys no longer happen automatically on merge.

## Success Criteria (Summary)

- A throwaway MR with a deliberately broken test/lint fails its pipeline before merge is possible.
- Merging to `main` builds and pushes an image but does **not** deploy; pushing a version tag does.
- A tagged deploy shows no extended downtime window, and the final `curl -f https://planner.msolve.it/api/health` step passes against the live site.
