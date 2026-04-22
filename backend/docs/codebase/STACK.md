# Technology Stack

## Core Sections (Required)

### 1) Runtime Summary

| Area | Value | Evidence |
|------|-------|----------|
| Primary language | PHP 8.4 | `composer.json` `require.php: >=8.2`, `CLAUDE.md` specifies 8.4 |
| Runtime | FrankenPHP (production), PHP-FPM (dev via Docker) | `CLAUDE.md`, `docker-compose.yaml` |
| Package manager | Composer | `composer.json`, `composer.lock` |
| Module/build system | Symfony Flex (autoconfig) | `composer.json` `extra.symfony` |

### 2) Production Frameworks and Dependencies

| Dependency | Version | Role in system | Evidence |
|------------|---------|----------------|----------|
| symfony/framework-bundle | 7.4.* | Core Symfony DI, routing, HTTP kernel | `composer.json` |
| symfony/security-bundle | 7.4.* | Session auth, json_login, voters | `composer.json`, `config/packages/security.yaml` |
| doctrine/orm | ^3.6.3 | ORM, entity mapping via attributes | `composer.json`, `config/packages/doctrine.yaml` |
| doctrine/doctrine-migrations-bundle | ^4.0 | DB schema versioning (11 migrations) | `composer.json`, `migrations/` |
| stof/doctrine-extensions-bundle | ^1.15.3 | SoftDeleteable trait (`deletedAt` field) | `composer.json`, `config/packages/stof_doctrine_extensions.yaml` |
| nelmio/api-doc-bundle | ^5.10.0 | OpenAPI/Swagger docs at `/api/doc` | `composer.json`, `config/packages/nelmio_api_doc.yaml` |
| nelmio/cors-bundle | ^2.6.1 | CORS for `localhost:3000` and `planner.msolve.it` | `composer.json`, `config/packages/nelmio_cors.yaml` |
| symfony/mailer | 7.4.* | Email sending (invitations) | `composer.json`, `src/Service/InvitationMailer.php` |
| symfony/rate-limiter | 7.4.* | Login throttling (3 attempts / 15 min) | `composer.json`, `config/packages/rate_limiter.yaml` |
| symfony/lock | 7.4.* | Distributed locking | `composer.json`, `config/packages/lock.yaml` |
| symfony/cache | 7.4.* | Redis-backed app cache | `composer.json`, `config/packages/cache.yaml` |
| league/flysystem-bundle | ^3.6 | File storage abstraction | `composer.json` |
| league/flysystem-aws-s3-v3 | ^3.32 | S3-compatible storage for avatars | `composer.json`, `config/packages/flysystem.yaml` |
| symfony/serializer | 7.4.* | JSON serialization | `composer.json` |
| symfony/validator | 7.4.* | DTO validation via `#[Assert\...]` | `composer.json` |
| twig/twig | ^3.24 | Email templates | `composer.json` |

### 3) Development Toolchain

| Tool | Purpose | Evidence |
|------|---------|----------|
| PHPUnit 12 | Test runner | `composer.json` `require-dev`, `phpunit.dist.xml` |
| PHP CS Fixer | Code formatting (PSR-12 + Symfony style) | `composer.json` `scripts.cs-fix` |
| PHPStan | Static analysis | `composer.json` `scripts.phpstan` |
| symfony/maker-bundle | Code generation scaffolding | `composer.json` `require-dev` |
| hautelook/alice-bundle + nelmio/alice | Test fixtures (YAML-based fake data) | `composer.json` `require-dev`, `src/DataFixtures/` |
| symfony/web-profiler-bundle | Dev profiler toolbar | `composer.json` `require-dev` |
| Xdebug 3 | Step debugging + coverage | `CLAUDE.md` |
| Mailpit | SMTP dev mail trap | `CLAUDE.md` |

### 4) Key Commands

```bash
# Install dependencies
composer install

# Run all checks (CS + PHPStan + tests)
composer run-all

# Code style fix
composer cs-fix

# Static analysis
composer phpstan

# Tests (from CLI outside PhpStorm)
docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit

# Migrations
bin/console doctrine:migrations:migrate

# Load fixtures
bin/console doctrine:fixtures:load --no-interaction
```

### 5) Environment and Config

- Config sources: `.env` (base), `.env.test` (test overrides), `.env.local` (local secrets, not committed)
- Required env vars:
  - `DATABASE_URL` — PostgreSQL DSN
  - `REDIS_URL` — Redis DSN (`redis://host:port`)
  - `MAILER_DSN` — SMTP DSN (Mailpit in dev)
  - `APP_SECRET` — Symfony security secret
  - `S3_ENDPOINT`, `S3_KEY`, `S3_SECRET`, `S3_BUCKET`, `S3_REGION` — S3-compatible object storage
  - `CORS_ALLOW_ORIGIN` — CORS origin regex
  - `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD` — used by Docker
- Deployment: Docker Compose (dev), FrankenPHP + Docker (prod), Traefik reverse proxy (prod)

### 6) Evidence

- `composer.json`
- `composer.lock`
- `config/packages/`
- `.env`
- `CLAUDE.md`
