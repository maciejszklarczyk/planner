# Fix Twig and Symfony security vulnerabilities — Plan Brief

> Full plan: `context/changes/fix/plan.md`
> Research: `context/changes/fix/research.md`

## What & Why

`composer audit` found 44 advisories total: 1 CRITICAL + 10 HIGH in `twig/twig` (v3.24.0) and three transitive `symfony/*` packages (v7.4.8), plus 6 lower-severity findings in `guzzlehttp/guzzle`, `guzzlehttp/psr7`, and `mtdowling/jmespath.php` (transitive via the AWS SDK). All are fixed by patch releases already inside this project's existing version constraints. One of the HIGH findings — a HEAD-request bypass on `#[IsGranted]`+GET-only routes — has real, live exposure: four endpoints in this codebase use exactly that pattern.

## Starting Point

`composer.json` pins Symfony to `7.4.*` and Twig to `^2.12|^3.24` — both ranges already allow the fixed versions (Symfony 7.4.12-14, Twig 3.28.0). No CI step currently catches vulnerable dependencies; `.gitlab-ci.yml`'s `test` stage only runs `php-cs-fixer` and `phpunit`.

## Desired End State

`composer audit --locked` reports zero advisories across all packages, the full 152-test suite still passes, and every future pipeline run includes a `composer audit` check — so a CVE of this severity fails CI automatically instead of waiting for a manual health check to surface it.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| CI audit scope | Bundle `composer audit` CI job into this change | The dependency bump is what makes the job pass on first run — doing them separately leaves a window where CI would fail | Plan |
| composer.json diff | Accept Composer's constraint normalization | Standard `composer update` behavior; keeps the manifest in sync with what's actually resolved | Plan |
| Mailer hardening | Verify only, no code change | `#[Assert\Email]` already present on `UserInviteDto::$email` — nothing to add | Research + Plan |
| Verification depth | Full suite + manual smoke test of the 4 exposed endpoints | Automated tests exercise the routes but not the exact HEAD-bypass vector; a manual pass gives direct confidence the CVE's specific pattern is closed | Plan |
| Twig sandbox / X509 / failure_forward | No code changes | None of these features are configured or in use in this codebase — confirmed via research, not exploitable regardless of the advisory severity | Research |

## Scope

**In scope:**
- `composer update twig/twig "symfony/*" guzzlehttp/guzzle guzzlehttp/psr7 mtdowling/jmespath.php --with-all-dependencies` (widened during plan review to close all 44 advisories, not just the CRITICAL/HIGH ones — see `reviews/plan-review.md` F1)
- Accepting the resulting `composer.json` constraint normalization
- Adding a `composer-audit` job to `.gitlab-ci.yml`'s `test` stage
- Full regression run + manual smoke test of the 4 CVE-exposed endpoints

**Out of scope:**
- `phpstan.dist.neon` config / phpstan CI job (separate, already-identified gap — different change)
- Any `InvitationMailer` or DTO validation changes (nothing to fix)
- Symfony major-version upgrade (7.4 → 8.x) — the `7.4.*` pin is intentional and unrelated

## Architecture / Approach

Pure dependency-version patch, no application code changes. Two sequential phases: (1) bump and verify, (2) close the CI gap that let this go undetected. The order matters — running the CI job before the bump would fail the pipeline on the very advisories this change fixes.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Upgrade vulnerable dependencies | `composer.lock`/`composer.json` updated, `composer audit` clean, full suite green, 4 exposed endpoints manually confirmed unchanged | Low — dry-run already confirmed zero conflicts; residual risk is an untested behavioral edge case in the 53 updated packages |
| 2. Add composer audit to CI | New CI job in the `test` stage catching future advisories automatically | Low — mirrors an existing, working job pattern; only real risk is CI YAML syntax error |

**Prerequisites:** None — dev stack already running, `.env.test` already configured.
**Estimated effort:** ~1 session, single sitting (dependency update + test run + one CI file edit).

## Open Risks & Assumptions

- Assumes the ~50 other transitive packages Composer proposes updating (beyond the 7 target packages) introduce no behavioral regressions — mitigated by the full 152-test suite + manual smoke test in Phase 1. If verification fails, Phase 1 now has an explicit rollback step (revert `composer.json`/`composer.lock` via git) rather than proceeding.
- Assumes GitLab CI's `composer:2.9.4` image supports `composer audit` without additional setup (it should — same image already used by other jobs).

## Success Criteria (Summary)

- `composer audit` reports zero advisories across all packages, including `twig/twig`, `symfony/http-kernel`, `symfony/mime`, `symfony/security-http`, `guzzlehttp/guzzle`, `guzzlehttp/psr7`, `mtdowling/jmespath.php`
- Full test suite passes unchanged; the 4 exposed endpoints behave identically under manual testing
- A pipeline run on this branch shows a passing `composer-audit` job in the `test` stage
