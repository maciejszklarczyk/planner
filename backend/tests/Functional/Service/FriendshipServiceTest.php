<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\Enum\FriendshipStatusEnum;
use App\Entity\FriendRequest;
use App\Entity\User;
use App\Exception\AlreadyFriendsException;
use App\Exception\CannotFriendSelfException;
use App\Exception\DuplicateFriendRequestException;
use App\Exception\FriendRequestCooldownActiveException;
use App\Exception\FriendRequestNotPendingException;
use App\Exception\UserNotFoundByEmailException;
use App\Repository\FriendRequestRepository;
use App\Repository\UserRepository;
use App\Service\FriendshipService;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;

#[CoversClass(FriendshipService::class)]
final class FriendshipServiceTest extends DatabaseTestCase
{
    private EntityManagerInterface $em;
    private FriendRequestRepository $friendRequestRepository;
    private UserRepository $userRepository;

    protected function setUp(): void
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

    private function service(?\DateTimeImmutable $now = null, int $cooldownDays = 3): FriendshipService
    {
        return new FriendshipService(
            em: $this->em,
            friendRequestRepository: $this->friendRequestRepository,
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

    public function testSendRequestRejectsSelfRequest(): void
    {
        $this->expectException(CannotFriendSelfException::class);

        $this->service()->sendRequest($this->user('user1@example.com'), 'user1@example.com');
    }

    public function testSendRequestRejectsUnknownEmail(): void
    {
        $this->expectException(UserNotFoundByEmailException::class);

        $this->service()->sendRequest($this->user('user1@example.com'), 'nobody@example.com');
    }

    public function testSendRequestCreatesPendingRow(): void
    {
        $request = $this->service()->sendRequest($this->user('user1@example.com'), 'user2@example.com');

        self::assertSame(FriendshipStatusEnum::PENDING, $request->getStatus());
        self::assertSame('user1@example.com', $request->getRequester()?->getEmail());
        self::assertSame('user2@example.com', $request->getAddressee()?->getEmail());
    }

    public function testSendRequestRejectsDuplicateSameDirection(): void
    {
        $service = $this->service();
        $service->sendRequest($this->user('user1@example.com'), 'user2@example.com');

        $this->expectException(DuplicateFriendRequestException::class);
        $service->sendRequest($this->user('user1@example.com'), 'user2@example.com');
    }

    public function testSendRequestAutoAcceptsCrossedRequest(): void
    {
        $service = $this->service();
        $original = $service->sendRequest($this->user('user1@example.com'), 'user2@example.com');

        $result = $service->sendRequest($this->user('user2@example.com'), 'user1@example.com');

        self::assertSame($original->getId(), $result->getId(), 'Crossed request must flip the existing row, not create a new one.');
        self::assertSame(FriendshipStatusEnum::ACCEPTED, $result->getStatus());
        self::assertCount(1, $this->friendRequestRepository->findAcceptedForUser($this->user('user1@example.com')->getId()));
    }

    public function testSendRequestRejectsAlreadyFriends(): void
    {
        $service = $this->service();
        $service->sendRequest($this->user('user1@example.com'), 'user2@example.com');
        $service->sendRequest($this->user('user2@example.com'), 'user1@example.com'); // crossed -> accepted

        $this->expectException(AlreadyFriendsException::class);
        $service->sendRequest($this->user('user1@example.com'), 'user2@example.com');
    }

    public function testSendRequestRejectsWhileCooldownActive(): void
    {
        $now = new \DateTimeImmutable();
        $declineService = $this->service($now);
        $request = $declineService->sendRequest($this->user('user1@example.com'), 'user2@example.com');
        $declineService->declineRequest($request);

        $resendService = $this->service($now->modify('+1 day'));

        $this->expectException(FriendRequestCooldownActiveException::class);
        $resendService->sendRequest($this->user('user1@example.com'), 'user2@example.com');
    }

    public function testSendRequestSucceedsAfterCooldownExpires(): void
    {
        $now = new \DateTimeImmutable();
        $declineService = $this->service($now);
        $request = $declineService->sendRequest($this->user('user1@example.com'), 'user2@example.com');
        $declineService->declineRequest($request);

        $resendService = $this->service($now->modify('+4 days'));
        $newRequest = $resendService->sendRequest($this->user('user1@example.com'), 'user2@example.com');

        self::assertSame(FriendshipStatusEnum::PENDING, $newRequest->getStatus());
        self::assertNotSame($request->getId(), $newRequest->getId());
    }

    public function testCancelDoesNotTriggerCooldownOnImmediateResend(): void
    {
        $service = $this->service();
        $request = $service->sendRequest($this->user('user1@example.com'), 'user2@example.com');
        $cancelled = $service->cancelRequest($request);

        self::assertSame(FriendshipStatusEnum::CANCELLED, $cancelled->getStatus());

        // No cooldown check should trigger — cancel is invisible to findLatestDeclinedBySender().
        $resent = $service->sendRequest($this->user('user1@example.com'), 'user2@example.com');
        self::assertSame(FriendshipStatusEnum::PENDING, $resent->getStatus());
    }

    public function testCancelOnNonPendingRequestIsRejected(): void
    {
        $service = $this->service();
        $request = $service->sendRequest($this->user('user1@example.com'), 'user2@example.com');
        $service->declineRequest($request);

        $this->expectException(FriendRequestNotPendingException::class);
        $service->cancelRequest($request);
    }

    public function testAcceptRequestSetsAcceptedAndRespondedAt(): void
    {
        $service = $this->service();
        $request = $service->sendRequest($this->user('user1@example.com'), 'user2@example.com');

        $accepted = $service->acceptRequest($request);

        self::assertSame(FriendshipStatusEnum::ACCEPTED, $accepted->getStatus());
        self::assertNotNull($accepted->getRespondedAt());
    }

    public function testAcceptOnNonPendingRequestIsRejected(): void
    {
        $service = $this->service();
        $request = $service->sendRequest($this->user('user1@example.com'), 'user2@example.com');
        $service->declineRequest($request);

        $this->expectException(FriendRequestNotPendingException::class);
        $service->acceptRequest($request);
    }

    public function testDeclineRequestSetsDeclinedAndRespondedAt(): void
    {
        $service = $this->service();
        $request = $service->sendRequest($this->user('user1@example.com'), 'user2@example.com');

        $declined = $service->declineRequest($request);

        self::assertSame(FriendshipStatusEnum::DECLINED, $declined->getStatus());
        self::assertNotNull($declined->getRespondedAt());
    }

    public function testListFriendsReturnsTheOtherSideOfEachAcceptedRow(): void
    {
        $service = $this->service();
        $service->sendRequest($this->user('user1@example.com'), 'user2@example.com');
        $service->sendRequest($this->user('user2@example.com'), 'user1@example.com'); // crossed -> accepted

        $friends = $service->listFriends($this->user('user1@example.com'));

        self::assertCount(1, $friends);
        self::assertSame('user2@example.com', $friends[0]->getEmail());
    }

    public function testListPendingSplitsIncomingAndOutgoing(): void
    {
        $service = $this->service();
        $service->sendRequest($this->user('user1@example.com'), 'user2@example.com');

        $pendingForSender = $service->listPending($this->user('user1@example.com'));
        self::assertCount(1, $pendingForSender['outgoing']);
        self::assertCount(0, $pendingForSender['incoming']);

        $pendingForRecipient = $service->listPending($this->user('user2@example.com'));
        self::assertCount(0, $pendingForRecipient['outgoing']);
        self::assertCount(1, $pendingForRecipient['incoming']);
    }
}
