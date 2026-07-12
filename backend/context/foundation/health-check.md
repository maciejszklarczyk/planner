---
project: planner/backend
checked_at: 2026-07-12T14:05:59Z
context_type: brownfield
health: needs-attention
audit:
  critical: 0
  high: 0
  moderate: 0
  low: 0
  unrated: 0
test_runner: PHPUnit 12.5 (detected, working, 152 tests collected, 2 failing — both pre-existing/unrelated)
ci_provider: GitLab CI
stack_assessment_linked: true
---

## Change since the last check

This is a re-run. The previous `context/foundation/health-check.md` (same session, earlier) found 1 CRITICAL + 10 HIGH `composer audit` advisories and no `composer-audit` CI job. Since then, that work was planned, implemented, reviewed, merged (`planner6551704/backend!38`), deployed, and archived to `context/archive/2026-07-12-fix/`. This check reflects the **post-fix state**.

## Dependency audit

**Lockfile**: `composer.lock` present. Reproducible builds — pass.

**Audit** (`composer audit --locked --format json`): **zero advisories** — the previous 44-advisory finding (1 CRITICAL, 10 HIGH, 19 MODERATE, 10 LOW, 4 unrated) is fully resolved. `twig/twig`, `symfony/http-kernel`, `symfony/mime`, `symfony/security-http`, `guzzlehttp/guzzle`, `guzzlehttp/psr7`, and `mtdowling/jmespath.php` are all confirmed patched.

**Outdated dependencies** (`composer outdated --direct`): 22 packages show a major-version gap. The bulk (20) are `symfony/*` packages showing `7.4.x → 8.1.x` — this reflects the project's intentional `7.4.*` pin (`composer.json extra.symfony.require`), not an oversight; not flagged as urgent. Two genuine gaps worth a look when convenient: `phpunit/phpunit` (12.5.17 → 13.2.4) and `phpdocumentor/reflection-docblock` (5.6.7 → 6.0.3) — both dev-only, low urgency.

## Test infrastructure

**Test runner**: PHPUnit 12.5, working — 152 tests collected and run without error via `bin/phpunit --list-tests` and a full run.

**Result: 150/152 pass, 2 failures** — both **pre-existing and unrelated** to the recently-shipped security patch, tied to still-uncommitted work-in-progress currently sitting in the working tree (`src/Controller/EventController.php`, `src/Entity/Event.php`, `src/Entity/User.php`, `fixtures/events.yaml` — all dirty, uncommitted since before this session started):

- `EventControllerTest::testItemCreateRequest` — `SQLSTATE[23000]: NOT NULL constraint failed: event.owner_id`, a data-integrity bug in the in-progress Event feature.
- `UserRepositoryTest::testUpgradePasswordUpdatesPassword` — `assert($owningAssoc->isManyToOne())` inside Doctrine's `BasicEntityPersister`, traced to a suspicious `OneToMany(... mappedBy: 'participants' ...)` mapping on `User.php:73` that looks like it should be a `ManyToMany`.

This was independently confirmed during the security-fix implementation via baseline comparison (same 2 of these 4 original failures reproduced identically with both old and new dependency versions). **Flagging again here because a health check should never treat known-failing tests as background noise** — even when root-caused to unrelated WIP, 2 failing tests mean an agent can't fully trust a green run today, and this WIP should be finished or reverted before it's relied on.

## CI/CD

**Provider**: GitLab CI (`.gitlab-ci.yml`), stages: `test` → `secret-detection` → `build` → `lint` → `docker-build` → `deploy`.

| Stage | Present |
|---|---|
| Lint (style — php-cs-fixer) | ✓ |
| Lint (config/container — `lint` stage) | ✓ (`composer validate`, `lint:yaml`, `lint:container`) |
| Test (phpunit) | ✓ |
| Build | ✓ |
| Type check (phpstan) | ✗ — still not present anywhere in the pipeline |
| Security scan (dependency) | ✓ — **new since the last check**: `composer-audit` job now runs in the `test` stage, blocking merges on any advisory |
| Security scan (secrets) | ✓ (GitLab `Secret-Detection` template, unchanged) |

**Improvement since the last check**: dependency-vulnerability scanning is now enforced in CI (`composer-audit` job, `.gitlab-ci.yml:56-64`), closing the exact gap the earlier health check flagged as the reason 44 advisories went undetected. The job shares a deterministic `cache.key` (`files: [composer.lock]`) with the other `vendor/`-caching jobs, added during that change's own implementation review to close a cache-race risk.

**Unchanged gap**: no `phpstan` job exists. This is the same static-analysis-not-enforced gap `context/foundation/stack-assessment.md` identified originally — explicitly out of scope for the security-fix change (documented in that change's "What We're NOT Doing"), so its persistence here is expected, not a regression.

## Configuration completeness

| File | Status |
|---|---|
| `.gitignore` | present |
| `.editorconfig` | present |
| `.env.example` | present |
| `.php-cs-fixer.dist.php` | present |
| `phpstan.neon` / `phpstan.dist.neon` | **still missing** — unchanged from the prior check |
| `phpunit.xml.dist` (stale duplicate of `phpunit.dist.xml`) | **still present** — unchanged, already documented as known in `backend/CLAUDE.md` |
| `CLAUDE.md` (instruction file) | present, and now includes a `composer-audit`/`config.audit.ignore` policy note (added during the security-fix implementation review) |

**Partially closed, partially open**: `stack-assessment.md`'s recommended `## Static analysis` instruction-file addition (documenting that phpstan output is advisory-only) was **not** added to `backend/CLAUDE.md` — only the newer `composer-audit` policy note landed, from a different change. The phpstan compensation text stack-assessment drafted is still sitting unapplied.

## Cross-reference with stack assessment

`context/foundation/stack-assessment.md` (verdict: `ready-with-compensation`) identified one real gap: phpstan installed but unconfigured and not wired into CI. That gap is **unchanged** — still open, still the single quality-gate-adjacent issue in an otherwise agent-friendly PHP/Symfony stack. The recommended `backend/CLAUDE.md` addition documenting this has also not yet been applied.

## Prioritized fixes

1. **Finish or revert the in-progress Event feature WIP** (`EventController.php`, `Event.php`, `User.php`, `fixtures/events.yaml`) — currently causing 2 failing tests via a NOT NULL constraint violation and a suspicious Doctrine relation mapping (`User.php:73`, `mappedBy: 'participants'` on what should likely be a `ManyToMany`).
   Impact: an agent (or human) running the test suite today sees 2 failures and has to already know they're unrelated WIP to trust the rest of the suite — that tribal knowledge doesn't scale.
   Fix: either commit/finish the Event participants feature (fixing the entity mapping and the `owner_id` NOT NULL violation), or `git stash`/discard if it's abandoned work.
   Effort: moderate to significant, depending on how far along the feature is — not scoped by this check.

2. **Add `phpstan.dist.neon` and a phpstan CI job.** Unchanged from the original stack assessment — still the one real toolchain-enforcement gap in this stack.
   Fix: pin an explicit level (5–6 given the codebase's consistent `strict_types` discipline) in `phpstan.dist.neon`, then add a job to `.gitlab-ci.yml`'s `test` stage mirroring `php-cs-fixer`/`phpunit`/`composer-audit`.
   Effort: moderate (15–30 min for initial level tuning).

3. **Add the phpstan advisory-only note to `backend/CLAUDE.md`.** Already drafted in `context/foundation/stack-assessment.md`'s "Recommended Instruction File Additions" — just needs pasting in, ideally alongside item 2 above.
   Effort: quick (< 5 min).

4. **Remove the stale `phpunit.xml.dist`.** Unchanged, low-priority cleanup already noted twice now.
   Effort: quick (< 5 min).

5. **Consider `phpunit/phpunit` 12→13 and `phpdocumentor/reflection-docblock` 5→6 upgrades** when convenient — both dev-only, no urgency.
   Effort: quick to moderate depending on breaking changes in phpunit 13.

## Summary

**Overall health: needs-attention** — a step better than a plain "healthy" only because of the 2 known-failing tests tied to in-progress work sitting in the working tree; everything else that was flagged as urgent in the previous check is now resolved. The dependency-vulnerability gap that drove the last check's verdict is fully closed (0 advisories, was 44) and is now actively guarded by CI (`composer-audit` job, new since last check). The remaining items — unfinished Event WIP, the still-open phpstan gap, and one unapplied instruction-file addition — are all well-understood, already-diagnosed, and quick to close; none of them are surprises.
