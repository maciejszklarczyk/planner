<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserInvitationToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserInvitationToken>
 */
class UserInvitationTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserInvitationToken::class);
    }

    /**
     * @return UserInvitationToken[]
     */
    public function findActiveByEmail(string $email): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.email = :email')
            ->andWhere('t.usedAt IS NULL')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('email', $email)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }
}
