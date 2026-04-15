# Coding Conventions

## Core Sections (Required)

### 1) Naming Rules

| Item | Rule | Example | Evidence |
|------|------|---------|----------|
| PHP files/classes | PascalCase | `GroupMembershipController.php`, `UserHasGroup.php` | `src/` |
| Methods | camelCase | `addUserToGroup()`, `findByGroup()` | `src/Service/GroupMembershipService.php` |
| Enums | PascalCase class, UPPER_CASE cases, string value `snake.dot` | `UserGroupRoleEnum::OWNER = 'owner'` | `src/Entity/Enum/UserGroupRoleEnum.php` |
| DB columns | snake_case (Doctrine naming strategy `underscore_number_aware`) | `user_has_group`, `deleted_at` | `config/packages/doctrine.yaml` |
| Constants in classes | UPPER_CASE | `GroupVoter::VIEW = 'view'` | `src/Security/GroupVoter.php` |
| Event classes | PascalCase + `Event` suffix | `UserRegisteredEvent` (planned) | `docs/superpowers/plans/2026-04-14-user-activity-log-plan.md` |
| Subscriber classes | PascalCase + `Subscriber` suffix | `UserActivitySubscriber` (planned) | `docs/superpowers/plans/2026-04-14-user-activity-log-plan.md` |

### 2) Formatting and Linting

- **Formatter**: PHP CS Fixer — config at `.php-cs-fixer.dist.php` (PSR-12 + Symfony ruleset)
- **Linter**: PHPStan — `phpstan.neon` or default config; `composer phpstan` runs it at `src` + `tests`
- Enforced rules: `declare(strict_types=1)` at top of every file, PSR-12 imports, Symfony style method ordering
- Run commands:
  ```bash
  composer cs-fix           # fix in place
  composer cs-fix-analyse   # dry-run check
  composer phpstan          # static analysis
  ```

### 3) Import and Module Conventions

- All PHP files start with `declare(strict_types=1);` — verified in every source file read
- Namespace: `App\` mapped to `src/` via PSR-4 (`composer.json`)
- No path aliases — standard PHP namespace imports
- Imports grouped: PHP built-ins, then Symfony/Doctrine packages, then `App\` classes

### 4) Error and Logging Conventions

- **Service layer**: throws domain exceptions (`CannotRemoveLastOwnerException`, `UserAlreadyInGroupException`, `GroupAlreadyHasOwnerException`) and Symfony `NotFoundHttpException`
- **Controller layer**: catches exceptions in try/catch blocks, maps to JSON error responses with `error` code string and `message` key
- Error response shape: `{"error": "ERROR_CODE", "message": "Human-readable text"}`
- **No structured logging** observed — no `LoggerInterface` injection found in controllers or services
- **No global exception listener** — each controller independently maps exceptions to HTTP codes

### 5) Testing Conventions

- Test file location: `tests/Functional/Controller/` (functional) and `tests/Unit/` (unit) — mirrors layer structure
- Naming: `{ClassName}Test.php`, method names `test{What}` or `#[DataProvider]` style
- **Functional tests**: extend `DatabaseTestCase` → `WebTestCase`; use real PostgreSQL test DB (`_test` suffix), real fixtures, no mocking of repositories or services
- **Unit tests**: `GroupVoterTest.php` in `tests/Unit/Security/` — uses PHPUnit mocks for dependencies
- DB setup: `DatabaseTestCase::setUpBeforeClass()` runs schema drop/create + fixture load once per test class (not per test method)
- Coverage: no threshold configured in `phpunit.dist.xml`; coverage requires Xdebug (`--coverage-html coverage/`)

### 6) Evidence

- `.php-cs-fixer.dist.php` (config file; not read but referenced in `composer.json`)
- `composer.json` (`scripts.cs-fix`, `scripts.phpstan`)
- `src/Controller/Admin/GroupMembershipController.php` (error response pattern)
- `src/Service/GroupMembershipService.php` (exception throwing pattern)
- `tests/DatabaseTestCase.php`
- `tests/Unit/Security/GroupVoterTest.php`
