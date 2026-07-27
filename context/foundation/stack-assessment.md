---
project: planner
assessed_at: 2026-07-27T20:29:29Z
agent_readiness: ready
context_type: brownfield
stack_components:
  language: "PHP 8.4 (backend) / TypeScript (frontend)"
  framework: "Symfony 7.4 (backend) / Next.js 16 App Router (frontend)"
  build_tool: "Composer (backend) / npm + Next.js CLI (frontend)"
  test_runner: "PHPUnit (backend) / Vitest (frontend)"
  package_manager: "Composer (backend) / npm (frontend)"
  ci_provider: "GitHub Actions"
  deployment_target: "Docker Compose (prod) via self-hosted GitHub Actions runner, images on GHCR"
gates_passed: 8
gates_failed: 0
---

## Stack Components

This is a two-component monorepo (no root-level package manifest — `backend/` and `frontend/` are each independently rooted projects, which is why the assessment scores them as separate components rather than one).

**Backend** — PHP 8.4 with Symfony 7.4 (Doctrine ORM 3.6, PostgreSQL 16, Redis 7, FrankenPHP as the app server). Static analysis via PHPStan (level 6, `backend/phpstan.dist.neon`), style enforced via PHP-CS-Fixer, tests via PHPUnit 12. Routing is attribute-based (`#[Route(...)]`), not YAML — a Symfony convention that keeps navigation predictable.

**Frontend** — TypeScript with Next.js 16 (React 19), App Router. `tsconfig.json` has `"strict": true`. TanStack Query/Table for data and tables, Zod for schema validation, Tailwind CSS 4 + Radix UI for styling/primitives. Tests via Vitest + Testing Library, linting via ESLint 9, formatting via Prettier.

Both sides already carry instruction files: root `CLAUDE.md` (stack overview, API contract, business rules), `backend/CLAUDE.md` + `backend/AGENTS.md` (Symfony conventions, testing, auth), `frontend/CLAUDE.md` (API client patterns, React Query conventions, hook rules). This is itself a strong agent-readiness signal — most of the "convention documentation" compensation work this skill would otherwise recommend is already done.

## Quality Gate Assessment

| Component              | Typed | Convention | Training Data | Documented | Verdict |
|------------------------|-------|------------|----------------|------------|---------|
| PHP 8.4 (language)     | ✓     | —          | —              | —          | pass    |
| Symfony 7.4 (framework)| —     | ✓          | ✓              | ✓          | pass    |
| Composer (build tool)  | —     | ✓          | ✓              | ✓          | pass    |
| PHPUnit (test runner)  | —     | —          | ✓              | ✓          | pass    |
| TypeScript (language)  | ✓     | —          | —              | —          | pass    |
| Next.js 16 (framework) | —     | ✓          | ✓              | ✓          | pass    |
| npm (build tool)       | —     | ✓          | ✓              | ✓          | pass    |
| Vitest (test runner)   | —     | —          | ✓              | ✓          | pass    |

Legend: ✓ = pass, ✗ = fail, ~ = partial, — = not applicable

### Gate Details

**Typed**
- PHP 8.4: pass — `backend/phpstan.dist.neon` configures PHPStan at level 6 across `src` and `tests`; combined with PHP 8.4's native scalar/union/enum type declarations (confirmed in `backend/CLAUDE.md`'s conventions: DTOs, enums via `::from()`, Doctrine attribute-typed properties), input/output shapes are statically checkable without running the program.
- TypeScript: pass — `frontend/tsconfig.json` sets `"strict": true`. Zod (`zod: ^4.3.6` in `package.json`) additionally validates shapes at runtime/API boundaries per `frontend/CLAUDE.md`'s form conventions.

**Convention-based**
- Symfony: pass — attribute-based routing, `AbstractController` inheritance, Doctrine entity/DTO/Repository layering documented in `backend/CLAUDE.md`'s "Important paths and files" and root `CLAUDE.md`'s "Backend src" tree. A stranger can predict `src/Controller/`, `src/Service/`, `src/Entity/` without reading every file.
- Next.js App Router: pass — file-based routing (`app/(auth)/`, `app/(dashboard)/` route groups per root `CLAUDE.md`), hooks isolated to `hooks/`, API client centralized in `lib/api.ts`. `frontend/CLAUDE.md` explicitly documents "all `useMutation` live in `hooks/` — never inline in components."
- Composer / npm: pass — both are the de facto standard package managers for their ecosystems, with predictable `composer.json` / `package.json` script conventions already in use (`composer run-all`, `npm run lint`, etc., per `backend/README.md`).

**Popular in training data** (assessed per language family, not globally)
- Symfony: pass — one of the two dominant full-stack PHP frameworks (alongside Laravel), extensive real-world corpus.
- Next.js: pass — the dominant React meta-framework; App Router patterns are well-represented in current training data.
- PHPUnit / Vitest: pass — both are the standard test runners in their respective ecosystems.

**Well-documented**
- Symfony: pass — versioned official docs (symfony.com/doc) per major/minor version, plus this project runs Nelmio API Doc Bundle for live OpenAPI/Swagger at `/api/doc`.
- Next.js: pass — versioned docs at nextjs.org/docs, kept current with App Router API changes.
- PHPUnit / Vitest: pass — both maintain current versioned reference docs.

## Gaps & Compensation

None. All eight scored components pass all applicable gates — no compensation strategies are needed.

## Summary

**Overall verdict: ready.** Both halves of this monorepo — Symfony/PHP on the backend, Next.js/TypeScript on the frontend — pass every quality gate cleanly: strong typing (PHPStan level 6, TypeScript strict mode), convention-heavy frameworks with documented folder/naming rules, mainstream standing within their respective language ecosystems, and current versioned documentation.

Key strength: this project's `CLAUDE.md` files (root, `backend/`, `frontend/`) already externalize the conventions an agent needs — business rules, API endpoint patterns, hook/query-key conventions, auth mechanisms — which is exactly the kind of instruction-file investment this skill would otherwise be recommending as compensation for a weaker stack. There's no compensation backlog to work through.

No key gaps identified. Recommended next step: `/10x-health-check` to look at dependency health, security posture, and CI/CD completeness independent of the stack-choice lens.
