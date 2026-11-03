<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\Enum\FriendshipStatusEnum;
use App\Entity\FriendRequest;
use App\Exception\AlreadyFriendsException;
use App\Exception\CannotFriendSelfException;
use App\Exception\DuplicateFriendRequestException;
use App\Exception\FriendRequestCooldownActiveException;
use App\Exception\UserNotFoundByEmailException;
use App\Service\FriendshipService;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Overlap cases of the send rule chain: two rules meeting on one user pair.
 *
 * Expected outcomes come from the business rules, not from FriendshipService:
 * - context/archive/2026-07-22-friendship-requests/plan.md "Critical Implementation Details":
 *   check order self -> active row -> cooldown -> create; declined and cancelled rows are history;
 *   crossed auto-accept applies only to a *pending* reverse row.
 * - PRD FR-006: declining starts a cooldown before the same sender can re-send to the same recipient.
 *
 * Case ids (C1-C9) match context/changes/testing-business-rule-correctness/research.md.
 */
#[CoversClass(FriendshipService::class)]
final class FriendshipSendRuleChainTest extends DatabaseTestCase
{
    use FriendshipServiceTestTrait;

    private const A = 'user1@example.com';
    private const B = 'user5@example.com';
    private const COOLDOWN_DAYS = 3;

    private \DateTimeImmutable $t0;

    protected function setUp(): void
    {
        $this->setUpFriendshipHarness();
        // Whole seconds: the DB stores timestamps without microseconds.
        $this->t0 = new \DateTimeImmutable('2026-06-01 12:00:00');
    }

    /**
     * Guards: cooldown check moved before the active-row check.
     */
    public function testC1PendingReverseRequestWinsOverSendersActiveCooldown(): void
    {
        $this->sendAndDecline(self::A, self::B, $this->t0);
        $reverse = $this->at('+1 day')->sendRequest($this->user(self::B), self::A);

        $result = $this->at('+1 day')->sendRequest($this->user(self::A), self::B);

        self::assertSame($reverse->getId(), $result->getId(), 'Must accept the pending B->A row, not create a new one.');
        self::assertSame([
            ['from' => self::A, 'to' => self::B, 'status' => 'declined'],
            ['from' => self::B, 'to' => self::A, 'status' => 'accepted'],
        ], $this->rowsWithoutIds());
    }

    /**
     * Guards: cooldown lookup made direction-agnostic.
     */
    public function testC2CooldownOnlyBlocksTheDeclinedSender(): void
    {
        $this->sendAndDecline(self::A, self::B, $this->t0);

        $reverse = $this->at('+1 day')->sendRequest($this->user(self::B), self::A);

        self::assertSame(FriendshipStatusEnum::PENDING, $reverse->getStatus());
        self::assertSame([
            ['from' => self::A, 'to' => self::B, 'status' => 'declined'],
            ['from' => self::B, 'to' => self::A, 'status' => 'pending'],
        ], $this->rowsWithoutIds());
    }

    /**
     * Guards: strict "<" turned into "<=", or a unit drift in the cooldown arithmetic.
     */
    public function testC3CooldownStillActiveOneSecondBeforeItEnds(): void
    {
        $this->sendAndDecline(self::A, self::B, $this->t0);
        $justBeforeEnd = $this->t0->modify('+'.self::COOLDOWN_DAYS.' days')->modify('-1 second');

        $this->expectException(FriendRequestCooldownActiveException::class);
        $this->service($justBeforeEnd, self::COOLDOWN_DAYS)->sendRequest($this->user(self::A), self::B);
    }

    public function testC3CooldownOverExactlyWhenTheWindowEnds(): void
    {
        $this->sendAndDecline(self::A, self::B, $this->t0);
        $end = $this->t0->modify('+'.self::COOLDOWN_DAYS.' days');

        $resent = $this->service($end, self::COOLDOWN_DAYS)->sendRequest($this->user(self::A), self::B);

        self::assertSame(FriendshipStatusEnum::PENDING, $resent->getStatus());
    }

    /**
     * Guards: "latest declined" ordering flipped or dropped — an old, expired decline must not
     * hide a recent one.
     */
    public function testC4MostRecentDeclineDecidesTheCooldown(): void
    {
        $this->sendAndDecline(self::A, self::B, $this->t0);
        $this->sendAndDecline(self::A, self::B, $this->t0->modify('+4 days'));

        $this->expectException(FriendRequestCooldownActiveException::class);
        $this->at('+5 days')->sendRequest($this->user(self::A), self::B);
    }

    /**
     * Guards: active-row lookup widened to declined rows (declined is history, not a crossed request).
     */
    public function testC5DeclinedReverseRequestIsHistoryNotACrossedRequest(): void
    {
        $this->sendAndDecline(self::B, self::A, $this->t0);

        $sent = $this->at('+1 hour')->sendRequest($this->user(self::A), self::B);

        self::assertSame(FriendshipStatusEnum::PENDING, $sent->getStatus());
        self::assertSame([
            ['from' => self::B, 'to' => self::A, 'status' => 'declined'],
            ['from' => self::A, 'to' => self::B, 'status' => 'pending'],
        ], $this->rowsWithoutIds());
    }

    /**
     * Guards: active-row lookup widened to cancelled rows.
     */
    public function testC6CancelledReverseRequestIsHistoryNotACrossedRequest(): void
    {
        $service = $this->service($this->t0, self::COOLDOWN_DAYS);
        $service->cancelRequest($service->sendRequest($this->user(self::B), self::A));

        $sent = $this->at('+1 hour')->sendRequest($this->user(self::A), self::B);

        self::assertSame(FriendshipStatusEnum::PENDING, $sent->getStatus());
        self::assertSame([
            ['from' => self::B, 'to' => self::A, 'status' => 'cancelled'],
            ['from' => self::A, 'to' => self::B, 'status' => 'pending'],
        ], $this->rowsWithoutIds());
    }

    /**
     * Guards: accepted check dropped or moved after the cooldown/create steps.
     */
    public function testC7FriendsAfterAnEarlierDeclineAreStillAlreadyFriends(): void
    {
        $this->sendAndDecline(self::A, self::B, $this->t0);
        $later = $this->at('+4 days');
        $later->acceptRequest($later->sendRequest($this->user(self::A), self::B));

        $this->expectException(AlreadyFriendsException::class);
        $this->at('+4 days')->sendRequest($this->user(self::A), self::B);
    }

    /**
     * @return iterable<string, array{string, class-string<\Throwable>}>
     */
    public static function rejectedSends(): iterable
    {
        yield 'self request' => ['self', CannotFriendSelfException::class];
        yield 'same-direction duplicate' => ['duplicate', DuplicateFriendRequestException::class];
        yield 'already friends' => ['friends', AlreadyFriendsException::class];
        yield 'cooldown active' => ['cooldown', FriendRequestCooldownActiveException::class];
        yield 'hidden addressee' => ['hidden', UserNotFoundByEmailException::class];
    }

    /**
     * Guards: a partial write (persist/flip) before a rule throws.
     *
     * @param class-string<\Throwable> $expected
     */
    #[DataProvider('rejectedSends')]
    public function testC8RejectedSendLeavesPersistedStateUnchanged(string $scenario, string $expected): void
    {
        $setup = $this->service($this->t0, self::COOLDOWN_DAYS);
        match ($scenario) {
            'duplicate' => $setup->sendRequest($this->user(self::A), self::B),
            'friends' => $setup->acceptRequest($setup->sendRequest($this->user(self::A), self::B)),
            'cooldown' => $this->sendAndDecline(self::A, self::B, $this->t0),
            default => $setup->sendRequest($this->user(self::B), self::A), // unrelated pending row
        };
        $before = $this->persistedRows();
        $addressee = match ($scenario) {
            'self' => self::A,
            'hidden' => 'user3@example.com',
            default => self::B,
        };

        try {
            $this->at('+1 hour')->sendRequest($this->user(self::A), $addressee);
            self::fail("Expected {$expected}.");
        } catch (\Throwable $e) {
            self::assertInstanceOf($expected, $e);
        }

        self::assertSame($before, $this->persistedRows());
    }

    /**
     * Guards: a half-applied crossed-request flip.
     */
    public function testC9CrossedAutoAcceptIsFullyApplied(): void
    {
        $this->service($this->t0, self::COOLDOWN_DAYS)->sendRequest($this->user(self::A), self::B);
        $acceptedAt = $this->t0->modify('+1 hour');

        $this->service($acceptedAt, self::COOLDOWN_DAYS)->sendRequest($this->user(self::B), self::A);

        $this->em->clear();
        $rows = $this->friendRequestRepository->findAll();
        self::assertCount(1, $rows);
        self::assertSame(FriendshipStatusEnum::ACCEPTED, $rows[0]->getStatus());
        self::assertEquals($acceptedAt, $rows[0]->getRespondedAt());

        foreach ([self::A, self::B] as $email) {
            $pending = $this->service()->listPending($this->user($email));
            self::assertSame([], $pending['incoming'], "{$email} must have no incoming pending request.");
            self::assertSame([], $pending['outgoing'], "{$email} must have no outgoing pending request.");
        }
    }

    private function at(string $offsetFromT0): FriendshipService
    {
        return $this->service($this->t0->modify($offsetFromT0), self::COOLDOWN_DAYS);
    }

    /**
     * $from sends to $to and $to declines, both at $when.
     */
    private function sendAndDecline(string $from, string $to, \DateTimeImmutable $when): FriendRequest
    {
        $service = $this->service($when, self::COOLDOWN_DAYS);

        return $service->declineRequest($service->sendRequest($this->user($from), $to));
    }

    /**
     * @return list<array{from: string|null, to: string|null, status: string}>
     */
    private function rowsWithoutIds(): array
    {
        return array_map(
            fn (array $row): array => ['from' => $row['from'], 'to' => $row['to'], 'status' => $row['status']],
            $this->persistedRows(),
        );
    }
}
