<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\FriendshipController;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fixture data (deterministic) relevant here:
 * - admin <-> user_1: accepted friendship
 * - user_1 -> user_5: pending
 * - user_5 -> user_2: declined recently (cooldown active)
 * - admin -> user_3: declined long ago (cooldown expired)
 *
 * Every test below uses a pair not already consumed by fixtures or an earlier test in this
 * class, since DatabaseTestCase shares one DB across all methods in a class (declaration order).
 */
#[UsesClass(FriendshipController::class)]
final class FriendshipControllerTest extends DatabaseTestCase
{
    /**
     * @return array<string, string>
     */
    private function devUser(string $email): array
    {
        return ['HTTP_X_DEV_USER' => $email];
    }

    public function testSendFriendRequestCreatesPendingRow(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();

        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user4@example.com'], $this->devUser('user2@example.com'));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('pending', $data['status']);
        self::assertSame('user4@example.com', $data['otherUser']['email']);
    }

    public function testSendFriendRequestRejectsSelfRequest(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();

        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user1@example.com'], $this->devUser('user1@example.com'));

        self::assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('CANNOT_FRIEND_SELF', $data['error']);
    }

    public function testSendFriendRequestRejectsDuplicateSameDirection(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user2@example.com'], $this->devUser('user1@example.com'));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user2@example.com'], $this->devUser('user1@example.com'));

        self::assertResponseStatusCodeSame(409);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('DUPLICATE_FRIEND_REQUEST', $data['error']);
    }

    public function testSendFriendRequestAutoAcceptsCrossedRequest(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user4@example.com'], $this->devUser('user3@example.com'));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $first = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user3@example.com'], $this->devUser('user4@example.com'));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $second = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('accepted', $second['status']);
        self::assertSame($first['id'], $second['id'], 'Crossed request must flip the existing row, not create a new one.');
    }

    public function testSendFriendRequestRejectsAlreadyFriends(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();

        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user1@example.com'], $this->devUser('admin@example.com'));

        self::assertResponseStatusCodeSame(409);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('ALREADY_FRIENDS', $data['error']);
    }

    public function testSendFriendRequestRejectsWhileCooldownActive(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();

        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user2@example.com'], $this->devUser('user5@example.com'));

        self::assertResponseStatusCodeSame(429);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('FRIEND_REQUEST_COOLDOWN_ACTIVE', $data['error']);
    }

    public function testAcceptFriendRequestAsAddresseeSucceeds(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user3@example.com'], $this->devUser('user1@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/accept", [], [], $this->devUser('user3@example.com'));

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('accepted', $data['status']);
    }

    public function testAcceptFriendRequestAsRequesterIsForbidden(): void
    {
        // The requester is a participant, just not the one allowed to accept — 403, not 404.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user2@example.com'], $this->devUser('admin@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/accept", [], [], $this->devUser('admin@example.com'));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAcceptFriendRequestAsNonParticipantReturns404(): void
    {
        // user_1 is neither side of the user_3 -> user_5 request — treated as not-found, not forbidden,
        // so a non-participant can't distinguish "exists" from "doesn't exist" via 403 vs 404.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user5@example.com'], $this->devUser('user3@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/accept", [], [], $this->devUser('user1@example.com'));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testDeclineFriendRequestAsAddresseeSucceeds(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user4@example.com'], $this->devUser('admin@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/decline", [], [], $this->devUser('user4@example.com'));

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('declined', $data['status']);
    }

    public function testAcceptOnNonPendingRequestReturns409(): void
    {
        // Fresh pair: user_2 -> user_5, accepted once, then a second accept attempt must fail.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user5@example.com'], $this->devUser('user2@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/accept", [], [], $this->devUser('user5@example.com'));
        self::assertResponseIsSuccessful();

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/accept", [], [], $this->devUser('user5@example.com'));

        self::assertResponseStatusCodeSame(409);
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('FRIEND_REQUEST_NOT_PENDING', $body['error']);
    }

    public function testDeclineOnNonPendingRequestReturns409(): void
    {
        // Fresh pair: user_4 -> user_1, declined once, then a second decline attempt must fail.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user1@example.com'], $this->devUser('user4@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/decline", [], [], $this->devUser('user1@example.com'));
        self::assertResponseIsSuccessful();

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/decline", [], [], $this->devUser('user1@example.com'));

        self::assertResponseStatusCodeSame(409);
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('FRIEND_REQUEST_NOT_PENDING', $body['error']);
    }

    public function testCancelFriendRequestAsRequesterSucceeds(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user5@example.com'], $this->devUser('admin@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/cancel", [], [], $this->devUser('admin@example.com'));

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('cancelled', $data['status']);
    }

    public function testCancelFriendRequestAsAddresseeIsForbidden(): void
    {
        // The addressee is a participant, just not the one allowed to cancel — 403, not 404.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user5@example.com'], $this->devUser('user4@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/cancel", [], [], $this->devUser('user5@example.com'));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCancelOnNonPendingRequestReturns409(): void
    {
        // Fresh pair: user_3 -> user_2, cancelled once, then a second cancel attempt must fail.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user2@example.com'], $this->devUser('user3@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/cancel", [], [], $this->devUser('user3@example.com'));
        self::assertResponseIsSuccessful();

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/cancel", [], [], $this->devUser('user3@example.com'));

        self::assertResponseStatusCodeSame(409);
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('FRIEND_REQUEST_NOT_PENDING', $body['error']);
    }

    public function testCancelFriendRequestAsNonParticipantReturns404(): void
    {
        // user_4 is neither side of this fresh user_3 -> user_2 request (the prior attempt at this pair
        // from testCancelOnNonPendingRequestReturns409 is now cancelled/history, so it's free again) —
        // treated as not-found, not forbidden.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user2@example.com'], $this->devUser('user3@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/cancel", [], [], $this->devUser('user4@example.com'));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCancelledRequestDoesNotTriggerCooldownOnResend(): void
    {
        // Reuses the already-cancelled pair from testCancelFriendRequestAsRequesterSucceeds (admin -> user_5),
        // re-sending immediately after cancellation. Cancelling must not be mistaken for a decline by the
        // cooldown check.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user5@example.com'], $this->devUser('admin@example.com'));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED, 'A cancelled request must not block an immediate resend via the decline cooldown.');
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('pending', $data['status']);
    }

    public function testCooldownExpiresAfterConfiguredDaysViaMockClock(): void
    {
        // Fresh pair: user_3 -> admin, sent then declined, then the clock is advanced past the
        // (test) 3-day cooldown window before re-sending.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'admin@example.com'], $this->devUser('user3@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/decline", [], [], $this->devUser('admin@example.com'));
        self::assertResponseIsSuccessful();

        self::ensureKernelShutdown();
        $client = self::createClient();
        self::getContainer()->set(ClockInterface::class, new MockClock('+4 days'));
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'admin@example.com'], $this->devUser('user3@example.com'));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED, 'Cooldown must have expired 4 days after a 3-day-configured decline.');
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('pending', $data['status']);
    }

    public function testListPendingRequestsShowsIncomingAndOutgoing(): void
    {
        // Reuses testSendFriendRequestCreatesPendingRow's still-pending pair (user_2 -> user_4).
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('GET', '/friend-requests', [], $this->devUser('user2@example.com'));
        $outgoing = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $outgoing['outgoing']);
        self::assertSame('user4@example.com', $outgoing['outgoing'][0]['otherUser']['email']);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('GET', '/friend-requests', [], $this->devUser('user4@example.com'));
        $incoming = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $incoming['incoming']);
        self::assertSame('user2@example.com', $incoming['incoming'][0]['otherUser']['email']);
    }

    public function testListFriendsShowsAcceptedPair(): void
    {
        // Reuses testSendFriendRequestAutoAcceptsCrossedRequest's now-accepted pair (user_3 <-> user_4).
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('GET', '/friends', [], $this->devUser('user3@example.com'));

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $emails = array_column($data['data'], 'email');
        self::assertContains('user4@example.com', $emails);
    }
}
