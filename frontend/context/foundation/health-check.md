---
project: frontend
checked_at: 2026-07-12T18:30:00+02:00
health_status: critical-issues
context_type: brownfield
language_family: js
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
  high: 1
  moderate: 3
  low: 1
test_runner_detected: false
ci_provider: GitLab CI
recommended_fixes: 8
---

## Dependency Health

### Lockfile

```
Status: present (package-lock.json)
Package manager: npm
```

### Security Audit

```
Tool: npm audit --json
Summary: 0 CRITICAL, 1 HIGH, 3 MODERATE, 1 LOW
Direct vs transitive: next and js-yaml are direct; brace-expansion (via @typescript-eslint/typescript-estree) and @babel/core are transitive dev-dependency findings
```

#### HIGH findings

- **next** 16.1.6 — bundle of advisories (middleware/proxy bypass via segment-prefetch routes `GHSA-267c-6grr-h53f`, i18n proxy bypass `GHSA-36qx-fr4f-26g5`, plus related cache-poisoning/CSRF/DoS advisories in the same range) — CVSS 7.5. Fix: update to `next@16.2.10` (non-major bump, `isSemVerMajor: false`).

#### MODERATE / LOW findings

- **postcss** (bundled by next) — XSS via unescaped `</style>` in CSS stringify output (`GHSA-qx2v-qp2m-jg93`). Resolved by the same `next@16.2.10` update.
- **brace-expansion** (transitive, via `@typescript-eslint/typescript-estree`) — DoS defeating the documented `max` range protection (`GHSA-jxxr-4gwj-5jf2`). Fix: `npm audit fix`.
- **js-yaml** 4.0.0–4.1.1 — quadratic-complexity DoS via repeated merge-key aliases (`GHSA-h67p-54hq-rp68`). Fix: `npm audit fix`.
- **@babel/core** ≤7.29.0 — arbitrary file read via `sourceMappingURL` comment (`GHSA-4x5r-pxfx-6jf8`), LOW. Fix: `npm audit fix`.

### Outdated Dependencies

```
Packages with major version gaps: 3
```

- **typescript**: 5.9.3 → 7.0.2 (2 major versions behind)
- **@types/node**: 20.19.39 → 26.1.1 (6 major versions behind — likely intentionally pinned to Node 20's runtime types; verify against the deployed Node version before bumping)
- **lucide-react**: 0.563.0 → 1.24.0 (crossed the 0.x → 1.x boundary; check the changelog for breaking icon API changes)

`eslint` (9.39.4 → 10.7.0, dev-only) is one major version behind — noted but below the 2-major threshold, low urgency.

## Test Suite

```
Test runner: not detected
Tests found: not applicable
Test execution: not attempted
```

⚠ No test runner detected. The agent cannot verify its own changes beyond TypeScript compilation and manual browser testing.
Recommended: Vitest + React Testing Library (matches the Next.js 16 / React 19 / TypeScript stack — see the compensation entry already drafted in `context/foundation/stack-assessment.md`).

## CI/CD

```
Provider: GitLab CI
Configuration: .gitlab-ci.yml
```

| Stage      | Status | Notes                                                                                                                                                       |
| ---------- | ------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Lint       | ✗      | not configured (eslint.config.mjs exists locally, not run in CI)                                                                                            |
| Test       | ✗      | not configured (no test runner in the project)                                                                                                              |
| Build      | ~      | `docker-build` stage builds the Docker image (which runs `next build` inside the Dockerfile), but there's no standalone build-verification step before that |
| Type check | ✗      | not configured (`tsc --noEmit` not run in CI)                                                                                                               |
| Security   | ✗      | not configured (no `npm audit` step)                                                                                                                        |

The pipeline currently has two stages only: `docker-build` and `deploy-production` — it's a deploy pipeline, not a quality-gate pipeline.

## Configuration

### Medium severity

- **Prettier / formatter config** — no `.prettierrc*` or `biome.json` found. ESLint (`eslint.config.mjs`) covers some style rules via `eslint-config-next`, but there's no dedicated formatter, so agent-generated code formatting will drift from hand-written code over time. Fix: add Prettier (`npm install -D prettier`, add `.prettierrc`) or adopt Biome for combined lint+format.

### Low severity

- **.editorconfig** — missing. Fix: add a `.editorconfig` with the project's indent/charset conventions (2-space indent, per the existing codebase style) so editors agree without relying on ESLint alone.

`tsconfig.json` has `strict: true` — no finding. `.gitignore` and `.env.example` are both present — no finding.

## Stack Assessment Cross-Reference

```
Stack assessment: context/foundation/stack-assessment.md
Agent readiness (from stack-assess): ready-with-compensation
```

| Quality Gate Gap               | Health-Check Finding                                                                                                                          | Status     |
| ------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------- | ---------- |
| Test runner — convention: fail | No test runner detected; CI has no test stage either; `CLAUDE.md` does not yet contain the Vitest compensation entry stack-assess recommended | Reinforced |

The compensation strategy stack-assess proposed (add a `## Testing` section to `frontend/CLAUDE.md` and adopt Vitest) has not been implemented yet — this is the single highest-impact gap across both reports.

## Recommended Fixes

### Fix before agent work (Category A)

### 1. HIGH-severity Next.js vulnerabilities

**Impact**: middleware/proxy-bypass and cache-poisoning advisories are exploitable in production; an agent modifying routing/middleware code without knowing about these could inadvertently rely on the vulnerable behavior.
**Severity**: high
**Effort**: quick (< 5 min)
**Fix**:

```bash
npm install next@16.2.10
npm audit
```

### 2. No test runner configured

**Impact**: the agent has no automated way to verify its own changes; every change requires manual QA. This is the largest gap identified across both this report and the stack assessment.
**Severity**: high
**Effort**: significant (> 1 hour, including writing initial tests)
**Fix**:

```bash
npm install -D vitest @vitejs/plugin-react @testing-library/react @testing-library/jest-dom jsdom
```

Then add the `## Testing` section already drafted in `context/foundation/stack-assessment.md` to `frontend/CLAUDE.md`, and add `"test": "vitest run"` / `"test:watch": "vitest"` scripts to `package.json`.

### 3. Remaining MODERATE/LOW audit findings

**Impact**: DoS and file-read advisories in dev-dependency-chain packages (brace-expansion, js-yaml, @babel/core) — lower exploitability than the next.js findings since they're dev-time tooling, not shipped runtime code, but cheap to fix.
**Severity**: medium/low
**Effort**: quick (< 5 min)
**Fix**:

```bash
npm audit fix
```

### 4. No formatter configured

**Impact**: agent-generated code has no enforced formatting convention beyond ESLint's style rules, so formatting will drift across files over time.
**Severity**: medium
**Effort**: quick (< 5 min)
**Fix**:

```bash
npm install -D prettier
```

Add a `.prettierrc` matching the codebase's existing style (single quotes are not consistently used — check `components/` for the prevailing convention before locking one in).

### 5. CI pipeline has no quality gates

**Impact**: nothing prevents a broken build, a type error, or a lint violation from reaching `main` and being deployed — the pipeline only builds and deploys the Docker image.
**Severity**: medium
**Effort**: moderate (15–30 min)
**Fix**: add a stage to `.gitlab-ci.yml` before `docker-build` that runs `npm ci`, `npm run lint`, `npx tsc --noEmit`, and (once added) `npm run test`.

### 6. Major-version-behind dependencies

**Impact**: `typescript` (2 majors behind) and `lucide-react` (crossed 0.x→1.x) risk breaking changes accumulating the longer the upgrade is deferred; an agent reading current docs (which reflect the latest major) may generate code that doesn't match the installed version.
**Severity**: low
**Effort**: moderate (15–30 min) per package, test after each
**Fix**: upgrade one package at a time, starting with `typescript` (`npm install -D typescript@latest`, then run `npx tsc --noEmit` to catch fallout). Verify `@types/node@26` matches the Node version actually deployed (check `Dockerfile`) before bumping — a types-only mismatch is otherwise harmless but not free to change.

### 7. Missing .editorconfig

**Impact**: minor — editor-level formatting consistency for contributors not using the same ESLint setup.
**Severity**: low
**Effort**: quick (< 5 min)
**Fix**: add a `.editorconfig` with `indent_style = space`, `indent_size = 2`, `charset = utf-8`, `end_of_line = lf`.

### Addressed in upcoming lessons (Category B)

None. The typical Category B items for this stage — a CI/CD pipeline, agent instruction files, and deployment configuration — are already present in this project (`.gitlab-ci.yml`, `CLAUDE.md` at both root and `frontend/`, `Dockerfile` + `docker-compose*.yaml`). The remaining CI gap (no quality-gate stages) is tracked above as a Category A fix instead, since the scaffolding already exists and only needs stages added.

## Summary

Health status: **critical-issues**

The project's dependency and configuration hygiene is reasonably good (lockfile present, strict TypeScript, ESLint configured, CI/CD and Docker deployment already in place), but two compounding gaps drive the critical-issues verdict: a HIGH-severity, easily-fixable Next.js security advisory, and the complete absence of a test runner — the latter directly undermines an agent's ability to verify its own changes and was already flagged as the sole gate failure in `context/foundation/stack-assessment.md`.

Next step: apply fix #1 (update Next.js) and fix #2 (add Vitest, per the compensation entry in `stack-assessment.md`) first — those two resolve the critical-issues verdict on their own. Then work through the remaining Category A fixes before starting agent-assisted feature work.
