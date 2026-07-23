---
project: frontend
assessed_at: 2026-07-12T18:25:00+02:00
agent_readiness: ready-with-compensation
context_type: brownfield
stack_components:
  language: TypeScript 5
  framework: Next.js 16.1.6 (App Router)
  build_tool: Next.js built-in (Turbopack/Webpack)
  test_runner: null
  package_manager: npm
  ci_provider: GitLab CI
  deployment_target: Docker
gates_passed: 7
gates_failed: 1
---

## Stack Components

**Language**: TypeScript 5, strict mode enabled (`tsconfig.json`: `"strict": true`). Path alias `@/*` configured. React 19.2.3 with JSX transform.

**Framework**: Next.js 16.1.6 using the App Router (`app/(auth)/`, `app/(dashboard)/` route groups per `CLAUDE.md`). Styling via Tailwind CSS 4 + Radix UI primitives. Data layer via TanStack Query 5. Forms via react-hook-form 7 + Zod 4.

**Build tool**: Next.js's built-in build pipeline (`next dev`, `next build`, `next start` in `package.json` scripts). No separate bundler config beyond `next.config.ts`.

**Test runner**: none detected. No `jest.config.*`, `vitest.config.*`, or `playwright.config.*`; no `*.test.*` / `*.spec.*` files; no test script in `package.json`.

**Package manager**: npm (`package-lock.json` present).

**CI/CD**: GitLab CI (`.gitlab-ci.yml`).

**Deployment**: Docker (`Dockerfile`, `docker-compose.yaml`, `docker-compose.prod.yaml`).

**Instruction files**: `CLAUDE.md` (root + `frontend/CLAUDE.md`) — documents API client conventions, React Query patterns, hook conventions, and business rules. No mention of testing conventions.

## Quality Gate Assessment

| Component                      | Typed | Convention | Training Data | Documented | Verdict |
| ------------------------------ | ----- | ---------- | ------------- | ---------- | ------- |
| Language (TypeScript)          | ✓     | —          | —             | —          | pass    |
| Framework (Next.js 16.1.6)     | —     | ✓          | ✓             | ✓          | pass    |
| Build tool (Next.js/Turbopack) | —     | ✓          | ✓             | ✓          | pass    |
| Test runner (none configured)  | —     | ✗          | —             | —          | fail    |

Legend: ✓ = pass, ✗ = fail, ~ = partial, — = not applicable

### Gate Details

**Language — Typed: pass.** `tsconfig.json` sets `"strict": true`. All source files are `.ts`/`.tsx`; `allowJs: true` is set but no `.js` source files were found in `components/`, `hooks/`, `lib/`, or `app/`. Zod schemas (`zod": "^4.3.6"` in dependencies) are used at form boundaries per `CLAUDE.md` (`useForm` + `zodResolver`).

**Framework — Convention: pass.** Next.js App Router in use (route groups `app/(auth)/`, `app/(dashboard)/` per project `CLAUDE.md`). File-based routing dictates page/layout location.

**Framework — Training data: pass.** Next.js + React are top-tier in the JS/TS training corpus; App Router patterns are well-represented in recent training data.

**Framework — Documented: pass.** Next.js maintains current, versioned docs at nextjs.org/docs matching the installed major version (16.x).

**Build tool — Convention: pass.** Build tooling is Next.js's own pipeline; no separate bundler configuration to diverge from convention.

**Build tool — Training data: pass.** Same corpus coverage as the framework itself.

**Build tool — Documented: pass.** Covered by Next.js's own documentation.

**Test runner — Convention: fail.** No test runner is configured, so there is no established convention (file naming, colocation vs. `__tests__/`, mock patterns) for an agent to follow. Evidence: no `jest.config.*`, `vitest.config.*`, `playwright.config.*` in project root; no `test` script in `package.json`; no `*.test.*`/`*.spec.*` files found under the project (excluding `node_modules`).

## Gaps & Compensation

**Gap: no test runner configured.**

Why it matters for agent workflows: without a test runner, an agent (or human) has no fast feedback loop to verify behavior changes beyond manual browser testing and TypeScript's compile-time checks. Given the project's heavy use of React Query mutations/hooks (`hooks/useUpdateUser`, `hooks/useDeleteGroup`, etc. per `CLAUDE.md`), hook-level bugs currently surface only through manual QA.

Compensation strategy: adopt Vitest + React Testing Library, matching the existing Next.js/React 19/TypeScript stack (Vitest has first-class Next.js support and is well-represented in current training data for this stack; Jest is also viable but Vitest's Vite-native speed and ESM support fit the Turbopack-era Next.js setup better).

### Recommended Instruction File Additions

Add to `frontend/CLAUDE.md`:

```markdown
## Testing

- Test runner: Vitest + React Testing Library (not yet installed — add via `npm install -D vitest @vitejs/plugin-react @testing-library/react @testing-library/jest-dom jsdom`)
- Test files: colocate as `*.test.tsx` next to the component/hook under test (e.g. `hooks/useUpdateUser.test.ts`)
- Hooks using `useMutation`/`useQuery`: wrap in `QueryClientProvider` with a fresh `QueryClient` per test; mock `lib/api.ts` at the module boundary, not individual fetch calls
- Run tests: `npm run test` (add script: `"test": "vitest run"`, `"test:watch": "vitest"`)
```

## Summary

Verdict: **ready-with-compensation**. The core stack (TypeScript strict mode, Next.js App Router, Next.js build tooling) passes all applicable quality gates — it's a mainstream, well-documented, convention-heavy, strongly-typed stack that agents can navigate with minimal steering. The one gap is the complete absence of a test runner, which is a process gap rather than a framework-choice gap and is straightforward to compensate for by adopting Vitest per the recommendation above.

Key strengths: strict TypeScript throughout, App Router conventions already documented in `CLAUDE.md`, consistent hook-based mutation pattern already established across the codebase.

Key gap: no automated test coverage or test runner.

Recommended next step: `/10x-health-check` to turn this gap (and any others) into a prioritized, actionable checklist.
