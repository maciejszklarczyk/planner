# Health-Check Fixes Implementation Plan

## Overview

Close the findings from `context/foundation/health-check.md` (health_status: critical-issues): bump Next.js to fix a HIGH-severity security advisory, stand up a Vitest test runner (the largest agent-readiness gap per `context/foundation/stack-assessment.md`), clean up remaining moderate/low audit findings, add a Prettier formatting baseline, add quality gates to `.gitlab-ci.yml`, add `.editorconfig`, and attempt the TypeScript major-version bump under an explicit revert path.

## Current State Analysis

- `package.json:5-8` — scripts are `dev`, `build`, `start`, `lint` only. No `test`, `typecheck`, or `format` scripts.
- `next` is pinned `16.1.6` (`package.json`), vulnerable to the advisory bundle fixed in `16.2.10`.
- No test runner installed; no `*.test.*`/`*.spec.*` files anywhere in the project.
- No `.prettierrc*`/`biome.json`; code style is inconsistent — `lib/`, `hooks/` use single-quote + semicolons (e.g. `lib/api.ts`, `hooks/useDeleteGroup.ts`), `components/`, `app/` lean double-quote + semicolons; `lib/utils.ts` has no semicolons.
- `.gitlab-ci.yml:7-8` defines only `docker-build` and `deploy` stages — no lint/typecheck/test stage exists, no npm/node image is used anywhere in CI.
- `Dockerfile:2` pins `node:20-alpine` — confirms `@types/node@^20` in `package.json` is correctly pinned to the deployed runtime; bumping to `26.x` would be a types/runtime mismatch. **Out of scope for this plan.**
- `tsconfig.json` — `moduleResolution: "bundler"`, `module: "esnext"`, `jsx: "react-jsx"`, single `next` TS plugin, no decorators, simple `@/*` path alias. Nothing exotic that would obviously break under a new compiler.
- `typescript` is pinned `"^5"` in `package.json` (installs 5.9.x per health-check.md), not an exact pin.
- `brace-expansion`, `js-yaml`, `@babel/core` audit findings are all transitive (via `@typescript-eslint/typescript-estree` and other dev tooling) — none are direct dependencies, so a non-`--force` `npm audit fix` should resolve them without unexpected major bumps.

## Desired End State

- `npm audit` reports 0 HIGH findings (moderate/low dev-chain findings resolved where `npm audit fix` allows).
- `npm run test` runs Vitest against at least one passing test (`hooks/useDeleteGroup.test.ts`).
- `CLAUDE.md` documents the testing convention (colocated `*.test.tsx`, `QueryClientProvider` wrapping, `lib/api.ts` module-boundary mocking).
- `npm run format` (or equivalent) applies a consistent Prettier style repo-wide; `.editorconfig` exists.
- `.gitlab-ci.yml` runs a `quality` stage (`npm ci && npm run lint && npx tsc --noEmit && npm run test`) before `docker-build`, blocking on failure.
- TypeScript is either on `latest` (7.x) with `npx tsc --noEmit` passing, or explicitly reverted to `^5` with the attempt and reason logged in this plan's Progress notes.

### Key Discoveries:

- `hooks/useDeleteGroup.ts:1-22` is the simplest existing hook (no options/callback params) — best smoke-test target for Vitest.
- `tsconfig.json:19-21` `@/*` alias must be mirrored in Vitest's `resolve.alias` config or imports in test files will fail to resolve.
- `lib/queryClient.ts` shows the mutation `onError` global handler pattern (401 → redirect) — the test wrapper needs its own throwaway `QueryClient`, not the app singleton, per `CLAUDE.md`'s own future convention.

## What We're NOT Doing

- Upgrading `lucide-react` past the 0.x→1.x boundary (deferred — separate change, icon API changes need dedicated review).
- Upgrading `@types/node` to 26.x (Dockerfile pins Node 20; would be a types/runtime mismatch).
- Writing tests for the full `hooks/` directory — only one smoke test, per user decision (infra-first, coverage grows with future feature work).
- Restructuring `.gitlab-ci.yml` beyond adding the `quality` stage (no caching strategy overhaul, no parallel job splitting).

## Implementation Approach

Five phases, ordered by risk and dependency: security first (independent, lowest risk), then test infra (needed before CI can gate on it), then formatting (isolated to avoid mixing style-only diffs with logic changes), then CI gating (depends on `test`/lint scripts existing), then the TypeScript major bump last (highest uncertainty, explicit revert path, doesn't block anything else).

## Phase 1: Security & Dependency Hygiene

### Overview

Resolve the HIGH-severity Next.js advisory bundle and remaining moderate/low transitive findings — the primary driver of the `critical-issues` verdict.

### Changes Required:

#### 1. Next.js version bump

**File**: `package.json`, `package-lock.json`

**Intent**: Update `next` from `16.1.6` to `16.2.10` to resolve the middleware/proxy-bypass, i18n-bypass, and cache-poisoning/CSRF/DoS advisory bundle (CVSS 7.5) plus the bundled `postcss` XSS finding. `eslint-config-next` is exact-pinned to the `next` version by convention (`package.json:43`) — bump it in lockstep or `npm run lint` runs against a stale config generation.

**Contract**: `npm install next@16.2.10 eslint-config-next@16.2.10` — non-major bump, no API changes expected.

#### 2. Remaining audit findings

**File**: `package-lock.json`

**Intent**: Resolve transitive `brace-expansion` (DoS), `js-yaml` (DoS), `@babel/core` (file-read) findings — all dev-dependency-chain, all semver-range-compatible per research.

**Contract**: `npm audit fix` (no `--force` — research confirmed no direct dep needs a forced major bump).

#### 3. Ad-hoc lint fixes (addendum, added during implementation)

**File**: `components/users/UsersTable.tsx`, `components/users/GroupsTable.tsx`, `components/layout/Breadcrumb.tsx`, `hooks/use-mobile.ts`, `hooks/useAuth.ts`, `components/forms/SetPasswordForm.tsx`, `eslint.config.mjs`

**Intent**: Gate 1.3 (`npm run lint`) failed on baseline `main` (before any Phase 1 change) with 8 pre-existing errors surfaced by `eslint-config-next@16.2.10`'s stricter React Compiler rules. This was outside the originally planned scope (package.json/package-lock.json only); the user explicitly approved fixing them in-session rather than leaving the phase blocked.

**Contract**: fixed `no-explicit-any` (UsersTable, GroupsTable: `ColumnDef<T, any>` → `ColumnDef<T, unknown>`), `prefer-const` (Breadcrumb: `let` → `const` ×3), `react-hooks/set-state-in-effect` (use-mobile: lazy `useState` initializer; SetPasswordForm: refactored to an async function inside the effect), unused var (useAuth). Excluded `components/ui/**` from ESLint via `eslint.config.mjs` `globalIgnores` (shadcn primitives, off-limits per `CLAUDE.md`) to unblock an unrelated `react-hooks/purity` finding in `sidebar.tsx`.

### Success Criteria:

#### Automated Verification:

- `npm audit` shows 0 HIGH findings
- `npm run build` completes without errors
- `npm run lint` passes

#### Manual Verification:

- App boots (`npm run dev`) and core pages (`/login`, `/events`) load without console errors

---

## Phase 2: Test Infrastructure (Vitest)

### Overview

Install Vitest + React Testing Library, wire scripts and config, add one smoke test, document the convention in `CLAUDE.md` — closes the single largest agent-readiness gap identified in `stack-assessment.md`.

### Changes Required:

#### 1. Install and configure Vitest

**File**: `package.json`, `vitest.config.ts` (new), `vitest.setup.ts` (new)

**Intent**: Add Vitest + RTL + jsdom as the test runner, matching the compensation strategy already drafted in `context/foundation/stack-assessment.md`.

**Contract**: `npm install -D vitest @vitejs/plugin-react @testing-library/react @testing-library/jest-dom jsdom`. `vitest.config.ts` needs `resolve.alias['@']` pointing at project root (mirroring `tsconfig.json`'s `@/*` path) and `environment: 'jsdom'`. `vitest.setup.ts` imports `@testing-library/jest-dom` and is referenced via `test.setupFiles` in the config.

#### 2. Add scripts

**File**: `package.json`

**Intent**: Expose test running to both developers and CI.

**Contract**: add `"test": "vitest run"` and `"test:watch": "vitest"` to `scripts`.

#### 3. Smoke test

**File**: `hooks/useDeleteGroup.test.ts` (new)

**Intent**: Prove the Vitest + RTL + React Query setup works end-to-end against a real hook, per the existing mutation-hook pattern documented in `CLAUDE.md`.

**Contract**: Wrap `renderHook` in a fresh `QueryClientProvider` (new `QueryClient` per test, not the app singleton in `lib/queryClient.ts`); mock `lib/api.ts`'s `api.delete` at the module boundary (`vi.mock('@/lib/api')`); assert `mutate(groupId)` resolves and calls the mocked `api.delete` with the right endpoint.

#### 4. Document convention

**File**: `CLAUDE.md`

**Intent**: Make the testing convention discoverable for future hook development, closing the gap `stack-assessment.md` flagged as unimplemented.

**Contract**: Add the `## Testing` section already drafted in `context/foundation/stack-assessment.md` (test runner, colocation convention, QueryClientProvider wrapping, module-boundary mocking, run commands) verbatim to `CLAUDE.md`.

### Success Criteria:

#### Automated Verification:

- `npm run test` passes with the `useDeleteGroup` smoke test green
- `npm run build` still completes (no config conflicts between Vitest and Next.js build)
- `npm run lint` passes (test files conform to eslint config)

#### Manual Verification:

- `npm run test:watch` starts and re-runs on file save

---

## Phase 3: Formatting Baseline (Prettier + .editorconfig)

### Overview

Introduce Prettier with default style (double quotes, semicolons) and `.editorconfig`, applied as a single isolated repo-wide format commit to keep the diff reviewable and separate from logic changes.

### Changes Required:

#### 1. Install and configure Prettier

**File**: `package.json`, `.prettierrc` (new), `.prettierignore` (new)

**Intent**: Establish a single enforced formatting convention; Prettier defaults (double quotes, semicolons, 2-space indent) were chosen over guessing a single-quote convention since `components/`/`app/` (the JSX-heavy majority of the codebase) already lean double-quote.

**Contract**: `npm install -D prettier`. `.prettierrc` can be empty/minimal (`{}`) since defaults are the target style — only add overrides if `npx prettier --check .` reveals a convention worth preserving beyond quote style. `.prettierignore` excludes `.next/`, `node_modules/`, `components/ui/` (shadcn primitives — do not reformat per `CLAUDE.md`'s "Nie modyfikuj `components/ui/`").

#### 2. Add format script

**File**: `package.json`

**Intent**: Make formatting runnable and CI-checkable.

**Contract**: add `"format": "prettier --write ."` and `"format:check": "prettier --check ."` to `scripts`.

#### 3. Repo-wide format commit

**File**: all non-ignored `.ts`/`.tsx` files

**Intent**: Apply the new formatting baseline in one commit so the diff is understood as "formatting only" and doesn't obscure future logic-change diffs. Expect a large diff in `lib/` and `hooks/` (currently single-quote) and a small diff in `components/`/`app/` (already close to double-quote+semicolon).

**Contract**: run `npm run format` once, commit as a standalone commit before any further phases touch those files.

#### 4. .editorconfig

**File**: `.editorconfig` (new)

**Intent**: Editor-level formatting consistency independent of ESLint/Prettier tooling.

**Contract**: `indent_style = space`, `indent_size = 2`, `charset = utf-8`, `end_of_line = lf`, `insert_final_newline = true`, `trim_trailing_whitespace = true`.

### Success Criteria:

#### Automated Verification:

- `npm run format:check` passes (zero diff) after the format commit
- `npm run lint` passes (Prettier output doesn't conflict with ESLint rules)
- `npm run build` completes

#### Manual Verification:

- Spot-check 2-3 reformatted files (one from `lib/`, one from `components/`) — confirm no logic changed, only style

---

## Phase 4: CI Quality Gate

### Overview

Add a `quality` stage to `.gitlab-ci.yml` that runs lint, typecheck, and test before `docker-build`, hard-blocking the pipeline on failure.

### Changes Required:

#### 1. New CI stage

**File**: `.gitlab-ci.yml`

**Intent**: Prevent broken code (lint violations, type errors, failing tests) from reaching the `docker-build`/`deploy` stages — currently the pipeline only builds and deploys with no quality checks at all.

**Contract**: add `quality` as the first entry in `stages:` (before `docker-build`). New job `quality-checks` using `image: node:20-alpine` (matches `Dockerfile:2`'s runtime), `tags: [docker]` (matching `docker-build`/`deploy-production` — required since the runner pool is tag-locked), running `npm ci && npm run lint && npx tsc --noEmit && npm run test && npm run format:check`, with `only: [main]` matching the existing jobs' trigger pattern. No `allow_failure` — failures block `docker-build` per the hard-gate decision.

### Success Criteria:

#### Automated Verification:

- A deliberately broken commit (e.g. lint violation) run through `quality-checks` fails the job (verify via `gitlab-ci-local` or a throwaway branch push if available; otherwise verify the job script runs cleanly locally with the same commands)
- `.gitlab-ci.yml` passes GitLab's CI lint (`gitlab-ci-local` or the project's CI Lint UI under Settings > CI/CD)

#### Manual Verification:

- A real push to `main` triggers the `quality` stage before `docker-build` in the GitLab pipeline UI

---

## Phase 5: TypeScript Major Bump (Risk-Flagged)

### Overview

Attempt the TypeScript `^5` → `latest` (7.x) bump. This is very likely the native Go-based compiler rewrite — real breakage risk. Explicit revert path if it fails.

### Changes Required:

#### 1. Attempt the bump

**File**: `package.json`, `package-lock.json`

**Intent**: Close the health-check.md finding that TypeScript is 2 majors behind, since current training-data docs assume a version this project doesn't match.

**Contract**: `npm install -D typescript@latest`, then run `npx tsc --noEmit`. If clean (zero new errors), keep the bump and commit. If it produces errors, attempt straightforward fixes only if there are fewer than ~10 and they're mechanical (e.g. type-only import syntax changes); otherwise revert (`npm install -D typescript@^5`) and record the attempted version + error summary in this plan's Progress section as a follow-up item for a dedicated future change.

### Success Criteria:

#### Automated Verification:

- `npx tsc --noEmit` passes (either on 7.x if kept, or on `^5` if reverted)
- `npm run build` completes
- `npm run lint` passes
- `npm run test` passes

#### Manual Verification:

- If kept on 7.x: spot-check that the Next.js `next` TS Server plugin still provides working IDE type-checking (open a `.tsx` file, confirm no phantom errors in editor)

---

## Testing Strategy

### Unit Tests:

- `hooks/useDeleteGroup.test.ts` — mutation success path (calls `api.delete`, invalidates `['admin','groups']` query key, shows success toast) and error path (shows error toast)

### Integration Tests:

- None added in this plan — infra-first scope per user decision; future feature work adds coverage incrementally.

### Manual Testing Steps:

1. After Phase 1: run app locally, click through login → events → settings, confirm no runtime regressions from the Next.js bump.
2. After Phase 2: run `npm run test:watch`, edit `useDeleteGroup.test.ts` trivially, confirm re-run triggers.
3. After Phase 3: diff a `lib/` file and a `components/` file before/after format commit, confirm no logic changes.
4. After Phase 4: push a throwaway branch with a lint violation, confirm the `quality` stage fails in GitLab's pipeline view.
5. After Phase 5: open the project in the editor, confirm TS IntelliSense still resolves `@/*` imports correctly.

## Performance Considerations

The new CI `quality` stage adds npm install + lint + typecheck + test time to every pipeline run (~1-2 min uncached). No caching precedent exists in `.gitlab-ci.yml` — first run establishes baseline; a caching follow-up (node_modules cache key) is out of scope here but worth a future change if pipeline time becomes a concern.

## Migration Notes

Not applicable — no data migrations. The Prettier format commit (Phase 3) should be recorded in `git blame` ignore config (`.git-blame-ignore-revs`) if the team wants clean blame history going forward; not included in this plan's scope since it wasn't raised as a requirement.

## References

- Source findings: `context/foundation/health-check.md`
- Compensation strategy origin: `context/foundation/stack-assessment.md`
- Mutation hook pattern: `hooks/useDeleteGroup.ts:1-22`
- Global QueryClient config: `lib/queryClient.ts`
- Path alias source of truth: `tsconfig.json:19-21`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Security & Dependency Hygiene

#### Automated

- [x] 1.1 `npm audit` shows 0 HIGH findings — 5cc4166
- [x] 1.2 `npm run build` completes without errors — 5cc4166
- [x] 1.3 `npm run lint` passes — 5cc4166

#### Manual

- [ ] 1.4 App boots and core pages load without console errors

### Phase 2: Test Infrastructure (Vitest)

#### Automated

- [x] 2.1 `npm run test` passes with the `useDeleteGroup` smoke test green — 22c01eb
- [x] 2.2 `npm run build` still completes — 22c01eb
- [x] 2.3 `npm run lint` passes — 22c01eb

#### Manual

- [ ] 2.4 `npm run test:watch` starts and re-runs on file save

### Phase 3: Formatting Baseline (Prettier + .editorconfig)

#### Automated

- [x] 3.1 `npm run format:check` passes (zero diff) after the format commit — 30c063c
- [x] 3.2 `npm run lint` passes — 30c063c
- [x] 3.3 `npm run build` completes — 30c063c

#### Manual

- [ ] 3.4 Spot-check 2-3 reformatted files — confirm no logic changed

### Phase 4: CI Quality Gate

#### Automated

- [x] 4.1 Deliberately broken commit fails the `quality-checks` job — e7ee051
- [x] 4.2 `.gitlab-ci.yml` passes GitLab's CI lint — e7ee051

#### Manual

- [ ] 4.3 Real push to `main` triggers `quality` stage before `docker-build` in pipeline UI

### Phase 5: TypeScript Major Bump (Risk-Flagged)

#### Automated

- [x] 5.1 `npx tsc --noEmit` passes (kept on 7.x or reverted to `^5`) — 8334ca4
- [x] 5.2 `npm run build` completes — 8334ca4
- [x] 5.3 `npm run lint` passes — 8334ca4
- [x] 5.4 `npm run test` passes — 8334ca4

**Attempt log:** installed `typescript@latest` (resolved 7.0.2). `npx tsc --noEmit` was clean (0 errors) — the Go-based compiler itself accepted the codebase. However `npm run build` failed (`The "id" argument must be of type string. Received undefined` — Next.js build worker crash) and `npm run lint` failed hard (`TypeError: Cannot read properties of undefined (reading 'Cjs')` in `eslint-config-next`'s bundled `typescript-eslint`/`@typescript-eslint/typescript-estree`, incompatible with TS 7's new compiler internals). These are toolchain-incompatibility crashes, not mechanical type errors, and exceed the "fewer than ~10 mechanical" bar for an in-place fix. Reverted via `npm install -D typescript@^5` (resolved 5.9.3). Follow-up: revisit once `eslint-config-next`/`typescript-eslint` ship TS 7 support upstream — track as a dedicated future change.

#### Manual

- [ ] 5.5 If kept on 7.x: editor TypeScript IntelliSense still works correctly
