<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Group;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[CoversClass(UserRepository::class)]
final class UserRepositoryTest extends DatabaseTestCase
{
    private EntityManagerInterface $em;
    private UserRepository $repository;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(UserRepository::class);
    }

    public function testFindWithPaginationReturnsUsers(): void
    {
        $users = $this->repository->findWithPagination();

        self::assertGreaterThanOrEqual(6, count($users));
        self::assertContainsOnlyInstancesOf(User::class, $users);
    }

    public function testFindWithPaginationSearchMatchesEmail(): void
    {
        $users = $this->repository->findWithPagination(search: 'user1@example.com');

        self::assertCount(1, $users);
        self::assertSame('user1@example.com', $users[0]->getEmail());
    }

    public function testFindWithPaginationSearchIsPartialMatch(): void
    {
        $users = $this->repository->findWithPagination(search: '@example.com');

        self::assertGreaterThanOrEqual(6, count($users));
    }

    public function testFindWithPaginationEmptySearchReturnsAll(): void
    {
        $all = $this->repository->findWithPagination();
        $empty = $this->repository->findWithPagination(search: '');

        self::assertCount(count($all), $empty);
    }

    public function testFindWithPaginationExcludesGroupMembers(): void
    {
        // group_1: admin=owner, user_1=member, user_2=member
        $group = $this->em->getRepository(Group::class)->findOneBy(['name' => 'Group 1']);

        $users = $this->repository->findWithPagination(excludeGroupId: $group->getId());
        $emails = array_map(fn (User $u) => $u->getEmail(), $users);

        self::assertNotContains('admin@example.com', $emails);
        self::assertNotContains('user1@example.com', $emails);
        self::assertNotContains('user2@example.com', $emails);
        self::assertContains('user3@example.com', $emails);
        self::assertContains('user4@example.com', $emails);
        self::assertContains('user5@example.com', $emails);
    }

    public function testFindWithPaginationLimitRespectsPageSize(): void
    {
        $users = $this->repository->findWithPagination(limit: 2);

        self::assertCount(2, $users);
    }

    public function testFindWithPaginationPageTwoReturnsDifferentUsers(): void
    {
        $page1 = $this->repository->findWithPagination(limit: 2);
        $page2 = $this->repository->findWithPagination(page: 2, limit: 2);

        self::assertCount(2, $page1);
        self::assertCount(2, $page2);
        self::assertNotSame($page1[0]->getId(), $page2[0]->getId());
    }

    public function testCountWithFiltersReturnsTotal(): void
    {
        $count = $this->repository->countWithFilters();

        self::assertGreaterThanOrEqual(6, $count);
    }

    public function testCountWithFiltersSearchMatchesEmail(): void
    {
        $count = $this->repository->countWithFilters(search: 'user1@example.com');

        self::assertSame(1, $count);
    }

    public function testCountWithFiltersEmptySearchMatchesAll(): void
    {
        $total = $this->repository->countWithFilters();
        $empty = $this->repository->countWithFilters(search: '');

        self::assertSame($total, $empty);
    }

    public function testCountWithFiltersExcludesGroupMembers(): void
    {
        // group_1 has 3 members: admin, user_1, user_2
        $group = $this->em->getRepository(Group::class)->findOneBy(['name' => 'Group 1']);

        $total = $this->repository->countWithFilters();
        $excluded = $this->repository->countWithFilters(excludeGroupId: $group->getId());

        self::assertSame($total - 3, $excluded);
    }

    public function testUpgradePasswordUpdatesPassword(): void
    {
        $user = new User();
        $user->setEmail('upgrade-temp@example.com');
        $this->em->persist($user);
        $this->em->flush();

        $this->repository->upgradePassword($user, 'new_hashed_password');

        $this->em->clear();
        $refreshed = $this->repository->findOneBy(['email' => 'upgrade-temp@example.com']);
        self::assertSame('new_hashed_password', $refreshed->getPassword());

        $this->em->remove($refreshed);
        $this->em->flush();
    }

    public function testUpgradePasswordThrowsForUnsupportedUser(): void
    {
        $nonUser = $this->createStub(PasswordAuthenticatedUserInterface::class);

        $this->expectException(UnsupportedUserException::class);

        $this->repository->upgradePassword($nonUser, 'any_password');
    }
}
