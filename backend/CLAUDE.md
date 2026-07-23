# Project Instructions

Project guidelines. Add to it in bullet points.

## Tech stack

- **PHP 8.4** (FrankenPHP as server)
- **Symfony 7.4**
- **PostgreSQL 16** (ORM: Doctrine 3.6 + migrations)
- **Redis 7** (cache, sessions, rate limiter, locks)
- **Mailpit** (SMTP dev, `symfony/mailer`)
- Nelmio API Doc Bundle (OpenAPI/Swagger at `/api/doc`)
- Nelmio CORS Bundle
- Stof Doctrine Extensions (SoftDeleteable, naming strategy)
- Alice + Fixtures Bundle (test data)
- PHPUnit 12
- PHP CS Fixer (PSR-12 + Symfony style)
- Symfony Maker Bundle (code generation)
- Xdebug 3 (dev/test)
- Docker Compose (dev) + Traefik (prod)

## Code conventions

- Routing via PHP attributes `#[Route(...)]` (not YAML/XML)
- Controllers extend Symfony's `AbstractController`
- DTOs validated via `#[Assert\...]` + `$validator->validate()`
- Entities mapped via Doctrine attributes (`#[ORM\Entity]`, `#[ORM\Column]`, etc.)
- Soft delete: Gedmo `SoftDeleteable` — entities have a `deletedAt` field of type `\DateTime()`, not physically deleted
- PHP 8.1 enums (e.g. `UserStatusEnum`, `UserGroupRoleEnum`) with `::from()`
- Domain exceptions in `src/Exception/` — thrown from services, caught in controllers
- Response DTOs in `src/Dto/Response/` — entity-to-JSON transformation
- Request DTOs in `src/Dto/` — input validation
- CS Fixer for PSR-12 + Symfony style: `vendor/bin/php-cs-fixer fix`
- Admin endpoints: `#[IsGranted('ROLE_ADMIN')]`
- Separate domain for frontend and API — no `/api/` segment in endpoint URLs
- CI has a `composer-audit` job that fails the pipeline on any advisory from `composer audit`. If an advisory has no upstream fix yet: add an entry to `composer.json` → `config.audit.ignore` with a comment/link to the tracking issue — don't disable the job in `.github/workflows/backend-ci.yml`.

## Important paths and files

- `src/Controller/Admin/GroupMembershipController.php` — group membership management
- `src/Service/GroupMembershipService.php` — group logic (add/remove/update role)
- `src/Entity/UserHasGroup.php` — User ↔ Group entity with role
- `src/Entity/Enum/UserGroupRoleEnum.php` — `owner | member`
- `src/Exception/CannotRemoveLastOwnerException.php` — thrown when trying to remove the last owner
- `config/packages/security.yaml` — sessions, json_login, access_control
- `config/packages/nelmio_cors.yaml` — CORS (allowed origins: localhost:3000, planner.msolve.it)
- `.env.test` — required for functional tests

## Running the project

- Dev server: `docker compose up`

## Authentication in tests and local dev

- `DevHeaderAuthenticator` is active in `dev` and `test` environments — just send the header `X-Dev-User: email@example.com` to authenticate as any user from the fixtures.
- In tests, pass the header via `HTTP_X_DEV_USER` (Symfony converts it automatically):
  ```php
  $client->request('GET', '/events', [], [], ['HTTP_X_DEV_USER' => 'user1@example.com']);
  ```
- Don't use `/auth/login` in new tests — `X-Dev-User` is simpler and doesn't require a session.
- ModHeader (or equivalent) in the browser: add header `X-Dev-User: admin@example.com` and switch users without logging out.

## Tests

- Functional tests require `.env.test` as environment variables — without it Symfony won't load `framework.test: true` and `createClient()` will throw `LogicException`.
- Running from the CLI (outside PhpStorm):
  ```
  docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit
  ```
- PhpStorm: Docker Compose configuration with the "Environment variables" field = `.env.test`.
- PHPUnit config file: `phpunit.dist.xml` (not `phpunit.xml.dist` — old file).
- Deterministic fixtures — no random assignments. Group map:
  - `group_1`: admin=owner, user_1=member, user_2=member
  - `group_2`: admin=member, user_1=owner, user_3=member
  - `group_3`: user_2=owner, user_3=member, user_4=member
  - `group_4`: user_4=owner, user_5=member
  - `group_5`: user_5=owner
- Use data providers where possible to keep tests smaller.

## Other notes

- Login rate limiting: 3 attempts / 15 min (`symfony/rate-limiter`)
- Invitation token: `bin2hex(random_bytes(32))`, valid for 1 day
- `UserRepository` supports pagination, search (`search` parameter), and `excludeGroupId`
- Doctrine naming strategy: `underscore_number_aware` (snake_case in the database)
