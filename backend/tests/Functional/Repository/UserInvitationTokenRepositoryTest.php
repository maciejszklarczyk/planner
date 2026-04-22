<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\UserInvitationToken;
use App\Repository\UserInvitationTokenRepository;
use App\Tests\DatabaseTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UserInvitationTokenRepository::class)]
final class UserInvitationTokenRepositoryTest extends DatabaseTestCase
{
    private EntityManagerInterface $em;
    private UserInvitationTokenRepository $repository;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(UserInvitationTokenRepository::class);

        $this->em->createQuery('DELETE FROM App\Entity\UserInvitationToken t')->execute();
        $this->em->clear();
    }

    private function persistToken(
        string $email,
        string $token,
        ?DateTimeImmutable $expiresAt = null,
        ?DateTimeImmutable $usedAt = null,
    ): void {
        $entity = new UserInvitationToken($token, $email);

        if ($expiresAt !== null) {
            $entity->setExpiresAt($expiresAt);
        }

        if ($usedAt !== null) {
            $entity->setUsedAt($usedAt);
        }

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function testFindActiveByEmailReturnsActiveToken(): void
    {
        $this->persistToken('invited@example.com', 'active_token');

        $result = $this->repository->findActiveByEmail('invited@example.com');

        self::assertCount(1, $result);
        self::assertSame('active_token', $result[0]->getToken());
    }

    public function testFindActiveByEmailReturnsMultipleActiveTokens(): void
    {
        $this->persistToken('invited@example.com', 'token_1');
        $this->persistToken('invited@example.com', 'token_2');

        $result = $this->repository->findActiveByEmail('invited@example.com');

        self::assertCount(2, $result);
    }

    public function testFindActiveByEmailExcludesUsedTokens(): void
    {
        $this->persistToken(
            email: 'invited@example.com',
            token: 'used_token',
            usedAt: new DateTimeImmutable('-1 hour'),
        );

        $result = $this->repository->findActiveByEmail('invited@example.com');

        self::assertCount(0, $result);
    }

    public function testFindActiveByEmailExcludesExpiredTokens(): void
    {
        $this->persistToken(
            email: 'invited@example.com',
            token: 'expired_token',
            expiresAt: new DateTimeImmutable('-1 second'),
        );

        $result = $this->repository->findActiveByEmail('invited@example.com');

        self::assertCount(0, $result);
    }

    public function testFindActiveByEmailExcludesOtherEmails(): void
    {
        $this->persistToken('other@example.com', 'other_token');

        $result = $this->repository->findActiveByEmail('invited@example.com');

        self::assertCount(0, $result);
    }

    public function testFindActiveByEmailReturnsEmptyArrayWhenNone(): void
    {
        $result = $this->repository->findActiveByEmail('nobody@example.com');

        self::assertEmpty($result);
    }
}
