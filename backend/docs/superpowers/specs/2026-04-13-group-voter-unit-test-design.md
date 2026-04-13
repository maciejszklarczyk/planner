# GroupVoter Unit Test Design

## Goal

Unit tests for `App\Security\GroupVoter` covering all vote outcomes without hitting the database.

## Scope

- `tests/Unit/Security/GroupVoterTest.php`
- No database, no Symfony kernel — pure PHPUnit `TestCase`

## Approach

Single test method with `#[DataProvider]`. Data provider passes string descriptors; test method builds entity objects.

```
testVote(attribute, subjectType, userRole, isAdmin, expected)
```

| Param | Values |
|---|---|
| `$attribute` | `'view'`, `'delete'`, `'edit'` (unsupported) |
| `$subjectType` | `'group'`, `'non-group'` |
| `$userRole` | `'owner'`, `'member'`, `'user'` (non-member), `null` (unauthenticated) |
| `$isAdmin` | `true`, `false` |
| `$expected` | `VoterInterface::ACCESS_GRANTED / DENIED / ABSTAIN` |

## Test Cases

| Key | attribute | subjectType | userRole | isAdmin | expected |
|---|---|---|---|---|---|
| abstain: unsupported attribute | `edit` | `group` | `user` | false | ABSTAIN |
| abstain: non-Group subject | `view` | `non-group` | `user` | false | ABSTAIN |
| deny: unauthenticated + view | `view` | `group` | `null` | false | DENIED |
| deny: unauthenticated + delete | `delete` | `group` | `null` | false | DENIED |
| grant: admin + view | `view` | `group` | `user` | true | GRANTED |
| grant: admin + delete | `delete` | `group` | `user` | true | GRANTED |
| grant: view → owner | `view` | `group` | `owner` | false | GRANTED |
| grant: view → member | `view` | `group` | `member` | false | GRANTED |
| deny: view → non-member | `view` | `group` | `user` | false | DENIED |
| grant: delete → owner | `delete` | `group` | `owner` | false | GRANTED |
| deny: delete → member | `delete` | `group` | `member` | false | DENIED |
| deny: delete → non-member | `delete` | `group` | `user` | false | DENIED |

## Entity Setup

`Group::addUserHasGroup` and `User::addUserHasGroup` call non-existent `setGroups()`/`setUsers()` on `UserHasGroup`. Bypass via `ReflectionClass` — get `userHasGroups` `ArrayCollection` and call `add()` directly.

## Dependencies

- Mock `AccessDecisionManagerInterface` — returns `$isAdmin`
- Mock `TokenInterface` — returns `$user`
- Real `Group`, `User`, `UserHasGroup` entity objects

## Files

```
tests/
└── Unit/
    └── Security/
        └── GroupVoterTest.php   ← new
```
