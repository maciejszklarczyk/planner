---
project: planner
checked_at: 2026-07-27T20:31:41Z
health_status: needs-attention
context_type: brownfield
language_family: multi
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
  high: 4
  moderate: 0
  low: 0
test_runner_detected: true
ci_provider: GitHub Actions
recommended_fixes: 4
---

This is a two-component monorepo (`backend/` PHP+Symfony, `frontend/` TypeScript+Next.js) — findings below are split by component where they differ.

## Dependency Health

### Lockfile

```
Backend  — Status: present (composer.lock)   — Package manager: composer
Frontend — Status: present (package-lock.json) — Package manager: npm
```

### Security Audit

```
Backend  — Tool: composer audit --format json
           Summary: 0 CRITICAL, 0 HIGH, 0 MODERATE, 0 LOW

Frontend — Tool: npm audit --json
           Summary: 0 CRITICAL, 4 HIGH, 0 MODERATE, 0 LOW
           Direct vs transitive: 1 direct (next), 3 transitive (brace-expansion, postcss, sharp)
```

Backend is clean — no action needed.

#### HIGH findings (frontend)

- **next** `16.2.10` (installed; range `>=16.0.0 <16.2.11`) — direct dependency, 3 stacked advisories: Middleware/Proxy bypass in App Router + Turbopack ([GHSA-6gpp-xcg3-4w24](https://github.com/advisories/GHSA-6gpp-xcg3-4w24)), DoS via Server Actions ([GHSA-m99w-x7hq-7vfj](https://github.com/advisories/GHSA-m99w-x7hq-7vfj)), SSRF in Server Actions on custom servers ([GHSA-89xv-2m56-2m9x](https://github.com/advisories/GHSA-89xv-2m56-2m9x)). Fix: bump to `16.2.11+` (`package.json` already declares `^16.2.10`, so `npm install next@latest` — or just `npm update next` — resolves within the existing semver range).
- **brace-expansion** (transitive, via `eslint-config-next`) — range `<=5.0.7`, DoS via exponential/unbounded expansion ([GHSA-3jxr-9vmj-r5cp](https://github.com/advisories/GHSA-3jxr-9vmj-r5cp), [GHSA-mh99-v99m-4gvg](https://github.com/advisories/GHSA-mh99-v99m-4gvg)). Fix available via `npm audit fix`.
- **postcss** (transitive) — range `<=8.5.17`. Fix available via `npm audit fix`.
- **sharp** (transitive) — range `<0.35.0`. Fix available via `npm audit fix`.

All four report `fixAvailable: true` — no forced/breaking resolution needed for any of them.

### Outdated Dependencies

```
Backend  — Packages with major version gaps (2+): 0
Frontend — Packages with major version gaps (2+): 1
```

Backend's Symfony stack is one major behind (`7.4.x` → `8.1.x`, all `update-possible` not `semver-safe-update`) — a normal LTS-to-next-major gap, not urgent, and a coordinated bump (framework + all bundles together) rather than a quick fix.

Frontend:
- **typescript**: `5.9.3` → `7.0.2` (2 major versions behind)
- **@types/node**: `20.19.39` → `26.1.2` (nominally 6 majors, but this just tracks Node's own version numbering — low real risk, ok to defer)
- Everything else outdated is a minor/patch gap (28 packages), not flagged individually.

## Test Suite

```
Backend  — Test runner: PHPUnit 12.5.17
           Tests found: 30+ (enumerated via --list-tests; Entity, Security/Voter, Service coverage)
           Test execution: enumerable, not re-run in full here (already verified passing in CI per backend-ci.yml's phpunit job)
           Configuration: backend/phpunit.dist.xml

Frontend — Test runner: Vitest 4.1.10
           Tests found: 1 test file, 2 tests (hooks/useDeleteGroup.test.ts)
           Test execution: passing (ran directly: `npx vitest run`)
           Configuration: frontend/vitest.config.ts
```

Both runners work. Frontend's suite is real but thin — only one hook has test coverage despite `frontend/CLAUDE.md` documenting a hooks-based mutation pattern used throughout the app. Not a blocking finding (the runner works, CI enforces it), but worth expanding as a matter of course, not urgency.

## CI/CD

```
Provider: GitHub Actions
Configuration: .github/workflows/backend-ci.yml, .github/workflows/frontend-ci.yml
              (+ backend-deploy.yml, frontend-deploy.yml, out of scope for CI coverage)
```

| Stage      | Backend | Frontend | Notes                                                              |
|------------|---------|----------|---------------------------------------------------------------------|
| Lint       | ✓       | ✓        | php-cs-fixer + `lint:yaml`/`lint:container` (backend), eslint (frontend) |
| Test       | ✓       | ✓        | phpunit w/ coverage upload (backend), vitest (frontend)              |
| Build      | ✓*      | ✗        | backend has `lint:container` (config compiles); frontend has **no** `next build` step in CI — only `tsc --noEmit` |
| Type check | ✓       | ✓        | phpstan level 6 (backend), `tsc --noEmit` (frontend)                 |
| Security   | ✓       | ✗        | `composer audit --locked` + gitleaks secret-scan (backend); frontend has **no** `npm audit` step |

Backend's CI is thorough — five jobs covering style, static analysis, tests+coverage, dependency audit, config lint, and secret scanning. Frontend's CI is solid on lint/type/test but has two real gaps: no `next build` verification (a build-breaking change could merge to `main` and only surface at deploy time, which is release-gated and may happen much later) and no automated dependency audit (the 4 HIGH findings above would not have been caught by CI as it stands).

## Configuration

```
Backend  — .editorconfig ✓, .gitignore ✓, .env.example ✓, .php-cs-fixer.dist.php ✓
Frontend — .editorconfig ✓, .gitignore ✓, .env.example ✓, .prettierrc ✓, eslint.config.mjs ✓, tsconfig.json (strict: true) ✓
```

All expected configuration files present. No gaps detected.

## Stack Assessment Cross-Reference

```
Stack assessment: context/foundation/stack-assessment.md
Agent readiness (from stack-assess): ready
```

The stack assessment found no quality-gate gaps (8/8 pass), so there's nothing to reinforce or mitigate here — the two reports agree: the stack choice is sound, and the gaps found in this check are operational (a stale dependency, two missing CI stages on one side), not structural.

## Recommended Fixes

### 1. Update `next` past 16.2.11 and run `npm audit fix`

**Impact**: three HIGH-severity advisories (middleware bypass, DoS, SSRF) are live in the frontend's direct dependency tree; an agent modifying frontend code has no automated signal that it's building on a known-vulnerable version.
**Severity**: high
**Effort**: quick (< 5 min)
**Fix**:

```bash
cd frontend
npm update next
npm audit fix
npm audit --json   # confirm 0 HIGH/CRITICAL remain
```

### 2. Add a dependency-audit stage to frontend CI

**Impact**: backend already fails its pipeline on any `composer audit` advisory; frontend has no equivalent, so HIGH/CRITICAL npm vulnerabilities (like the ones just found) can land on `main` unnoticed until someone runs `npm audit` by hand.
**Severity**: high
**Effort**: quick (< 5 min)
**Fix**: add a step to `.github/workflows/frontend-ci.yml`'s `quality-checks` job (or a new job), e.g.:

```yaml
      - run: npm audit --audit-level=high
```

(Match backend's convention of failing on advisories with no upstream fix documented via an ignore-list, if you want parity — see `backend/CLAUDE.md`'s note on `composer.json` → `config.audit.ignore`.)

### 3. Add a `next build` step to frontend CI

**Impact**: frontend CI currently verifies types (`tsc --noEmit`) and lint, but not that the app actually builds. A build-breaking change (bad import, invalid `next.config.ts`, etc.) can merge to `main` and only fail at deploy time — which is release-gated, so the failure surfaces much later and further from the change that caused it.
**Severity**: medium
**Effort**: quick (< 5 min)
**Fix**: add to `.github/workflows/frontend-ci.yml`:

```yaml
      - run: npm run build
```

### 4. Consider the TypeScript 5→7 gap when convenient

**Impact**: not urgent — `strict: true` is already set and nothing here is broken — but two major versions behind means the agent's generated code may not reflect newer TS idioms/diagnostics, and the gap will only widen.
**Severity**: low
**Effort**: moderate (15–30 min, plus fixing any new strict-mode diagnostics TS 6/7 introduces)
**Fix**:

```bash
cd frontend
npm install -D typescript@latest
npx tsc --noEmit   # check for newly-surfaced type errors before committing
```

## Summary

Health status: needs-attention

This is an unusually mature brownfield project for a health check — both sides have working test runners, full lockfiles, complete local configuration, and (on the backend) an exceptionally thorough CI pipeline (style, static analysis, tests+coverage, dependency audit, secret scanning). The gaps are narrow and concrete: the frontend carries four HIGH-severity dependency advisories (all with fixes available, none forced/breaking) and its CI pipeline doesn't yet catch build breakage or dependency vulnerabilities the way the backend's does. Fixing the `next` update and adding two CI steps closes essentially the whole gap.

Next step: run the two quick fixes above (update `next`, add `npm audit` + `npm run build` to frontend CI), then this project is in `healthy` territory with no further prep needed before agent-assisted work.
