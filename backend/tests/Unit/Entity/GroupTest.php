<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Enum\UserGroupRoleEnum;
use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserHasGroup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Group::class)]
final class GroupTest extends TestCase
{
    public function testIdIsNullByDefault(): void
    {
        self::assertNull((new Group())->getId());
    }

    public function testConstructorInitializesEmptyCollection(): void
    {
        self::assertCount(0, (new Group())->getUserHasGroups());
    }

    public function testSetAndGetName(): void
    {
        $group = new Group();
        $group->setName('My Group');

        self::assertSame('My Group', $group->getName());
    }

    public function testSetAndGetDescription(): void
    {
        $group = new Group();
        $group->setDescription('A description');

        self::assertSame('A description', $group->getDescription());
    }

    public function testSetNullDescription(): void
    {
        $group = new Group();
        $group->setDescription(null);

        self::assertNull($group->getDescription());
    }

    public function testAddUserHasGroup(): void
    {
        $group = new Group();
        $uhg = new UserHasGroup();

        $group->addUserHasGroup($uhg);

        self::assertCount(1, $group->getUserHasGroups());
        self::assertSame($group, $uhg->getGroup());
    }

    public function testAddUserHasGroupDoesNotDuplicate(): void
    {
        $group = new Group();
        $uhg = new UserHasGroup();

        $group->addUserHasGroup($uhg);
        $group->addUserHasGroup($uhg);

        self::assertCount(1, $group->getUserHasGroups());
    }

    public function testRemoveUserHasGroup(): void
    {
        $group = new Group();
        $uhg = new UserHasGroup();
        $uhg->setGroup($group);
        $group->addUserHasGroup($uhg);

        $group->removeUserHasGroup($uhg);

        self::assertCount(0, $group->getUserHasGroups());
        self::assertNull($uhg->getGroup());
    }

    public function testGetGroupOwnerUserReturnsOwner(): void
    {
        $group = new Group();
        $user = new User();
        $uhg = new UserHasGroup();
        $uhg->setRole(UserGroupRoleEnum::OWNER);
        $uhg->setUser($user);
        $group->addUserHasGroup($uhg);

        self::assertSame($user, $group->getGroupOwnerUser());
    }

    public function testGetGroupOwnerUserReturnsNullWhenNoOwner(): void
    {
        $group = new Group();
        $uhg = new UserHasGroup();
        $uhg->setRole(UserGroupRoleEnum::MEMBER);
        $group->addUserHasGroup($uhg);

        self::assertNull($group->getGroupOwnerUser());
    }

    public function testGetGroupOwnerUserReturnsNullWhenEmpty(): void
    {
        self::assertNull((new Group())->getGroupOwnerUser());
    }
}
