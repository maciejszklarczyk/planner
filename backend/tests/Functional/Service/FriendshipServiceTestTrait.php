<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\FriendRequest;
use App\Entity\User;
use App\Repository\FriendRequestRepository;
use App\Repository\UserRepository;
use App\Service\FriendshipService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\MockClock;

/**
 * Shared harness for DB-backed FriendshipService tests: a clean friend_request table per test,
 * a service built with a controllable clock, and a view of the persisted rows.
 */
trait FriendshipServiceTestTrait
{
    private EntityManagerInterface $em;
    private FriendRequestRepository $friendRequestRepository;
    private UserRepository $userRepository;

    private function setUpFriendshipHarness(): void
    {
        self::ensureKernelShutdown();
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->friendRequestRepository = self::getContainer()->get(FriendRequestRepository::class);
        $this->userRepository = self::getContainer()->get(UserRepository::class);

        // Start each test from a clean slate of FriendRequest rows (fixtures seed some for other suites).
        $this->em->createQuery('DELETE FROM App\Entity\FriendRequest')->execute();
        $this->em->clear();
    }

    private function service(
        ?\DateTimeImmutable $now = null,
        int $cooldownDays = 3,
        ?FriendRequestRepository $friendRequestRepository = null,
    ): FriendshipService {
        return new FriendshipService(
            em: $this->em,
            friendRequestRepository: $friendRequestRepository ?? $this->friendRequestRepository,
            userRepository: $this->userRepository,
            clock: new MockClock($now ?? new \DateTimeImmutable()),
            cooldownDays: $cooldownDays,
        );
    }

    private function user(string $email): User
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);
        self::assertNotNull($user, "Fixture user {$email} not found.");

        return $user;
    }

    /**
     * Every persisted friend_request row, read fresh from the database.
     *
     * @return list<array{id: int|null, from: string|null, to: string|null, status: string}>
     */
    private function persistedRows(): array
    {
        $this->em->clear();

        return array_map(
            fn (FriendRequest $fr): array => [
                'id' => $fr->getId(),
                'from' => $fr->getRequester()?->getEmail(),
                'to' => $fr->getAddressee()?->getEmail(),
                'status' => $fr->getStatus()->value,
            ],
            $this->friendRequestRepository->findBy([], ['id' => 'ASC']),
        );
    }
}
