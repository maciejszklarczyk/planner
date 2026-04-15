# Architecture

## Core Sections (Required)

### 1) Architectural Style

- **Primary style**: Layered (Controller → Service → Repository → Entity)
- **Why this classification**: Each layer has distinct responsibility: controllers parse HTTP and return JSON, services own business logic, repositories own DB queries, entities own data shape. Services don't know about HTTP; controllers don't write queries.
- **Primary constraints**:
  1. Admin endpoints always use `#[IsGranted('ROLE_ADMIN')]` at controller class level
  2. Soft delete only — entities with `deletedAt` (User, Group) are never hard-deleted from code
  3. Domain exceptions are thrown from services and caught in controllers (no global exception handler)

### 2) System Flow

```text
HTTP Request
  → Symfony Security (session check / json_login authenticator)
  → Controller (parse input, optionally #[MapRequestPayload] DTO)
  → Symfony Validator (validates DTO via #[Assert\...] annotations)
  → Service (business logic, throws domain exceptions)
  → Repository (Doctrine queries, pagination)
  → Entity (data mutated via setters)
  → EntityManager::flush() (persist to PostgreSQL)
  → Controller builds JSON response (Response DTO or inline array)
  → JsonResponse returned
```

Notable side paths:
- Voter check (`#[IsGranted('view', 'group')]`) happens between Security and Controller for resource-level authorization
- Invitations: `InvitationController::complete()` sets `UserStatusEnum::ACTIVE` — planned dispatch point for `UserRegisteredEvent` (not yet wired; Phase 2 of branch 16 activity log feature)
- File upload: `UserAvatarController` → Flysystem → S3

### 3) Layer/Module Responsibilities

| Layer or module | Owns | Must not own | Evidence |
|-----------------|------|--------------|----------|
| `Controller` | HTTP input/output, DTO instantiation via `#[MapRequestPayload]`, exception-to-HTTP mapping | Business rules, DB queries | `src/Controller/Admin/GroupMembershipController.php` |
| `Service` | Business rules (owner count checks, exception throwing), orchestration of multiple repos | HTTP status codes, Response serialization | `src/Service/GroupMembershipService.php` |
| `Repository` | Doctrine queries, DQL, pagination logic, custom filters (`excludeGroupId`) | Business logic, HTTP | `src/Repository/UserRepository.php` |
| `Entity` | ORM mapping attributes, getters/setters, simple domain helpers (`isMemberOf`, `getGroupOwnerUser`) | Complex business rules, HTTP, repos | `src/Entity/User.php`, `src/Entity/Group.php` |
| `Dto/` | Input validation constraints, `fromEntity()` static factory methods for response DTOs | Persistence, routing | `src/Dto/Response/UserListItemDto.php` |
| `Security/` | Voter logic (`GroupVoter`), JSON auth/logout handlers, entry point | Business rules | `src/Security/GroupVoter.php` |
| `Exception/` | Domain exception classes (thrown by services) | Catching, HTTP mapping | `src/Exception/CannotRemoveLastOwnerException.php` |

### 4) Reused Patterns

| Pattern | Where found | Why it exists |
|---------|-------------|---------------|
| Repository pattern | `src/Repository/*.php` | Doctrine standard; isolates DB from business logic |
| DTO (Request + Response) | `src/Dto/`, `src/Dto/Response/` | Separates HTTP input validation from entity shape; clean API contract |
| Voter | `src/Security/GroupVoter.php` | Resource-level authorization (view/delete Group) decoupled from controller |
| Soft delete | `User`, `Group` entities via Gedmo `SoftDeleteableEntity` | Preserve audit trail; Doctrine filter excludes soft-deleted from queries |
| Domain exceptions | `src/Exception/*.php` thrown in services, caught in controllers | Clean error boundary between service and HTTP layer |
| Static factory DTOs | `GroupListItemDto::fromEntity()`, `UserListItemDto::fromEntities()` | Consistent entity-to-JSON mapping without exposing entity internals |

### 5) Known Architectural Risks

- **No global exception handler**: each controller catch block duplicates the error-to-HTTP mapping pattern; inconsistency will grow as more endpoints are added
- **Fat controller for invitations**: `InvitationController::complete()` directly uses `EntityManagerInterface` without a service — business logic leaks into controller
- **Existing docs outdated**: `docs/01-tech-stack.md`, `docs/02-data-model.md`, `docs/03-api-design.md`, `docs/04-architecture.md` describe a different (earlier) project schema with Trip/Expense entities; these do not reflect the current codebase

### 6) Evidence

- `src/Controller/Admin/GroupMembershipController.php`
- `src/Service/GroupMembershipService.php`
- `src/Repository/UserRepository.php`
- `src/Entity/User.php`
- `src/Security/GroupVoter.php`
- `src/Exception/CannotRemoveLastOwnerException.php`
