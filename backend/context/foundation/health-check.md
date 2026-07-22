---
project: planner/backend
checked_at: 2026-07-22T20:25:05Z
health_status: needs-attention
context_type: brownfield
language_family: php
stack_assessment_available: true
checks_run:
  - lockfile
  - dependency_audit
  - outdated_deps
  - test_runner
  - ci_cd
  - configuration
audit_findings:
  critical: 0
  high: 0
  moderate: 4
  low: 0
test_runner_detected: true
ci_provider: GitLab CI
recommended_fixes: 3
---

## Dependency Health

### Lockfile

```
Status: present (composer.lock)
Package manager: composer
```

### Security Audit

```
Tool: composer audit --format json
Summary: 0 CRITICAL, 0 HIGH, 4 MODERATE, 0 LOW
Direct vs transitive: all 4 findings are transitive (guzzlehttp/guzzle,
pulled in via aws/aws-sdk-php ^7.4.5, required by league/flysystem-aws-s3-v3)
```

#### MODERATE findings

- **guzzlehttp/guzzle** 7.14.0 — PKSA-fy2t-3c5f-827y: URI fragments disclosed in redirect Referer headers (affects <7.15.1).
- **guzzlehttp/guzzle** 7.14.0 — PKSA-qxvb-2bpp-dnk6: Host-only cookie scope is not preserved (affects <7.15.1).
- **guzzlehttp/guzzle** 7.14.0 — PKSA-bbs6-q5q9-f3t4: Unbounded response cookies risk denial of service (affects <7.15.1).
- **guzzlehttp/guzzle** 7.14.0 — PKSA-pwsk-hy21-4gby: Proxy-Authorization headers can be sent to origin servers (affects <7.14.2).

All four resolve by bumping the transitive `guzzlehttp/guzzle` constraint to
`>=7.15.1`; `aws/aws-sdk-php`'s own constraint (`^7.4.5`) already permits
that version, so no direct dependency needs a version bump.

### Outdated Dependencies

```
Packages with major version gaps: 3 (of 30 direct dependencies checked)
```

- **symfony/* (framework-bundle, security-bundle, serializer, etc.)**: v7.4.x → v8.1.x (1 major version ahead). Project intentionally pins `7.4.*` (`extra.symfony.require` in `composer.json`) — likely deliberate LTS choice, not an oversight.
- **phpunit/phpunit**: 12.5.17 → 13.2.4 (1 major version ahead).
- **phpdocumentor/reflection-docblock**: 5.6.7 → 6.0.3 (1 major version ahead).

The remaining ~27 direct dependencies are on semver-safe minor/patch
updates only (e.g. `doctrine/orm` 3.6.3 → 3.6.7, `nelmio/api-doc-bundle`
v5.10.0 → v5.10.3) — routine, not flagged individually.

## Test Suite

```
Test runner: PHPUnit
Tests found: 152 tests
Test execution: passing (152 tests, 297 assertions, 0 failures)
```

```
Configuration: phpunit.dist.xml
Framework: PHPUnit 12.5.17
```

Full suite executed with `.env.test` loaded (per project convention) —
completed in 2.4s.

## CI/CD

```
Provider: GitLab CI
Configuration: .gitlab-ci.yml
```

| Stage      | Status | Notes                                                        |
|------------|--------|----------------------------------------------------------------|
| Lint       | ✓      | `php-cs-fixer fix --dry-run --diff` in the `test` stage         |
| Test       | ✓      | `phpunit` job with pcov coverage → Cobertura report             |
| Build      | ✓      | `composer install` in the `build` stage                        |
| Type check | ✗      | no `phpstan` job — PHPStan is installed but not wired into CI  |
| Security   | ✓      | `composer-audit` job + GitLab `Secret-Detection` template       |

Additional stages beyond the schema's five: `lint` also runs
`composer validate`, `bin/console lint:yaml`, `bin/console lint:container`;
`docker-build` and `deploy-production` (tag-triggered) round out the
pipeline.

## Configuration

```
All expected configuration files present. No gaps detected.
```

`.editorconfig`, `.php-cs-fixer.dist.php`, `.gitignore`, `.env.example`,
`CLAUDE.md` (root + `backend/`), and `AGENTS.md` are all present. `.env` is
committed but contains only non-secret defaults per Symfony convention —
`.gitignore` correctly excludes `.env.local` / `.env.*.local` for real
secrets, so this is the expected pattern, not a gap.

The one missing piece — a PHPStan configuration file (`phpstan.neon` /
`phpstan.dist.neon`) — is already tracked as the central finding in
`context/foundation/stack-assessment.md` and is not duplicated here as a
separate configuration gap; see the cross-reference below.

## Stack Assessment Cross-Reference

```
Stack assessment: context/foundation/stack-assessment.md
Agent readiness (from stack-assess): ready-with-compensation
```

| Quality Gate Gap                                   | Health-Check Finding                                                                 | Status     |
|------------------------------------------------------|----------------------------------------------------------------------------------------|------------|
| Typed (pass, with note): PHPStan not enforced        | Confirmed — no `phpstan` job in `.gitlab-ci.yml`, no `phpstan.neon` config file found  | Reinforced |
| Convention-based: pass                                | `backend/CLAUDE.md` documents the `Controller/Entity/Repository/Service/Dto` layout    | Mitigated (already strong) |
| Test infrastructure (not a stack-assess gate, but relevant) | 152 tests, all passing, wired into CI with coverage reporting                    | Strength — no gap |

The compensation entry stack-assess recommended for `backend/CLAUDE.md`
(a `## Static analysis` block) has **not yet been added** — `backend/CLAUDE.md`
currently has no such section. This is worth closing since the gap it
documents is still open.

## Recommended Fixes

### 1. Wire PHPStan into CI (or, at minimum, document that it isn't enforced)

**Impact**: an agent editing entities, DTOs, or service signatures gets no
automated type-safety feedback — only PHP's own type declarations and
code review catch mismatches, so regressions surface at runtime instead
of in CI.
**Severity**: high
**Effort**: moderate (15–30 min)
**Fix**:

```bash
# 1. Add a baseline config so existing code passes cleanly at a chosen level
vendor/bin/phpstan analyse src tests --memory-limit 1G --generate-baseline
# creates phpstan-baseline.neon; then create phpstan.dist.neon that includes it
# and pins a level (start at 5-6 given the existing typed codebase)

# 2. Add a job to .gitlab-ci.yml's `test` stage, mirroring the existing
#    php-cs-fixer / phpunit jobs:
#    phpstan:
#      stage: test
#      image: composer:2.9.4
#      needs: [composer]
#      script:
#        - vendor/bin/phpstan analyse src tests --memory-limit 1G
```

Until this lands, paste the compensation block from
`context/foundation/stack-assessment.md` into `backend/CLAUDE.md` so an
agent knows not to over-trust an unenforced check.

### 2. Update `guzzlehttp/guzzle` to close 4 MODERATE advisories

**Impact**: transitive HTTP client used by the S3 filesystem adapter has
4 known moderate-severity issues (cookie/header leakage, DoS via
unbounded cookies) below `7.15.1`. Not exploitable unless the S3 adapter
is actively used against untrusted redirect targets, but cheap to close.
**Severity**: moderate
**Effort**: quick (< 5 min)
**Fix**:

```bash
composer update guzzlehttp/guzzle --with-dependencies
composer audit --format json   # confirm the 4 advisories are gone
```

### 3. Plan the Symfony 7.4 → 8.1 upgrade path (informational, not urgent)

**Impact**: none today — `composer.json` intentionally pins `7.4.*`
(`extra.symfony.require`), and 7.4 is still a fully supported line. Flagged
only so the major-version gap doesn't silently grow.
**Severity**: low
**Effort**: significant (> 1 hour, whenever it's scheduled)
**Fix**: no action needed now; revisit when Symfony 7.4 approaches
end-of-support, or when a specific 8.x feature is needed. Same applies to
`phpunit` 12→13 and `phpdocumentor/reflection-docblock` 5→6 — both are
dev-only tooling, safe to defer.

## Summary

Health status: needs-attention

The project is in strong shape: dependencies are locked, no CRITICAL or
HIGH security findings, all 152 tests pass, and CI already covers
linting, testing, building, and secret scanning. The one real gap —
PHPStan installed but not enforced anywhere — was already identified by
`/10x-stack-assess` and is the single thing keeping this from a clean
"healthy" verdict; it's a 15–30 minute fix. The 4 MODERATE dependency
advisories are a quick `composer update` away from closed.

Next step: close fix #1 (PHPStan in CI) and #2 (guzzle update) — both are
small — then this project is ready for agent-assisted feature work on the
`trip-domain-model` change (Event participants, Friendship) already
scoped in `context/foundation/prd.md`.
