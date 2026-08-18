<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\FriendshipStatusEnum;
use App\Entity\FriendRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FriendRequest>
 */
class FriendRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FriendRequest::class);
    }

    /**
     * Find the pending-or-accepted row between two users, in either direction.
     */
    public function findActiveBetween(int $userAId, int $userBId): ?FriendRequest
    {
        return $this->createQueryBuilder('fr')
            ->andWhere('fr.status IN (:activeStatuses)')
            ->andWhere('(fr.requester = :userA AND fr.addressee = :userB) OR (fr.requester = :userB AND fr.addressee = :userA)')
            ->setParameter('activeStatuses', [FriendshipStatusEnum::PENDING, FriendshipStatusEnum::ACCEPTED])
            ->setParameter('userA', $userAId)
            ->setParameter('userB', $userBId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Most recent declined row sent by $requesterId to $addresseeId (direction-specific, for the cooldown check).
     */
    public function findLatestDeclinedBySender(int $requesterId, int $addresseeId): ?FriendRequest
    {
        return $this->createQueryBuilder('fr')
            ->andWhere('fr.status = :declined')
            ->andWhere('fr.requester = :requesterId')
            ->andWhere('fr.addressee = :addresseeId')
            ->setParameter('declined', FriendshipStatusEnum::DECLINED)
            ->setParameter('requesterId', $requesterId)
            ->setParameter('addresseeId', $addresseeId)
            ->orderBy('fr.respondedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return FriendRequest[]
     */
    public function findAcceptedForUser(int $userId): array
    {
        return $this->createQueryBuilder('fr')
            ->join('fr.requester', 'req')
            ->addSelect('req')
            ->join('fr.addressee', 'add')
            ->addSelect('add')
            ->andWhere('fr.status = :accepted')
            ->andWhere('fr.requester = :userId OR fr.addressee = :userId')
            ->setParameter('accepted', FriendshipStatusEnum::ACCEPTED)
            ->setParameter('userId', $userId)
            ->orderBy('fr.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{incoming: FriendRequest[], outgoing: FriendRequest[]}
     */
    public function findPendingForUser(int $userId): array
    {
        $incoming = $this->createQueryBuilder('fr')
            ->join('fr.requester', 'req')
            ->addSelect('req')
            ->andWhere('fr.status = :pending')
            ->andWhere('fr.addressee = :userId')
            ->setParameter('pending', FriendshipStatusEnum::PENDING)
            ->setParameter('userId', $userId)
            ->orderBy('fr.id', 'ASC')
            ->getQuery()
            ->getResult();

        $outgoing = $this->createQueryBuilder('fr')
            ->join('fr.addressee', 'add')
            ->addSelect('add')
            ->andWhere('fr.status = :pending')
            ->andWhere('fr.requester = :userId')
            ->setParameter('pending', FriendshipStatusEnum::PENDING)
            ->setParameter('userId', $userId)
            ->orderBy('fr.id', 'ASC')
            ->getQuery()
            ->getResult();

        return ['incoming' => $incoming, 'outgoing' => $outgoing];
    }
}
