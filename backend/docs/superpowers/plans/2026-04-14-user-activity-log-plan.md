# Implementation Plan: User Activity Log

## Summary

Append-only activity log for users. User sees own logs; admin sees any user's logs. Initial event: `user.registered`, dispatched via Symfony event system, handled by subscriber calling dedicated service.

## Scope

- IN: `UserActivityLog` entity, migration, `UserActivityTypeEnum`, `UserActivityLogService`, `UserActivitySubscriber`, `UserRegisteredEvent`, `UserActivityLogController`, `UserActivityLogRepository`, functional tests
- OUT: log deletion, IP/context fields, events other than `user.registered`

## Dependencies

- Existing: `User` entity, `InvitationController` (dispatch point for `user.registered`), `DatabaseTestCase`, `EntityManagerInterface`
- No new packages required

---

## Technical Design

### Entity: `UserActivityLog`

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
    private string $eventType;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
}
```

### Enum: `UserActivityTypeEnum`

```php
enum UserActivityTypeEnum: string
{
    case USER_REGISTERED = 'user.registered';
}
```

Stored as `string` — new cases require no DB migration.

### Event: `UserRegisteredEvent`

```php
class UserRegisteredEvent
{
    public function __construct(public readonly User $user) {}
}
```

### Service: `UserActivityLogService`

```php
class UserActivityLogService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function log(User $user, UserActivityTypeEnum $type): void
    {
        $log = new UserActivityLog();
        $log->setUser($user);
        $log->setEventType($type->value);
        $log->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($log);
        $this->em->flush();
    }
}
```

### Subscriber: `UserActivitySubscriber`

```php
class UserActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(private UserActivityLogService $logService) {}

    public static function getSubscribedEvents(): array
    {
        return [UserRegisteredEvent::class => 'onUserRegistered'];
    }

    public function onUserRegistered(UserRegisteredEvent $event): void
    {
        $this->logService->log($event->user, UserActivityTypeEnum::USER_REGISTERED);
    }
}
```

### Repository: `UserActivityLogRepository`

```php
public function findByUser(User $user, int $page, int $limit): array;
public function countByUser(User $user): int;
```

### Controller: `UserActivityLogController`

```php
#[Route('/user/activity-logs')]
#[IsGranted('ROLE_USER')]
public function getMyLogs(#[CurrentUser] User $user, Request $request): JsonResponse

#[Route('/admin/users/{id}/activity-logs')]
#[IsGranted('ROLE_ADMIN')]
public function getUserLogs(int $id, Request $request): JsonResponse
```

Response DTO: `UserActivityLogDto` with `id`, `eventType`, `createdAt`.

Paginated response:
```json
{ "items": [...], "total": 1, "page": 1, "limit": 20 }
```

---

## Implementation Steps

### Phase 1: Foundation

1. [ ] Create `UserActivityTypeEnum` in `src/Entity/Enum/`
2. [ ] Create `UserActivityLog` entity in `src/Entity/`
3. [ ] Create `UserActivityLogRepository` in `src/Repository/`
4. [ ] Generate and run Doctrine migration

### Phase 2: Event System

5. [ ] Create `UserRegisteredEvent` in `src/Event/`
6. [ ] Create `UserActivityLogService` in `src/Service/`
7. [ ] Create `UserActivitySubscriber` in `src/EventSubscriber/`
8. [ ] Dispatch `UserRegisteredEvent` in `InvitationController::complete()` after `setStatus(ACTIVE)`

### Phase 3: API

9. [ ] Create `UserActivityLogDto` in `src/Dto/Response/`
10. [ ] Create `UserActivityLogController` in `src/Controller/`

### Phase 4: Tests

11. [ ] Functional test: user sees own logs (`GET /user/activity-logs`)
12. [ ] Functional test: admin sees user logs (`GET /admin/users/{id}/activity-logs`)
13. [ ] Functional test: user cannot access another user's logs (403)
14. [ ] Functional test: completing invitation creates `user.registered` log entry

---

## Acceptance Criteria

- [ ] Completing invitation dispatches `UserRegisteredEvent`
- [ ] `UserActivitySubscriber` persists `UserActivityLog` with `user.registered`
- [ ] `GET /user/activity-logs` returns paginated logs for current user
- [ ] `GET /admin/users/{id}/activity-logs` returns paginated logs for any user (admin only)
- [ ] Non-admin cannot access admin endpoint (403)
- [ ] User cannot access another user's logs

---

## Risks

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Event not dispatched in all registration paths | Medium | Medium | Check all places where `UserStatusEnum::ACTIVE` is set |
| Migration conflict on branch | Low | Low | Generate migration fresh after rebase |
