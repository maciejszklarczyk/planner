---
project: planner/backend
assessed_at: 2026-07-22T20:09:43Z
agent_readiness: ready-with-compensation
context_type: brownfield
stack_components:
  language: PHP 8.4
  framework: Symfony 7.4
  build_tool: Composer
  test_runner: PHPUnit 12
  package_manager: Composer
  ci_provider: GitLab CI
  deployment_target: Docker Compose (docker-compose.prod.yaml)
gates_passed: 4
gates_failed: 0
---

## Stack Components

**Language**: PHP 8.4 (composer.json: `"php": ">=8.4"`). Codebase uses
`declare(strict_types=1)` consistently (verified in `src/Entity/User.php`
and others) plus typed properties, parameters, and return types throughout
`src/`.

**Framework**: Symfony 7.4 (`symfony/framework-bundle: 7.4.*`), with
Doctrine ORM 3.6, Doctrine Migrations 4, Stof Doctrine Extensions
(SoftDeleteable), Nelmio API Doc Bundle (OpenAPI), Nelmio CORS, Symfony
Rate Limiter, Symfony Security Bundle. FrankenPHP runtime per project docs.

**Build tool / package manager**: Composer 2.x, `composer.json` +
`composer.lock` present, `sort-packages` and `bump-after-update` enabled.

**Test runner**: PHPUnit 12 (`phpunit/phpunit: ^12.5.17`), config at
`phpunit.dist.xml`, run via `composer run-tests` script and a dedicated
`phpunit` job in `.gitlab-ci.yml` with coverage (pcov + Cobertura report).

**Static analysis (dev dependency, present but under-wired)**: PHPStan
2.1 is a `require-dev` dependency with a `composer phpstan` script
(`analyse src tests --memory-limit 1G`), but no `phpstan.neon` /
`phpstan.dist.neon` config file exists in the repo root, and no PHPStan
job exists in `.gitlab-ci.yml`.

**CI/CD**: GitLab CI (`.gitlab-ci.yml`) — stages: build, test (php-cs-fixer
dry-run, phpunit, composer-audit), secret-detection, lint (composer
validate, yaml lint, container lint), docker-build, deploy (tag-triggered
production deploy via Docker Compose).

**Deployment**: Docker Compose, `docker/php/Dockerfile.prod`, deploy job
pulls/restarts the stack and runs `doctrine:migrations:migrate` +
`cache:clear` against production.

**Instruction files**: `CLAUDE.md` (root + `backend/CLAUDE.md`) and
`AGENTS.md` — both present and reasonably detailed (stack, conventions,
fixtures, dev-auth flow, testing instructions).

## Quality Gate Assessment

| Component   | Typed | Convention | Training Data | Documented | Verdict           |
|-------------|-------|------------|----------------|------------|--------------------|
| Language    | ✓     | —          | —              | —          | pass (with note)   |
| Framework   | —     | ✓          | ✓              | ✓          | pass               |
| Build tool  | —     | —          | ✓              | ✓          | pass               |
| Test runner | —     | —          | ✓              | ✓          | pass               |

Legend: ✓ = pass, ✗ = fail, ~ = partial, — = not applicable

### Gate Details

**Typed — pass (with note).** PHP 8.4 supports explicit types at function
boundaries and the codebase uses them consistently
(`declare(strict_types=1)` + typed properties/params/returns observed
across `src/Entity/*.php`, `src/Controller/*.php`). This is stronger than
the generic "PHP without typed properties" failure case in the criteria
doc. The note: PHPStan is a dev dependency with a runnable script but has
**no config file** (`phpstan.neon` absent) and **no CI job** — so the
type-consistency the agent relies on when reading code is enforced by
convention/review, not by an automated gate. An agent making changes has
no automatic signal if it introduces a type violation.

**Convention-based — pass.** Symfony ships strong opinions: PHP-attribute
routing (`#[Route(...)]`), `AbstractController` base class, Doctrine
attribute-mapped entities, a service container with autowiring, a fixed
`src/{Controller,Entity,Repository,Service,Dto,Security,Exception}`
layout that's already documented in `backend/CLAUDE.md`. A stranger (or
agent) can predict where new code belongs without exploring the whole
tree.

**Popular in training data — pass.** Symfony is one of the two mainstream
PHP frameworks (alongside Laravel) and appears heavily in PHP training
corpora — attribute routing, Doctrine ORM patterns, and Symfony's DI
conventions are well-represented idioms.

**Well-documented — pass.** symfony.com hosts current, versioned
reference docs (7.4 branch); Doctrine ORM, PHPUnit, and Composer all have
canonical, versioned, official documentation.

## Gaps & Compensation

### Gap: PHPStan configured as a dependency but not enforced

**What failed**: not a hard gate failure (Typed still passes on language
merits), but a real robustness gap — static type-checking exists in
`composer.json` as a callable script, yet has no config file and is never
run in CI. This means the "typed" guarantee an agent leans on is only as
strong as manual discipline, with no automated backstop.

**Why it matters for agent workflows**: an agent editing Doctrine
entities, DTOs, or service signatures currently gets no automated
feedback if it introduces a type mismatch — it will only surface at
runtime or in code review. A wired-in PHPStan job turns "typed" from a
convention into a checked property, which is exactly what makes a
codebase easy for an agent to reason about safely.

**Compensation — ready to paste into `backend/CLAUDE.md`**:

```markdown
## Static analysis

- PHPStan is a dev dependency (`composer phpstan`) but has no committed
  config and no CI job yet. Until that's wired up:
  - Run `vendor/bin/phpstan analyse src tests --memory-limit 1G` locally
    before finishing any non-trivial change (new entity fields, service
    signatures, DTO changes).
  - Prefer explicit return types and typed properties on every new class
    — don't rely on PHPStan to catch omissions, since it isn't gating CI.
  - If you add a `phpstan.neon`, start at a level the existing codebase
    passes cleanly (baseline it if needed) rather than the strictest
    level, to avoid an unrelated wall of pre-existing errors blocking
    your change.
```

No other gate produced a gap — Symfony, Composer, and PHPUnit all pass
cleanly with strong training-data support and current documentation, so
no additional compensation entries are needed for those.

### Recommended Instruction File Additions

The single ready-to-paste block above (PHPStan section) is the only
addition recommended by this assessment. Everything else already meets
the bar without compensation.

## Summary

**Overall verdict: ready-with-compensation.** All four components score
well — PHP 8.4 with consistent explicit typing, Symfony's strong
conventions, mainstream training-data presence, and current official
docs. The only real gap is operational, not architectural: PHPStan is
present as a dependency but not wired into CI or configured, so the
codebase's "typed" strength is currently self-enforced rather than
gate-enforced. Wiring PHPStan into `.gitlab-ci.yml` (even at a lenient
baseline) would move this stack from "ready-with-compensation" to
"ready" outright — but this is optional; the CLAUDE.md compensation
above is sufficient for an agent to work safely in the meantime.

Recommended next step: `/10x-health-check` — it will audit dependency
health, the test suite, and CI/CD coverage in more depth, including
whether `composer-audit`, `phpunit`, and the missing PHPStan gate line up
with the project's actual risk profile.
