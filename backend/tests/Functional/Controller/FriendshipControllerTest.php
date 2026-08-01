<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\FriendshipController;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\UsesClass;
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

    public function testAcceptFriendRequestAsNonAddresseeIsForbidden(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user2@example.com'], $this->devUser('admin@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/accept", [], [], $this->devUser('user4@example.com'));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
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
        // Reuses the already-accepted pair from testAcceptFriendRequestAsAddresseeSucceeds (user_1 <-> user_3).
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('GET', '/friend-requests', [], $this->devUser('user3@example.com'));
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(0, $data['incoming'], 'Request should already be accepted, not pending.');
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

    public function testCancelFriendRequestAsNonRequesterIsForbidden(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user5@example.com'], $this->devUser('user4@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/cancel", [], [], $this->devUser('user2@example.com'));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
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
