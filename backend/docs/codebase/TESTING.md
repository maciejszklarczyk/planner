# Testing Patterns

## Core Sections (Required)

### 1) Test Stack and Commands

- **Primary test framework**: PHPUnit 12 (`phpunit/phpunit: ^12.5.17`)
- **Assertion tools**: PHPUnit built-in assertions; Symfony WebTestCase HTTP client
- **Fixture tool**: Alice (YAML-based) via HautelookAliceBundle + nelmio/alice; loaded through Doctrine Fixtures Bundle

```bash
# Run all tests (from inside Docker container, or with env vars)
docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit

# Run single test file
vendor/bin/phpunit tests/Functional/Controller/AuthControllerTest.php

# Run with readable output
vendor/bin/phpunit --testdox

# Run with coverage (requires Xdebug)
vendor/bin/phpunit --coverage-html coverage/

# Via composer script
composer run-tests
```

### 2) Test Layout

```
tests/
├── bootstrap.php                          # Symfony test bootstrap
├── DatabaseTestCase.php                   # Base class: real DB setup + fixtures
├── Unit/
│   └── Security/
│       └── GroupVoterTest.php             # Unit test with PHPUnit mocks
└── Functional/
    └── Controller/
        ├── AuthControllerTest.php         # 10 auth flow tests
        ├── GroupControllerTest.php        # Group endpoints
        └── Admin/
            └── GroupMembershipControllerTest.php  # Admin group member management
```

- Naming convention: `{ClassName}Test.php`
- Functional tests extend `App\Tests\DatabaseTestCase` (which extends `WebTestCase`)
- Unit tests extend `PHPUnit\Framework\TestCase` directly (no DB needed)

### 3) Test Scope Matrix

| Scope | Covered? | Typical target | Notes |
|-------|----------|----------------|-------|
| Unit | Yes (1 file) | `GroupVoter` security voter | Uses PHPUnit mocks for `AccessDecisionManagerInterface` |
| Functional/Integration | Yes (3 files) | Auth, Group, Admin group membership controllers | Hit real PostgreSQL test DB; no mocking of services or repos |
| E2E | No | — | No browser/Panther tests |

### 4) Mocking and Isolation Strategy

- **Functional tests**: NO mocking — real Symfony kernel, real PostgreSQL (`_test` suffix DB), real fixtures loaded once per test class via `DatabaseTestCase::setUpBeforeClass()`
- **Unit tests**: PHPUnit `createMock()` for dependencies (seen in `GroupVoterTest.php`)
- **DB isolation**: `DatabaseTestCase` drops/creates schema and reloads fixtures once per class (`static $isDbSetUp` guard). Not reset between individual test methods — tests must not leave state that breaks following tests
- **Test DB config**: loaded from `.env.test`; `dbname_suffix: '_test'` appended (see `config/packages/test/doctrine.yaml`)
- **Password hashing in tests**: bcrypt cost=4 (lowest) to speed up auth tests (`config/packages/security.yaml` `when@test`)
- **Common failure mode**: missing `.env.test` causes `LogicException` from `createClient()` — Symfony won't load `framework.test: true`

### 5) Coverage and Quality Signals

- **Coverage tool**: Xdebug + PHPUnit `--coverage-html`
- **Coverage threshold**: none configured in `phpunit.dist.xml`
- **Current gaps**:
  - `UserController`, `InvitationController`, `UserAvatarController`, `GroupController` — no dedicated test files
  - `UserGroupService`, `InvitationMailer` services — no unit tests
  - Admin `UserController` (invite/resend) — not directly tested
  - Planned: `UserActivityLogController` tests (per `docs/superpowers/plans/2026-04-14-user-activity-log-plan.md`)

### 6) Evidence

- `phpunit.dist.xml`
- `tests/DatabaseTestCase.php`
- `tests/Functional/Controller/AuthControllerTest.php`
- `tests/Unit/Security/GroupVoterTest.php`
- `config/packages/test/doctrine.yaml`
- `config/packages/security.yaml` (`when@test` section)
- `README_TESTS.md`
