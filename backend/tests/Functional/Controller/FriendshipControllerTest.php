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
 * - user_5 -> friend_2: declined recently (cooldown active)
 * - admin -> user_3: declined long ago (cooldown expired)
 *
 * Every test below uses a pair not already consumed by fixtures or an earlier test in this
 * class, since DatabaseTestCase shares one DB across all methods in a class (declaration order).
 */
#[UsesClass(FriendshipController::class)]
final class FriendshipControllerTest extends DatabaseTestCase
{
    private string $lastResponseContent = '';

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

        $client->jsonRequest('POST', '/friend-requests', ['email' => 'friend4@example.com'], $this->devUser('friend2@example.com'));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('pending', $data['status']);
        self::assertSame('friend4@example.com', $data['otherUser']['email']);
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
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'friend2@example.com'], $this->devUser('user1@example.com'));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'friend2@example.com'], $this->devUser('user1@example.com'));

        self::assertResponseStatusCodeSame(409);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('DUPLICATE_FRIEND_REQUEST', $data['error']);
    }

    public function testSendFriendRequestAutoAcceptsCrossedRequest(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'friend4@example.com'], $this->devUser('friend3@example.com'));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $first = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'friend3@example.com'], $this->devUser('friend4@example.com'));

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

        $client->jsonRequest('POST', '/friend-requests', ['email' => 'friend2@example.com'], $this->devUser('user5@example.com'));

        self::assertResponseStatusCodeSame(429);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('FRIEND_REQUEST_COOLDOWN_ACTIVE', $data['error']);
    }

    public function testAcceptFriendRequestAsAddresseeSucceeds(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'friend3@example.com'], $this->devUser('user1@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/accept", [], [], $this->devUser('friend3@example.com'));

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('accepted', $data['status']);
    }

    public function testAcceptFriendRequestAsRequesterIsForbidden(): void
    {
        // The requester is a participant, just not the one allowed to accept — 403, not 404.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'friend2@example.com'], $this->devUser('admin@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/accept", [], [], $this->devUser('admin@example.com'));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAcceptFriendRequestAsNonParticipantReturns404(): void
    {
        // user_1 is neither side of the friend_3 -> user_5 request — treated as not-found, not forbidden,
        // so a non-participant can't distinguish "exists" from "doesn't exist" via 403 vs 404.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user5@example.com'], $this->devUser('friend3@example.com'));
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
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'friend4@example.com'], $this->devUser('admin@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/decline", [], [], $this->devUser('friend4@example.com'));

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('declined', $data['status']);
    }

    public function testAcceptOnNonPendingRequestReturns409(): void
    {
        // Fresh pair: friend_2 -> user_5, accepted once, then a second accept attempt must fail.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user5@example.com'], $this->devUser('friend2@example.com'));
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
        // Fresh pair: friend_4 -> user_1, declined once, then a second decline attempt must fail.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user1@example.com'], $this->devUser('friend4@example.com'));
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

    public function testDeclineFriendRequestAsRequesterIsForbidden(): void
    {
        // The requester is a participant, just not the one allowed to decline — 403, not 404.
        // Reuses the still-pending friend_2 -> friend_4 row (testSendFriendRequestCreatesPendingRow) by fetching
        // its id via listPending, rather than sending a fresh request: every undirected user pair in this
        // fixture set already has an active (pending/accepted) row by this point in the class (state is
        // cumulative across all methods — see the class docblock), so creating a brand-new pair here would
        // either collide with an existing active relationship or leave a stray pending row that pollutes a
        // later count-based assertion (e.g. testListPendingRequestsShowsIncomingAndOutgoing). A forbidden
        // decline attempt never changes the row's status, so reusing it here is side-effect-free.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('GET', '/friend-requests', [], $this->devUser('friend2@example.com'));
        $pending = json_decode($client->getResponse()->getContent(), true);
        $outgoingToUser4 = array_values(array_filter(
            $pending['outgoing'],
            fn (array $row): bool => 'friend4@example.com' === $row['otherUser']['email'],
        ));
        self::assertCount(1, $outgoingToUser4, 'Expected exactly one pending outgoing request from friend_2 to friend_4.');
        $requestId = $outgoingToUser4[0]['id'];

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$requestId}/decline", [], [], $this->devUser('friend2@example.com'));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeclineFriendRequestAsNonParticipantReturns404(): void
    {
        // friend_3 is neither side of this admin -> friend_2 request — treated as not-found, not forbidden.
        // Reuses the still-pending admin -> friend_2 row (testAcceptFriendRequestAsRequesterIsForbidden) by
        // fetching its id via listPending, for the same side-effect-free reuse reason as the test above —
        // a failed (404) decline attempt never changes the row's status.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('GET', '/friend-requests', [], $this->devUser('admin@example.com'));
        $pending = json_decode($client->getResponse()->getContent(), true);
        $outgoingToUser2 = array_values(array_filter(
            $pending['outgoing'],
            fn (array $row): bool => 'friend2@example.com' === $row['otherUser']['email'],
        ));
        self::assertCount(1, $outgoingToUser2, 'Expected exactly one pending outgoing request from admin to friend_2.');
        $requestId = $outgoingToUser2[0]['id'];

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$requestId}/decline", [], [], $this->devUser('friend3@example.com'));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
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
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user5@example.com'], $this->devUser('friend4@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/cancel", [], [], $this->devUser('user5@example.com'));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCancelOnNonPendingRequestReturns409(): void
    {
        // Fresh pair: friend_3 -> friend_2, cancelled once, then a second cancel attempt must fail.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'friend2@example.com'], $this->devUser('friend3@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/cancel", [], [], $this->devUser('friend3@example.com'));
        self::assertResponseIsSuccessful();

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/cancel", [], [], $this->devUser('friend3@example.com'));

        self::assertResponseStatusCodeSame(409);
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('FRIEND_REQUEST_NOT_PENDING', $body['error']);
    }

    public function testCancelFriendRequestAsNonParticipantReturns404(): void
    {
        // friend_4 is neither side of this fresh friend_3 -> friend_2 request (the prior attempt at this pair
        // from testCancelOnNonPendingRequestReturns409 is now cancelled/history, so it's free again) —
        // treated as not-found, not forbidden.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'friend2@example.com'], $this->devUser('friend3@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/cancel", [], [], $this->devUser('friend4@example.com'));

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
        // Fresh pair: friend_3 -> admin, sent then declined, then the clock is advanced past the
        // (test) 3-day cooldown window before re-sending.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'admin@example.com'], $this->devUser('friend3@example.com'));
        $request = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', "/friend-requests/{$request['id']}/decline", [], [], $this->devUser('admin@example.com'));
        self::assertResponseIsSuccessful();

        self::ensureKernelShutdown();
        $client = self::createClient();
        self::getContainer()->set(ClockInterface::class, new MockClock('+4 days'));
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'admin@example.com'], $this->devUser('friend3@example.com'));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED, 'Cooldown must have expired 4 days after a 3-day-configured decline.');
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('pending', $data['status']);
    }

    public function testListPendingRequestsShowsIncomingAndOutgoing(): void
    {
        // Reuses testSendFriendRequestCreatesPendingRow's still-pending pair (friend_2 -> friend_4).
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('GET', '/friend-requests', [], $this->devUser('friend2@example.com'));
        $outgoing = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $outgoing['outgoing']);
        self::assertSame('friend4@example.com', $outgoing['outgoing'][0]['otherUser']['email']);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('GET', '/friend-requests', [], $this->devUser('friend4@example.com'));
        $incoming = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $incoming['incoming']);
        self::assertSame('friend2@example.com', $incoming['incoming'][0]['otherUser']['email']);
    }

    public function testListFriendsShowsAcceptedPair(): void
    {
        // Reuses testSendFriendRequestAutoAcceptsCrossedRequest's now-accepted pair (friend_3 <-> friend_4).
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('GET', '/friends', [], $this->devUser('friend3@example.com'));

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $emails = array_column($data['data'], 'email');
        self::assertContains('friend4@example.com', $emails);
    }

    public function testListPendingRequestsNeverIncludesAnotherUsersUnrelatedRequest(): void
    {
        // friend_4 has no active relationship with admin or user_1 (their only history with either — the
        // admin -> friend_4 decline and the friend_4 -> user_1 decline — is inactive), so their pending list is a
        // clean vantage point to prove the admin <-> user_1 accepted friendship and the fixture user_1 -> user_5
        // pending request never leak in.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('GET', '/friend-requests', [], $this->devUser('friend4@example.com'));

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $emails = array_merge(
            array_column($data['incoming'], 'otherUser'),
            array_column($data['outgoing'], 'otherUser'),
        );
        $emails = array_column($emails, 'email');
        self::assertNotContains('admin@example.com', $emails);
        self::assertNotContains('user1@example.com', $emails);
    }

    public function testListFriendsNeverIncludesAnotherUsersUnrelatedFriendship(): void
    {
        // Same vantage point as above: friend_4 is friends with friend_3 (testSendFriendRequestAutoAcceptsCrossedRequest),
        // but has no relationship with admin or user_1, so their friends list must not surface the
        // admin <-> user_1 accepted friendship.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('GET', '/friends', [], $this->devUser('friend4@example.com'));

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $emails = array_column($data['data'], 'email');
        self::assertNotContains('admin@example.com', $emails);
        self::assertNotContains('user1@example.com', $emails);
    }

    public function testSendFriendRequestToBlockedUserReturnsSameNotFoundAsUnknownEmail(): void
    {
        // user_3 is blocked. The response must be indistinguishable from an unknown email,
        // so a caller cannot probe which emails belong to blocked accounts.
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'nobody@example.com'], $this->devUser('friend1@example.com'));
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $unknown = json_decode($client->getResponse()->getContent(), true);

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest('POST', '/friend-requests', ['email' => 'user3@example.com'], $this->devUser('friend1@example.com'));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $blocked = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('USER_NOT_FOUND', $blocked['error']);
        self::assertSame(
            str_replace('nobody@example.com', '<email>', $unknown['message']),
            str_replace('user3@example.com', '<email>', $blocked['message']),
        );
    }

    public function testPendingReverseRequestWinsOverSendersActiveCooldown(): void
    {
        // C1 (research.md): friend_5 is in cooldown towards friend_6, but friend_6 has since sent
        // friend_5 a request — friend_5 sending must accept it, not answer 429.
        $sent = $this->sendAs('friend5@example.com', 'friend6@example.com');
        $this->postAs('friend6@example.com', "/friend-requests/{$sent['id']}/decline");
        $reverse = $this->sendAs('friend6@example.com', 'friend5@example.com');
        self::assertSame('pending', $reverse['status']);

        $result = $this->sendAs('friend5@example.com', 'friend6@example.com');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('accepted', $result['status']);
        self::assertSame($reverse['id'], $result['id']);
    }

    public function testMostRecentDeclineDecidesTheCooldown(): void
    {
        // C4 (research.md): an old, expired decline must not hide a recent one.
        $first = $this->sendAs('friend7@example.com', 'friend8@example.com');
        $this->postAs('friend8@example.com', "/friend-requests/{$first['id']}/decline");

        $second = $this->sendAs('friend7@example.com', 'friend8@example.com', '+4 days');
        self::assertSame('pending', $second['status'], 'The first decline has expired after 4 days.');
        $this->postAs('friend8@example.com', "/friend-requests/{$second['id']}/decline", '+4 days');

        $this->sendAs('friend7@example.com', 'friend8@example.com', '+5 days');

        self::assertResponseStatusCodeSame(429);
        $data = json_decode($this->lastResponseContent, true);
        self::assertSame('FRIEND_REQUEST_COOLDOWN_ACTIVE', $data['error']);
    }

    /**
     * @return array<string, mixed>
     */
    private function sendAs(string $sender, string $addressee, ?string $clockOffset = null): array
    {
        $this->requestAs($sender, 'POST', '/friend-requests', ['email' => $addressee], $clockOffset);

        return json_decode($this->lastResponseContent, true);
    }

    private function postAs(string $user, string $uri, ?string $clockOffset = null): void
    {
        $this->requestAs($user, 'POST', $uri, [], $clockOffset);
        self::assertResponseIsSuccessful();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requestAs(string $user, string $method, string $uri, array $payload, ?string $clockOffset): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        if (null !== $clockOffset) {
            self::getContainer()->set(ClockInterface::class, new MockClock($clockOffset));
        }
        $client->jsonRequest($method, $uri, $payload, $this->devUser($user));
        $this->lastResponseContent = (string) $client->getResponse()->getContent();
    }
}
