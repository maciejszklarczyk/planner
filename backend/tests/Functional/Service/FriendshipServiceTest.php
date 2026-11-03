<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\Enum\FriendshipStatusEnum;
use App\Entity\Enum\UserStatusEnum;
use App\Entity\User;
use App\Exception\AlreadyFriendsException;
use App\Exception\CannotFriendSelfException;
use App\Exception\DuplicateFriendRequestException;
use App\Exception\FriendRequestCooldownActiveException;
use App\Exception\FriendRequestNotPendingException;
use App\Exception\UserNotFoundByEmailException;
use App\Service\FriendshipService;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(FriendshipService::class)]
final class FriendshipServiceTest extends DatabaseTestCase
{
    use FriendshipServiceTestTrait;

    protected function setUp(): void
    {
        $this->setUpFriendshipHarness();
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function hiddenAddressees(): iterable
    {
        yield 'inactive' => ['user2@example.com'];
        yield 'blocked' => ['user3@example.com'];
        yield 'deleted status' => ['user4@example.com'];
    }

    #[DataProvider('hiddenAddressees')]
    public function testSendRequestToHiddenUserLooksLikeUnknownEmailAndCreatesNothing(string $hiddenEmail): void
    {
        $requester = $this->user('user1@example.com');

        $hiddenMessage = $this->sendRequestErrorMessage($requester, $hiddenEmail);
        $unknownMessage = $this->sendRequestErrorMessage($requester, 'nobody@example.com');

        // The response must not reveal that the account exists: same message shape as an unknown email.
        self::assertSame(
            str_replace('nobody@example.com', '<email>', $unknownMessage),
            str_replace($hiddenEmail, '<email>', $hiddenMessage),
        );
        self::assertSame(0, $this->friendRequestRepository->count([]), 'A rejected send must not persist a row.');
    }

    public function testCrossedRequestFromUserHiddenAfterSendingIsNotAutoAccepted(): void
    {
        // user_5 sends to user_1, then user_5's account gets blocked. user_1 "sending back" must not
        // turn the stale pending request into a friendship with a blocked account.
        $service = $this->service();
        $pending = $service->sendRequest($this->user('user5@example.com'), 'user1@example.com');

        $this->setStatus('user5@example.com', UserStatusEnum::BLOCKED);

        try {
            try {
                $service->sendRequest($this->user('user1@example.com'), 'user5@example.com');
                self::fail('Sending to a blocked user must be rejected.');
            } catch (UserNotFoundByEmailException) {
            }

            $this->em->clear();
            $rows = $this->friendRequestRepository->findAll();
            self::assertCount(1, $rows);
            self::assertSame($pending->getId(), $rows[0]->getId());
            self::assertSame(FriendshipStatusEnum::PENDING, $rows[0]->getStatus());
        } finally {
            // The test DB is shared by every test in the run — restore the fixture status.
            $this->setStatus('user5@example.com', UserStatusEnum::NEW);
        }
    }

    private function setStatus(string $email, UserStatusEnum $status): void
    {
        $this->user($email)->setStatus($status);
        $this->em->flush();
    }

    private function sendRequestErrorMessage(User $requester, string $email): string
    {
        try {
            $this->service()->sendRequest($requester, $email);
        } catch (UserNotFoundByEmailException $e) {
            return $e->getMessage();
        }

        self::fail("Expected UserNotFoundByEmailException for {$email}.");
    }

    public function testSendRequestCreatesPendingRow(): void
    {
        $request = $this->service()->sendRequest($this->user('user1@example.com'), 'user5@example.com');

        self::assertSame(FriendshipStatusEnum::PENDING, $request->getStatus());
        self::assertSame('user1@example.com', $request->getRequester()?->getEmail());
        self::assertSame('user5@example.com', $request->getAddressee()?->getEmail());
    }

    public function testSendRequestRejectsDuplicateSameDirection(): void
    {
        $service = $this->service();
        $service->sendRequest($this->user('user1@example.com'), 'user5@example.com');

        $this->expectException(DuplicateFriendRequestException::class);
        $service->sendRequest($this->user('user1@example.com'), 'user5@example.com');
    }

    public function testSendRequestAutoAcceptsCrossedRequest(): void
    {
        $service = $this->service();
        $original = $service->sendRequest($this->user('user1@example.com'), 'user5@example.com');

        $result = $service->sendRequest($this->user('user5@example.com'), 'user1@example.com');

        self::assertSame($original->getId(), $result->getId(), 'Crossed request must flip the existing row, not create a new one.');
        self::assertSame(FriendshipStatusEnum::ACCEPTED, $result->getStatus());
        self::assertCount(1, $this->friendRequestRepository->findAcceptedForUser($this->user('user1@example.com')->getId()));
    }

    public function testSendRequestRejectsAlreadyFriends(): void
    {
        $service = $this->service();
        $service->sendRequest($this->user('user1@example.com'), 'user5@example.com');
        $service->sendRequest($this->user('user5@example.com'), 'user1@example.com'); // crossed -> accepted

        $this->expectException(AlreadyFriendsException::class);
        $service->sendRequest($this->user('user1@example.com'), 'user5@example.com');
    }

    public function testSendRequestRejectsWhileCooldownActive(): void
    {
        $now = new \DateTimeImmutable();
        $declineService = $this->service($now);
        $request = $declineService->sendRequest($this->user('user1@example.com'), 'user5@example.com');
        $declineService->declineRequest($request);

        $resendService = $this->service($now->modify('+1 day'));

        $this->expectException(FriendRequestCooldownActiveException::class);
        $resendService->sendRequest($this->user('user1@example.com'), 'user5@example.com');
    }

    public function testSendRequestSucceedsAfterCooldownExpires(): void
    {
        $now = new \DateTimeImmutable();
        $declineService = $this->service($now);
        $request = $declineService->sendRequest($this->user('user1@example.com'), 'user5@example.com');
        $declineService->declineRequest($request);

        $resendService = $this->service($now->modify('+4 days'));
        $newRequest = $resendService->sendRequest($this->user('user1@example.com'), 'user5@example.com');

        self::assertSame(FriendshipStatusEnum::PENDING, $newRequest->getStatus());
        self::assertNotSame($request->getId(), $newRequest->getId());
    }

    public function testCancelDoesNotTriggerCooldownOnImmediateResend(): void
    {
        $service = $this->service();
        $request = $service->sendRequest($this->user('user1@example.com'), 'user5@example.com');
        $cancelled = $service->cancelRequest($request);

        self::assertSame(FriendshipStatusEnum::CANCELLED, $cancelled->getStatus());

        // No cooldown check should trigger — cancel is invisible to findLatestDeclinedBySender().
        $resent = $service->sendRequest($this->user('user1@example.com'), 'user5@example.com');
        self::assertSame(FriendshipStatusEnum::PENDING, $resent->getStatus());
    }

    public function testCancelOnNonPendingRequestIsRejected(): void
    {
        $service = $this->service();
        $request = $service->sendRequest($this->user('user1@example.com'), 'user5@example.com');
        $service->declineRequest($request);

        $this->expectException(FriendRequestNotPendingException::class);
        $service->cancelRequest($request);
    }

    public function testAcceptRequestSetsAcceptedAndRespondedAt(): void
    {
        $service = $this->service();
        $request = $service->sendRequest($this->user('user1@example.com'), 'user5@example.com');

        $accepted = $service->acceptRequest($request);

        self::assertSame(FriendshipStatusEnum::ACCEPTED, $accepted->getStatus());
        self::assertNotNull($accepted->getRespondedAt());
    }

    public function testAcceptOnNonPendingRequestIsRejected(): void
    {
        $service = $this->service();
        $request = $service->sendRequest($this->user('user1@example.com'), 'user5@example.com');
        $service->declineRequest($request);

        $this->expectException(FriendRequestNotPendingException::class);
        $service->acceptRequest($request);
    }

    public function testDeclineRequestSetsDeclinedAndRespondedAt(): void
    {
        $service = $this->service();
        $request = $service->sendRequest($this->user('user1@example.com'), 'user5@example.com');

        $declined = $service->declineRequest($request);

        self::assertSame(FriendshipStatusEnum::DECLINED, $declined->getStatus());
        self::assertNotNull($declined->getRespondedAt());
    }

    public function testListFriendsReturnsTheOtherSideOfEachAcceptedRow(): void
    {
        $service = $this->service();
        $service->sendRequest($this->user('user1@example.com'), 'user5@example.com');
        $service->sendRequest($this->user('user5@example.com'), 'user1@example.com'); // crossed -> accepted

        $friends = $service->listFriends($this->user('user1@example.com'));

        self::assertCount(1, $friends);
        self::assertSame('user5@example.com', $friends[0]->getEmail());
    }

    public function testListPendingSplitsIncomingAndOutgoing(): void
    {
        $service = $this->service();
        $service->sendRequest($this->user('user1@example.com'), 'user5@example.com');

        $pendingForSender = $service->listPending($this->user('user1@example.com'));
        self::assertCount(1, $pendingForSender['outgoing']);
        self::assertCount(0, $pendingForSender['incoming']);

        $pendingForRecipient = $service->listPending($this->user('user5@example.com'));
        self::assertCount(0, $pendingForRecipient['outgoing']);
        self::assertCount(1, $pendingForRecipient['incoming']);
    }
}
