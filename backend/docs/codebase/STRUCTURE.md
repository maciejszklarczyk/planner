# Codebase Structure

## Core Sections (Required)

### 1) Top-Level Map

| Path | Purpose | Evidence |
|------|---------|----------|
| `src/` | All application source code | `composer.json` autoload |
| `config/` | Symfony package + route YAML config | `config/packages/`, `config/routes.yaml` |
| `migrations/` | Doctrine DB migrations (11 files) | `migrations/Version*.php` |
| `tests/` | PHPUnit test suites | `phpunit.dist.xml`, `tests/` |
| `fixtures/` | Alice YAML fixture files | `fixtures/` dir |
| `src/DataFixtures/` | Doctrine fixtures entry point | `src/DataFixtures/AppFixtures.php` |
| `public/` | Web root (`index.php`), uploaded files | `public/index.php` |
| `templates/` | Twig templates (email) | `templates/` |
| `docker/` | Docker Compose configs | `docker-compose.yaml`, `compose.override.yaml` |
| `docs/` | Architecture specs, plans, codebase docs | `docs/` |
| `var/` | Cache, logs (runtime generated) | `.gitignore` |
| `vendor/` | Composer dependencies (not edited) | `vendor/` |
| `requests/` | Legacy IntelliJ `.http` files — superseded by Bruno collection at project root (`bruno/`) | `requests/` |

### 2) Entry Points

- Main runtime entry: `public/index.php` (Symfony front controller)
- CLI entry: `bin/console` (Symfony console)
- Secondary: none (no workers, no separate queues)
- How entry is selected: web server routes all to `public/index.php`; CLI uses `bin/console`

### 3) Module Boundaries

| Boundary | What belongs here | What must not be here |
|----------|-------------------|------------------------|
| `src/Controller/` | HTTP request handling, input extraction, JSON response building | Business logic, DB queries |
| `src/Controller/Admin/` | Admin-only endpoints (`#[IsGranted('ROLE_ADMIN')]`) | Non-admin user flows |
| `src/Service/` | Business logic, orchestration of repositories; `EventMapper` maps DTOs to Event entity | HTTP handling, direct DB calls |
| `src/Repository/` | Doctrine queries, pagination | Business rules, HTTP concerns |
| `src/Entity/` | Doctrine entities + ORM mapping, enums | Business logic beyond entity state |
| `src/Entity/Enum/` | PHP 8.1 backed enums (`UserGroupRoleEnum`, `UserStatusEnum`, `UserActivityTypeEnum`) | Business logic |
| `src/Dto/` | Input validation (`src/Dto/`) and response transformation (`src/Dto/Response/`) | Persistence, routing |
| `src/Security/` | Voters, auth handlers, entry points | Business rules |
| `src/Exception/` | Domain exception classes | Catching exceptions (controllers handle that) |
| `src/Event/` | Symfony event classes (**planned** — not yet created) | Listeners/subscribers |
| `src/EventSubscriber/` | Event listeners (**planned** — not yet created) | HTTP handling |

### 3a) Key Entities

| Entity | File | Notes |
|--------|------|-------|
| `User` | `src/Entity/User.php` | SoftDeleteable, `UserStatusEnum` (PENDING/ACTIVE/INACTIVE), nullable password |
| `Group` | `src/Entity/Group.php` | SoftDeleteable |
| `UserHasGroup` | `src/Entity/UserHasGroup.php` | Join entity for User↔Group with `UserGroupRoleEnum` (owner/member) |
| `UserInvitationToken` | `src/Entity/UserInvitationToken.php` | SHA-256 hashed token, 1-day TTL |
| `UserActivityLog` | `src/Entity/UserActivityLog.php` | Append-only log; `eventType` stored as string from `UserActivityTypeEnum`; `onDelete: CASCADE` on User FK |
| `Event` | `src/Entity/Event.php` | SoftDeleteable; fields: `name` (string), `startDate` (DateTimeImmutable) |

### 4) Naming and Organization Rules

- **File naming**: PascalCase PHP classes matching class name (`GroupMembershipController.php`, `UserHasGroup.php`)
- **Directory organization**: by layer (Controller, Service, Repository, Entity, Dto, Security, Exception)
- **Namespaces**: `App\Controller\`, `App\Controller\Admin\`, `App\Service\`, `App\Repository\`, `App\Entity\`, `App\Entity\Enum\`, `App\Dto\`, `App\Dto\Response\`, `App\Security\`, `App\Exception\`
- **DB naming strategy**: `underscore_number_aware` (snake_case column names) — configured in `config/packages/doctrine.yaml`
- **Routes**: PHP attributes `#[Route(...)]`, no YAML/XML route files (except framework defaults)
- **No `/api/` prefix**: routes like `/auth/login`, `/admin/groups`, `/user` — API is on separate domain from frontend

### 5) Evidence

- `src/Controller/AuthController.php`
- `src/Controller/Admin/GroupMembershipController.php`
- `src/Service/GroupMembershipService.php`
- `src/Entity/User.php`
- `src/Entity/Group.php`
- `src/Entity/UserActivityLog.php`
- `src/Entity/Enum/UserActivityTypeEnum.php`
- `config/packages/doctrine.yaml`
- `composer.json` (autoload PSR-4 `App\\ -> src/`)
