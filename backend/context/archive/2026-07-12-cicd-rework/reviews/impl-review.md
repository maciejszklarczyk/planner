<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: CI/CD Rework

- **Plan**: context/changes/cicd-rework/plan.md
- **Scope**: Full plan (Phase 1 of 3 - Phase 3 of 3)
- **Date**: 2026-07-12
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Findings

### F1 — IMAGE_TAG fallback silently reintroduces floating :latest

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: docker-compose.prod.yaml:3
- **Detail**: `image: ${CI_REGISTRY_IMAGE:-...}:${IMAGE_TAG:-latest}` defaults to `:latest` when IMAGE_TAG unset. Plan's whole Phase 2 point was killing the floating-`:latest` deploy. CI job always sets IMAGE_TAG first (verified), so the normal pipeline path is safe — but a manual `docker compose up` on the host, or any future script that forgets the export, silently deploys `:latest` again instead of failing.
- **Fix A ⭐ Recommended**: Hard-fail on missing IMAGE_TAG — change to `${IMAGE_TAG:?IMAGE_TAG must be set}`.
  - Strength: Matches stated goal exactly; missing tag becomes a loud error, not a silent regression to old behavior.
  - Tradeoff: Breaks bare `docker compose up` on the host without IMAGE_TAG exported (e.g. local debugging) — operator must always set it, even to `latest` explicitly.
  - Confidence: HIGH — one-line change, no other call sites depend on the default.
  - Blind spot: Haven't checked if any host-side script/cron relies on the implicit `:latest` default.
- **Fix B**: Keep default but document it as an intentional local-dev convenience, don't touch prod path.
  - Strength: Zero risk of breaking anything, no behavior change.
  - Tradeoff: Leaves the exact gap this finding describes — a regression path back to floating-tag deploys.
  - Confidence: MED — depends on whether that gap is acceptable risk.
  - Blind spot: None significant.
- **Decision**: FIXED (via Fix A — docker-compose.prod.yaml:3 now `${IMAGE_TAG:?IMAGE_TAG must be set}`)

### F2 — Stale "remove when: manual" instruction in setup doc

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: docs/DOCKER-EXECUTOR-SETUP.md:264-270
- **Detail**: Phase 3's plan item explicitly targeted "manual-trigger references" for correction. Section 9/Krok 2 was fixed, but Section 11 ("Wyłącz manual trigger (automatyczny deploy)") still tells the operator to remove a `when: manual` line from `deploy-production` — a line that job never had; it's driven purely by `rules:`. Leftover, now internally inconsistent with the corrected Section 9.
- **Fix**: Delete Section 11 (or rewrite it to state deploy-production already runs automatically on tag push, no manual step exists).
- **Decision**: FIXED (Section 11's "Wyłącz manual trigger" subsection removed from docs/DOCKER-EXECUTOR-SETUP.md)

### F3 — No post-deploy health check

- **Severity**: 👁️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: .gitlab-ci.yml:107
- **Detail**: `docker compose ps` after deploy only prints container status, not an HTTP/health check — a container can show "Up" and still be unhealthy. Not part of plan scope; noting for a future follow-up.
- **Fix**: Add a `curl -f` against a health endpoint after cache:clear, fail the job if it doesn't return 2xx.
- **Decision**: FIXED (.gitlab-ci.yml:107 now runs `curl -f https://api-planner.msolve.it/health` after `docker compose ... ps` — uses existing HealthCheckController's `/health` endpoint, not `/api/doc`)

## Notes

Pre-existing, out-of-scope items found but not flagged (confirmed via `git show ddf4c5a^` predating this change): `docker login -u ... -p $CI_REGISTRY_PASSWORD` (CLI-arg password, .gitlab-ci.yml:81,96) and a commented-out Traefik htpasswd hash (docker-compose.prod.yaml:21).

All automated success criteria re-verified directly: YAML parses clean (`python3 -c "import yaml; yaml.safe_load(open('.gitlab-ci.yml'))"`), `grep -n "env.prod"` returns nothing in `.env.example` or `docs/DOCKER-EXECUTOR-SETUP.md`. Plan-drift sub-agent confirmed all Phase 1-3 "Changes Required" items MATCH the plan's contracts.
