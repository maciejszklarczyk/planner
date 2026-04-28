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
├── bootstrap.php
├── DatabaseTestCase.php                                        # Base class: real DB setup + fixtures
├── Controller/                                                 # Mixed functional (no Functional/ prefix)
│   ├── EventControllerTest.php
│   └── HealthCheckControllerTest.php
├── Functional/
│   ├── Controller/
│   │   ├── AuthControllerTest.php
│   │   ├── GroupControllerTest.php
│   │   └── Admin/
│   │       ├── GroupMembershipControllerTest.php
│   │       └── UserControllerTest.php
│   ├── Repository/
│   │   ├── UserInvitationTokenRepositoryTest.php
│   │   └── UserRepositoryTest.php
│   └── Security/
│       └── DevHeaderAuthenticatorTest.php
└── Unit/
    ├── Dto/
    │   ├── EditUserDtoTest.php
    │   ├── InvitationCompleteDtoTest.php
    │   └── UserInviteDtoTest.php
    ├── Entity/
    │   ├── EventTest.php
    │   ├── GroupTest.php
    │   └── UserTest.php
    ├── Security/
    │   └── GroupVoterTest.php
    └── Service/
        └── InvitationMailerTest.php
```

- Naming convention: `{ClassName}Test.php`
- Functional tests extend `App\Tests\DatabaseTestCase` (which extends `WebTestCase`)
- Unit tests extend `PHPUnit\Framework\TestCase` directly (no DB needed)
- Note: `tests/Controller/` is a second root-level functional dir (EventController, HealthCheckController tests live here, not under `tests/Functional/`)

### 3) Test Scope Matrix

| Scope | Covered? | Typical target | Notes |
|-------|----------|----------------|-------|
| Unit | Yes (7 files) | DTOs, entities, GroupVoter, InvitationMailer | Uses PHPUnit mocks for dependencies |
| Functional/Integration | Yes (9 files) | Auth, Group, Event, Admin, Repo, Security | Hit real PostgreSQL test DB; no mocking of services or repos |
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
  - `UserController` (PUT /user, DELETE /user/{userId}) — no dedicated test file
  - `InvitationController`, `UserAvatarController` — no dedicated test files
  - `UserActivityLog` — entity, repository (stub, buggy), and migration exist; no service, event, subscriber, or controller; no tests

### 6) Evidence

- `phpunit.dist.xml`
- `tests/DatabaseTestCase.php`
- `tests/Functional/Controller/AuthControllerTest.php`
- `tests/Unit/Security/GroupVoterTest.php`
- `config/packages/test/doctrine.yaml`
- `config/packages/security.yaml` (`when@test` section)
- `README_TESTS.md`
