# External Integrations

## Core Sections (Required)

### 1) Integration Inventory

| System | Type | Purpose | Auth model | Criticality | Evidence |
|--------|------|---------|------------|-------------|----------|
| PostgreSQL 16 | Database | Primary data store | Env-var DSN (`DATABASE_URL`) | High | `config/packages/doctrine.yaml`, `.env` |
| Redis 7 | Cache + rate limiter storage | App cache pool, login rate limit pool | Env-var DSN (`REDIS_URL`) | High | `config/packages/cache.yaml`, `config/packages/rate_limiter.yaml` |
| S3-compatible storage | Object storage | User avatar files | `S3_KEY`/`S3_SECRET` env vars | Medium | `config/packages/flysystem.yaml`, `.env` |
| Mailpit (dev) / SMTP (prod) | Email | User invitation emails | `MAILER_DSN` env var | High (invitations) | `config/packages/mailer.yaml`, `src/Service/InvitationMailer.php` |

### 2) Data Stores

| Store | Role | Access layer | Key risk | Evidence |
|-------|------|--------------|----------|----------|
| PostgreSQL | All entity persistence: User, Group, UserHasGroup, UserInvitationToken | Doctrine ORM (`EntityManagerInterface`, Repository classes) | Schema drift if migrations are skipped; `_test` DB used for tests | `config/packages/doctrine.yaml` |
| Redis | Application cache (`cache.app`), rate limiter pool (`cache.rate_limiter`) | Symfony Cache component | Redis down = no rate limiting (risk of brute-force); app cache misses degrade perf | `config/packages/cache.yaml` |
| S3-compatible (RustFS/MinIO in dev) | User avatar storage (`uploads.storage` Flysystem adapter) | `UserAvatarController` + Flysystem | Credentials misconfiguration = broken avatar upload/display | `config/packages/flysystem.yaml`, `.env` |

### 3) Secrets and Credentials Handling

- Credential sources: environment variables only (`.env` has defaults, `.env.local` holds real secrets, never committed)
- Env vars for secrets: `APP_SECRET`, `DATABASE_URL`, `REDIS_URL`, `S3_KEY`, `S3_SECRET`, `MAILER_DSN`
- No hardcoding observed in source files
- Secret rotation: no automated rotation mechanism observed (`[TODO]` — manual rotation)
- `.env.test` holds test DB config; loaded for tests via `--env` flag or PhpStorm config

### 4) Reliability and Failure Behavior

- **Retry/backoff**: none implemented — service calls to DB/Redis/S3 fail immediately on connection error
- **Timeout policy**: Doctrine DBAL uses PHP default socket timeouts; no explicit timeout config found
- **Circuit breaker**: none observed
- **Rate limiter**: login throttling at 3 attempts / 15 min stored in Redis `cache.rate_limiter` pool; Redis down = rate limiting disabled (fail-open)

### 5) Observability for Integrations

- **Logging**: no `LoggerInterface` observed in controllers or services; Symfony dev profiler toolbar available in dev
- **Metrics/tracing**: none configured
- **Missing visibility gaps**: no structured logs for integration failures (Redis down, S3 unavailable, DB connection lost), no alerts, no health check for Redis or S3 (only `/health` endpoint exists but its implementation is minimal — `src/Controller/HealthCheck.php`)

### 6) Dev Tooling for API

| Tool | Location | Purpose |
|------|----------|---------|
| Bruno | `bruno/` (project root) | Manual API request collection — replaces IntelliJ `.http` files in `backend/requests/` |
| IntelliJ HTTP | `backend/requests/` | Legacy `.http` files — superseded by Bruno but still present |

**Bruno collection details:**
- Collection name: `Planner`
- Format: OpenCollection 1.0.0 (YAML per request)
- Default header: `X-Dev-User: admin@example.com` (dev auth, set at collection level)
- Environments: `DEV` (`http://localhost:8000`), `LIVE` (`https://api-planner.msolve.it`)
- Folders: `auth/`, `Event/`, `Groups/`, `User/`
- Covered endpoints: login (admin + user1), logout, me, health, events CRUD, groups CRUD, user avatar upload

### 7) Evidence

- `.env`
- `config/packages/doctrine.yaml`
- `config/packages/cache.yaml`
- `config/packages/rate_limiter.yaml`
- `config/packages/flysystem.yaml`
- `config/packages/mailer.yaml`
- `config/packages/nelmio_cors.yaml`
- `src/Service/InvitationMailer.php`
- `bruno/opencollection.yml`
- `bruno/environments/DEV.yml`
