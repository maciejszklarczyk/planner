# Codebase Concerns

## Core Sections (Required)

### 1) Top Risks (Prioritized)

| Severity | Concern | Evidence | Impact | Suggested action |
|----------|---------|----------|--------|------------------|
| High | No global exception handler | Each controller has its own try/catch blocks mapping domain exceptions to HTTP codes | Inconsistent error responses as codebase grows | Add Symfony event listener on `KernelEvents::EXCEPTION` to centralize error mapping |
| Medium | Test DB not reset between test methods | `DatabaseTestCase` drops+creates schema once per class, not per method | Test pollution: a test that creates data can affect subsequent tests in the same class | Reset DB per method, or use transactions with rollback |
| Medium | `InvitationController` has business logic in controller | `complete()` directly uses `EntityManagerInterface` — no service layer | Hard to test unit-style; violates layered architecture | Extract to `UserRegistrationService` or similar |
| Medium | No observability (logging, metrics, tracing) | No `LoggerInterface` injected anywhere in controllers/services | Cannot diagnose production errors; no visibility into S3/Redis/DB failures | Add structured logging to service layer at minimum |
| Low | Password nullable in User entity | `#[ORM\Column(nullable: true)]` on `password` — invited-but-not-activated users have null password | Could allow auth edge cases if security config changes | Acceptable for invitation flow; document intent clearly |

### 2) Technical Debt

| Debt item | Why it exists | Where | Risk if ignored | Suggested fix |
|-----------|---------------|-------|-----------------|---------------|
| Two PHPUnit config files | `phpunit.dist.xml` (active) and `phpunit.xml.dist` (old) both present | root | Confusion about which config is active | Delete `phpunit.xml.dist` |
| Business logic in `InvitationController` | No service was created when this was built | `src/Controller/InvitationController.php` | Controller grows harder to test; breaks layer rules | Extract to service |
| Activity log Phase 1 partial: entity + enum created, rest missing | Implementation started on branch 16 | `src/Entity/UserActivityLog.php`, `src/Entity/Enum/UserActivityTypeEnum.php` exist; no Repository, migration, Event, Subscriber, Service, or Controller yet | Feature non-functional until Phase 2–4 complete | Implement per `docs/superpowers/plans/2026-04-14-user-activity-log-plan.md` |
| `UserRegisteredEvent` not yet dispatched | Phase 2 not started | `src/Controller/InvitationController.php` — no event dispatch after `setStatus(ACTIVE)` | Activity log will never receive registrations | Dispatch in `InvitationController::complete()` per plan |

### 3) Security Concerns

| Risk | OWASP category | Evidence | Current mitigation | Gap |
|------|----------------|----------|--------------------|-----|
| Brute-force login | A07 (Auth failures) | `config/packages/security.yaml` login_throttling | Rate limiter: 3 attempts / 15 min via Redis | Redis down = fail-open (no rate limiting) |
| CORS misconfiguration | A05 (Security Misconfig) | `config/packages/nelmio_cors.yaml` | Explicit allow-list: `localhost:3000` + `planner.msolve.it`, `allow_credentials: true` | Review if origins change |
| Session security | A07 | `config/packages/security.yaml` | httpOnly sessions, Symfony Security | `secure`/`sameSite` cookie attrs not set in YAML — must be verified at Traefik/server level |
| Mass assignment | A03 (Injection) | `src/Dto/User/EditUserDto.php` | `#[MapRequestPayload]` + `#[Assert\...]` DTOs | No known gap |
| Invitation token storage | A02 (Crypto) | `src/Controller/Admin/UserController.php` | ✅ SHA-256 hashed at rest; raw token sent via email only | None — resolved |

### 4) Performance and Scaling Concerns

| Concern | Evidence | Current symptom | Scaling risk | Suggested improvement |
|---------|----------|-----------------|-------------|-----------------------|
| No pagination on group list | `src/Controller/GroupController.php` `findAll()` | Returns all groups in one query | Unbounded query as groups grow | Add pagination or admin-only limit |
| DB setup per test class | `tests/DatabaseTestCase.php` schema drop/create | Slow test suite on large fixture sets | Longer CI runs | Use transactions + rollback per test |
| No query caching in dev/test | `config/packages/doctrine.yaml` | Dev hits DB every request | Acceptable in dev | Prod cache pool already configured |

### 5) Fragile/High-Churn Areas

| Area | Why fragile | Safe change strategy |
|------|-------------|----------------------|
| `src/Controller/Admin/GroupMembershipController.php` | Admin business logic + multiple exception types | Cover with functional tests before any change |
| `src/Service/GroupMembershipService.php` | Owner-count invariants are business-critical | Unit test owner-count edge cases; run full suite after changes |
| `tests/DatabaseTestCase.php` | Base class for all functional tests; DB reset logic is global | Any change affects every functional test |
| `src/Entity/User.php` | Security interface + ORM + serialization intersection | Schema changes require migration + fixture update + test update |

### 6) Open Questions

1. **[ASK USER]** Are `secure`/`sameSite` session cookie attributes set at Traefik/server level in prod?
2. **[TODO]** No coverage threshold enforced. Run `composer run-coverage` to measure baseline, then set threshold in `phpunit.dist.xml` and enforce in CI.

### 7) Evidence

- `src/Controller/InvitationController.php` (business logic in controller; `UserRegisteredEvent` dispatch missing)
- `src/Controller/Admin/UserController.php` (SHA-256 token hashing implemented)
- `tests/DatabaseTestCase.php` (per-class DB setup)
- `src/Controller/GroupController.php` (`findAll()` without pagination)
- `phpunit.dist.xml` + `phpunit.xml.dist` (two config files)
- `docs/superpowers/plans/2026-04-14-user-activity-log-plan.md` (unimplemented feature on current branch)
