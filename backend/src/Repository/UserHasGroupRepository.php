<?php

namespace App\Repository;

use App\Entity\UserHasGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserHasGroup>
 */
class UserHasGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserHasGroup::class);
    }

    /**
     * Find membership by user ID and group ID.
     */
    public function findByUserAndGroup(int $userId, int $groupId): ?UserHasGroup
    {
        return $this->createQueryBuilder('uhg')
            ->andWhere('uhg.user = :userId')
            ->andWhere('uhg.group = :groupId')
            ->setParameter('userId', $userId)
            ->setParameter('groupId', $groupId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if user is already a member of the group.
     */
    public function isUserInGroup(int $userId, int $groupId): bool
    {
        return null !== $this->findByUserAndGroup($userId, $groupId);
    }

    //    /**
    //     * @return UserHasGroup[] Returns an array of UserHasGroup objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?UserHasGroup
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
