<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Dto\User\EditUserDto;
use App\Entity\Enum\UserStatusEnum;
use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserHasGroup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    public function testIdIsNullByDefault(): void
    {
        self::assertNull((new User())->getId());
    }

    public function testConstructorInitializesEmptyCollection(): void
    {
        self::assertCount(0, (new User())->getUserHasGroups());
    }

    public function testSetAndGetEmail(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        self::assertSame('test@example.com', $user->getEmail());
    }

    public function testGetUserIdentifier(): void
    {
        $user = new User();
        $user->setEmail('id@example.com');

        self::assertSame('id@example.com', $user->getUserIdentifier());
    }

    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $user = new User();

        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function testSetAndGetRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function testHasRole(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        self::assertTrue($user->hasRole('ROLE_ADMIN'));
        self::assertTrue($user->hasRole('ROLE_USER'));
        self::assertFalse($user->hasRole('ROLE_SUPERADMIN'));
    }

    public function testSetAndGetPassword(): void
    {
        $user = new User();
        $user->setPassword('hashed_secret');

        self::assertSame('hashed_secret', $user->getPassword());
    }

    public function testSerializeHashesPassword(): void
    {
        $user = new User();
        $user->setPassword('plain');

        $data = $user->__serialize();

        $key = "\0".User::class."\0password";
        self::assertArrayHasKey($key, $data);
        self::assertSame(hash('crc32c', 'plain'), $data[$key]);
    }

    public function testSetAndGetName(): void
    {
        $user = new User();
        $user->setName('John Doe');

        self::assertSame('John Doe', $user->getName());
    }

    public function testSetNullName(): void
    {
        $user = new User();
        $user->setName(null);

        self::assertNull($user->getName());
    }

    public function testUpdateFromDto(): void
    {
        $user = new User();
        $user->setEmail('old@example.com');
        $user->setName('Old Name');

        $dto = new EditUserDto(id: 1, email: 'new@example.com', name: 'New Name');
        $user->updateFromDto($dto);

        self::assertSame('new@example.com', $user->getEmail());
        self::assertSame('New Name', $user->getName());
    }

    public function testSetAndGetAddedBy(): void
    {
        $user = new User();
        $addedBy = new User();
        $user->setAddedBy($addedBy);

        self::assertSame($addedBy, $user->getAddedBy());
    }

    public function testSetNullAddedBy(): void
    {
        $user = new User();
        $user->setAddedBy(null);

        self::assertNull($user->getAddedBy());
    }

    public function testSetAndGetStatus(): void
    {
        $user = new User();
        $user->setStatus(UserStatusEnum::ACTIVE);

        self::assertSame(UserStatusEnum::ACTIVE, $user->getStatus());
    }

    public function testDefaultStatus(): void
    {
        self::assertSame(UserStatusEnum::NEW, (new User())->getStatus());
    }

    public function testSetAndGetAvatar(): void
    {
        $user = new User();
        $user->setAvatar('https://example.com/avatar.png');

        self::assertSame('https://example.com/avatar.png', $user->getAvatar());
    }

    public function testSetNullAvatar(): void
    {
        $user = new User();
        $user->setAvatar(null);

        self::assertNull($user->getAvatar());
    }

    public function testAddUserHasGroup(): void
    {
        $user = new User();
        $uhg = new UserHasGroup();

        $user->addUserHasGroup($uhg);

        self::assertCount(1, $user->getUserHasGroups());
        self::assertSame($user, $uhg->getUser());
    }

    public function testAddUserHasGroupDoesNotDuplicate(): void
    {
        $user = new User();
        $uhg = new UserHasGroup();

        $user->addUserHasGroup($uhg);
        $user->addUserHasGroup($uhg);

        self::assertCount(1, $user->getUserHasGroups());
    }

    public function testRemoveUserHasGroup(): void
    {
        $user = new User();
        $uhg = new UserHasGroup();
        $uhg->setUser($user);
        $user->addUserHasGroup($uhg);

        $user->removeUserHasGroup($uhg);

        self::assertCount(0, $user->getUserHasGroups());
        self::assertNull($uhg->getUser());
    }

    public function testIsMemberOf(): void
    {
        $user = new User();
        $group = new Group();
        $uhg = new UserHasGroup();
        $uhg->setGroup($group);
        $user->addUserHasGroup($uhg);

        self::assertTrue($user->isMemberOf($group));
    }

    public function testIsNotMemberOfOtherGroup(): void
    {
        $user = new User();
        $group = new Group();
        $other = new Group();
        $uhg = new UserHasGroup();
        $uhg->setGroup($group);
        $user->addUserHasGroup($uhg);

        self::assertFalse($user->isMemberOf($other));
    }
}
