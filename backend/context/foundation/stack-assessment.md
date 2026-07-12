---
project: planner/backend
assessed_at: 2026-07-12T12:43:31Z
agent_readiness: ready-with-compensation
context_type: brownfield
stack_components:
  language: PHP 8.4
  framework: Symfony 7.4
  build_tool: Composer
  test_runner: PHPUnit 12.5
  package_manager: Composer
  ci_provider: GitLab CI
  deployment_target: Docker Compose + Traefik (prod), FrankenPHP dev server
gates_passed: 3
gates_failed: 1
---

## Stack Components

**Language**: PHP 8.4, run under FrankenPHP. All 50 files under `src/` declare `strict_types=1`, and DTOs/entities use typed properties throughout (Doctrine attribute mapping, `#[Assert\...]` on request DTOs). Static analysis (`phpstan/phpstan ^2.1`) is present as a dev dependency and a `composer phpstan` script exists, but no `phpstan.neon` / `phpstan.dist.neon` configuration file exists anywhere in the repo, and the GitLab CI pipeline does not run a phpstan job (only `php-cs-fixer` and `phpunit` run in the `test` stage).

**Framework**: Symfony 7.4, used with strong conventions — attribute-based routing (`#[Route(...)]`), `AbstractController` inheritance, Doctrine ORM 3.6 entity mapping via attributes, Gedmo `SoftDeleteable` for soft deletes, domain exceptions in `src/Exception/`, Request/Response DTO separation (`src/Dto/` vs `src/Dto/Response/`). The project's own `CLAUDE.md` (backend + root) documents these conventions explicitly.

**Build tool**: Composer, standard `composer.json` with PSR-4 autoloading (`App\` → `src/`), Symfony Flex for recipe management.

**Test runner**: PHPUnit 12.5, configured via `phpunit.dist.xml` (project note: `phpunit.xml.dist` is a stale duplicate — `phpunit.dist.xml` is the one actually used). Functional tests require `.env.test` for `framework.test: true`. CI runs `vendor/bin/phpunit --coverage-cobertura=coverage.xml --coverage-text` with pcov.

**Package manager**: Composer, `composer.lock` present (implied by `--prefer-dist` in CI).

**CI/CD**: GitLab CI (`.gitlab-ci.yml`), stages: test → secret-detection → build → lint → docker-build → deploy. The `test` stage runs `php-cs-fixer` (dry-run diff) and `phpunit` (with coverage). No `phpstan` stage exists despite phpstan being installed and scripted in `composer.json` (`composer run-all` locally chains `cs-fix-analyse` → `phpstan` → `run-tests`, but CI only mirrors two of the three).

**Deployment**: Docker Compose for both dev (`docker-compose.yaml`) and prod (`docker-compose.prod.yaml`), fronted by Traefik in prod per project docs.

**Instruction files**: `CLAUDE.md` present at three levels — project root (stack overview, API URL rules, role model, cross-cutting business rules), `backend/CLAUDE.md` (PHP/Symfony conventions, key file map, test-running instructions, dev-auth header), `frontend/CLAUDE.md` (not assessed here — out of scope for this backend assessment).

## Quality Gate Assessment

| Component  | Typed | Convention | Training Data | Documented | Verdict    |
|------------|-------|------------|---------------|------------|------------|
| Language   | ✓     | —          | —             | —          | pass       |
| Framework  | —     | ✓          | ✓             | ✓          | pass       |
| Build tool | —     | ✓          | ✓             | ✓          | pass       |
| Test runner| —     | —          | ✓             | ✓          | pass       |

Legend: ✓ = pass, ✗ = fail, ~ = partial, — = not applicable

**Overall gate tally: 3 of 4 pass outright.** The 4th column below (static-analysis enforcement, adjacent to but not identical to the "typed" gate) is where the actual gap sits — see Gate Details.

### Gate Details

**Typed — pass.** PHP 8.4 supports full type declarations, and this codebase uses them consistently: `strict_types=1` in all 50 `src/` files (evidence: `grep -rl "declare(strict_types" src | wc -l` → 50, matching `find src -name "*.php" | wc -l` → 50), typed Doctrine entity properties, typed DTO properties validated via `#[Assert\...]`. The language-level typing gate passes cleanly.

*Adjacent gap, noted separately since it doesn't flip the gate but does matter for agents*: `phpstan/phpstan` is a listed dev dependency and has a `composer phpstan` script (`phpstan analyse src tests --memory-limit 1G`), but no `phpstan.neon` or `phpstan.dist.neon` file exists in the repo root (evidence: `find . -maxdepth 2 -iname "phpstan*"` returns only `./vendor/phpstan`, the vendor binary directory — no config file). Without a config file, phpstan falls back to defaults (level 0, minimal ruleset) rather than an intentionally chosen level. The CI pipeline also never invokes phpstan (evidence: `.gitlab-ci.yml` stages list `test`, `secret-detection`, `build`, `lint`, `docker-build`, `deploy` — the `test` stage jobs are `php-cs-fixer` and `phpunit` only, `grep -n phpstan .gitlab-ci.yml` matches nothing). This means static analysis exists in the toolchain but isn't gating anything — an agent (or human) can introduce a type error that PHP's runtime typing won't catch until it manifests as a bug, and CI won't flag it.

**Convention-based — pass.** Symfony 7.4 is a strongly opinionated framework: attribute-based routing, `AbstractController` base class, Doctrine ORM attribute mapping, a service container with autowiring, a documented directory layout (`Controller/`, `Dto/`, `Entity/`, `Exception/`, `Repository/`, `Security/`, `Service/`). This project follows the conventions and additionally documents its own layering choices in `backend/CLAUDE.md` (e.g., "Request DTOs in `src/Dto/`", "Response DTOs in `src/Dto/Response/`", "Domain exceptions in `src/Exception/` — rzucane z serwisów, łapane w kontrolerach"). An agent reading the instruction file plus a few example files can predict where new code belongs.

**Popular in training data — pass.** Symfony is a mainstream framework within the PHP language family (alongside Laravel), with a large public corpus of docs, tutorials, and Stack Overflow content. PHPUnit is the dominant PHP test framework. Doctrine ORM is the standard PHP ORM. All components here are top-tier within PHP's ecosystem, not niche picks.

**Well-documented — pass.** Symfony ships versioned, current official docs (symfony.com/doc/7.4). Doctrine ORM has versioned docs matching the 3.x line in use. PHPUnit 12 docs are current. Nelmio API Doc Bundle exposes live OpenAPI/Swagger at `/api/doc`, giving the agent a runtime-verifiable contract in addition to static docs.

## Gaps & Compensation

**Gap: static analysis is installed but not enforced.** `phpstan` exists as a dependency and a composer script, but with no config file it runs at an unspecified (effectively minimal) level, and CI doesn't run it at all. This doesn't fail the language-level "typed" gate — PHP's own type system and this project's strict-types discipline already give an agent a lot to reason from — but it does mean the toolchain has a documented static-analysis step (`composer run-all` locally references it) that provides no real backstop, which could mislead an agent into assuming a safety net that isn't there in CI.

Why this matters for agent workflows: an agent asked to refactor a service or DTO gets no automated "you broke a type contract" signal until either PHPUnit happens to cover the affected code path or the bug reaches runtime. Since the project's own `backend/CLAUDE.md` currently says nothing about phpstan levels or when to run it, an agent has no way to know this step exists at all unless it inspects `composer.json` scripts directly.

**Compensation strategy**: add a `phpstan.dist.neon` config pinning an explicit level (start conservative, e.g. level 5-6 given the existing typed codebase, and ratchet up over time), and add a phpstan job to `.gitlab-ci.yml`'s `test` stage alongside the existing `php-cs-fixer` and `phpunit` jobs. This is an infrastructure fix, not just a documentation one — but until it lands, the instruction file should say so explicitly rather than implying phpstan is an enforced gate.

### Recommended Instruction File Additions

Add to `backend/CLAUDE.md` under a new `## Static analysis` heading (or extend the existing `## Testy` section):

```markdown
## Static analysis

- `phpstan/phpstan` is installed (`composer phpstan` script: `phpstan analyse src tests --memory-limit 1G`) but **no `phpstan.neon` config exists yet** and **CI does not run it** — treat phpstan output as advisory only until this gap is closed, not as a merge gate.
- Do not assume "phpstan passes" implies anything currently — it is running at PHPStan's default level with no project-specific config.
- If you add or change type-sensitive code (Doctrine entities, DTOs, service method signatures), rely on `strict_types=1` + explicit type declarations as the actual safety net, not phpstan, until a config file and CI job exist.
- If asked to close this gap: add `phpstan.dist.neon` at the repo root pinning an explicit level, then add a `phpstan` job to the `test` stage of `.gitlab-ci.yml` mirroring the existing `php-cs-fixer`/`phpunit` job structure.
```

## Summary

**Overall verdict: ready-with-compensation.** Three of the four agent-friendly quality gates pass outright — PHP 8.4 with consistent `strict_types` discipline, Symfony 7.4's strong conventions, its top-tier standing in the PHP training-data corpus, and current versioned docs (plus a live OpenAPI surface via Nelmio). This is a solid, mainstream, well-typed stack that an agent can navigate confidently using the existing `CLAUDE.md` files as a map.

The one real gap isn't a stack choice problem — it's a toolchain-enforcement gap: `phpstan` is present but unconfigured and not wired into CI, so the project's static-analysis intent (visible in the `composer run-all` script) isn't actually backed by anything. This is cheap to close (one config file + one CI job) and, until closed, cheap to document so an agent doesn't over-trust a check that isn't running.

**Recommended next step**: `/10x-health-check` — it will read this file and can focus its dependency/security/CI checks on confirming (or closing) the phpstan gap identified here, plus surface any other missing configuration this assessment didn't scope (e.g. `phpstan.xml.dist` vs `phpunit.dist.xml` stale-file duplication noted in `backend/CLAUDE.md`, secret scanning already present via GitLab's `Secret-Detection` template).
