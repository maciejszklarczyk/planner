<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Fix Twig and Symfony security vulnerabilities

- **Plan**: context/changes/fix/plan.md
- **Scope**: Full plan (Phase 1 of 2, Phase 2 of 2)
- **Date**: 2026-07-12
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Success criteria independently re-verified: `composer audit --locked` (0 advisories), `composer validate` (valid), `.gitlab-ci.yml` YAML lint (valid), local dry-run script (clean), full test suite (148/152, same 4 pre-existing failures the implementer already diagnosed via baseline comparison during implementation — confirmed reproducible, unrelated to this change). Manual rows (1.4–1.7, 2.3) correctly left unchecked, no rubber-stamping. Drift-detection sub-agent confirmed exact plan-to-code match across `composer.json`, `composer.lock` package versions, `.gitlab-ci.yml`, and the auto-regenerated `config/reference.php`.

## Findings

### F1 — New composer-audit job shares an unnamed cache key with concurrent jobs

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: .gitlab-ci.yml:50-58 (pre-fix)
- **Detail**: `composer-audit` used `cache: paths: [vendor/]` with no explicit `key:`, same as `php-cs-fixer`/`phpunit`. GitLab falls back to a single shared cache slot per branch without an explicit key; the new job added a third concurrent writer to an already-shared slot in the `test` stage.
- **Fix**: Add `key: files: [composer.lock]` to all four `test`/`build` stage jobs sharing `vendor/` cache.
- **Decision**: FIXED — added `cache.key.files: [composer.lock]` to `php-cs-fixer`, `phpunit`, `composer-audit`, and `composer` (build) jobs. Commit 9569cee.

### F2 — No accepted-risk escape hatch if a future advisory has no fix

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: .gitlab-ci.yml:50-58, composer.json
- **Detail**: `composer-audit` has no `allow_failure`, and `composer.json` has no `config.audit.ignore` block. Fine today (audit is clean), but the first unfixable future CVE would hard-block all merges with no sanctioned escape hatch.
- **Fix**: Document the process in `backend/CLAUDE.md` — no code change needed today, just a policy note.
- **Decision**: FIXED — added a note under "Inne uwagi" in `backend/CLAUDE.md` pointing to `composer.json`'s `config.audit.ignore` as the sanctioned escape hatch. Commit 9569cee.

### F3 — Fifth redundant composer install per pipeline run

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: .gitlab-ci.yml:50-58
- **Detail**: `composer-audit` runs its own independent `composer install` rather than reusing the build-stage `composer` job's `vendor/` artifact. Pre-existing pattern (`phpunit`/`php-cs-fixer` already do this).
- **Fix**: Proposed `needs:` reuse of the build-stage artifact turned out to be infeasible — this repo's `stages:` order is `test → secret-detection → build → ...`, so `composer` (build) runs *after* `composer-audit` (test); a forward `needs:` reference across that ordering is invalid in GitLab CI. Reusing the artifact would require reordering stages, out of scope for this observation.
- **Decision**: SKIPPED — mirrors existing (imperfect) convention faithfully; restructuring stage order is a separate, larger decision.
