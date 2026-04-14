# User Activity Log — Design Spec

**Date:** 2026-04-14
**Branch:** 16-add-user-activity-log-handling

## Overview

Append-only log of user activity events. User sees own logs; admin sees any user's logs. Start with `user.registered` event; extensible for future event types without DB migrations.

## Entity: `UserActivityLog`

```php
#[ORM\Entity(repositoryClass: UserActivityLogRepository::class)]
class UserActivityLog
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 100)]
    private string $eventType;  // value from UserActivityTypeEnum

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
}
```

- `onDelete: 'CASCADE'` — physical user delete removes logs; soft-deleted users retain logs
- No edits/updates — append-only. Future: admin may delete logs (no schema change needed)

## Enum: `UserActivityTypeEnum`

```php
enum UserActivityTypeEnum: string
{
    case USER_REGISTERED = 'user.registered';
    // future: USER_LOGGED_IN, GROUP_JOINED, GROUP_LEFT, GROUP_ROLE_CHANGED, etc.
}
```

Stored as string in DB — adding new event types requires no migration.

## Service Layer

```
UserRegisteredEvent          # Symfony event carrying User object
    ↓
UserActivitySubscriber       # listens for UserRegisteredEvent
    ↓
UserActivityLogService       # creates and persists UserActivityLog
    ↓
UserActivityLogRepository    # queries logs (with pagination)
```

### `UserActivityLogService`

```php
class UserActivityLogService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function log(User $user, UserActivityTypeEnum $type): void;
}
```

Single public write point. No static methods — fully injectable and testable via mocks.

### `UserActivityLogRepository`

```php
public function findByUser(User $user, int $page, int $limit): array;
public function countByUser(User $user): int;
```

### `UserActivitySubscriber`

Listens on `UserRegisteredEvent`, calls `UserActivityLogService::log($user, UserActivityTypeEnum::USER_REGISTERED)`.

`UserRegisteredEvent` dispatched from the place where user registration completes (controller or existing service).

## API Endpoints

### User endpoint
```
GET /user/activity-logs?page=1&limit=20
Authorization: ROLE_USER (own logs only)
```

### Admin endpoint
```
GET /admin/users/{id}/activity-logs?page=1&limit=20
Authorization: ROLE_ADMIN
```

### Response format
```json
{
  "items": [
    { "id": 1, "eventType": "user.registered", "createdAt": "2026-04-14T10:00:00Z" }
  ],
  "total": 1,
  "page": 1,
  "limit": 20
}
```

## Controllers

Single `UserActivityLogController` with two actions:

- `getMyLogs()` — `#[IsGranted('ROLE_USER')]`, uses `$this->getUser()`
- `getUserLogs(int $id)` — `#[IsGranted('ROLE_ADMIN')]`, fetches user by `{id}` from repository

## Future Extensibility

- New event type: add case to `UserActivityTypeEnum`, dispatch event, add subscriber method — no migration
- Log deletion: `UserActivityLogRepository::deleteByUser(User $user)` — no schema change
- Extra context (IP, performedBy): add nullable columns in future migration
