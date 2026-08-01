<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Find users with pagination and filters.
     *
     * @param int         $page           Page number (1-indexed)
     * @param int         $limit          Items per page
     * @param string|null $search         Search in email
     * @param int|null    $excludeGroupId Exclude users already in this group
     * @param int|null    $excludeUserId  Exclude this specific user (e.g. the current caller)
     *
     * @return User[]
     */
    public function findWithPagination(
        int $page = 1,
        int $limit = 50,
        ?string $search = null,
        ?int $excludeGroupId = null,
        ?int $excludeUserId = null,
    ): array {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.id', 'ASC');

        // Apply search filter
        if (null !== $search && '' !== $search) {
            $qb->andWhere('u.email LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        // Exclude users already in group
        if (null !== $excludeGroupId) {
            $qb->andWhere('u.id NOT IN (
                SELECT IDENTITY(uhg.user)
                FROM App\Entity\UserHasGroup uhg
                WHERE uhg.group = :groupId
            )')
            ->setParameter('groupId', $excludeGroupId);
        }

        // Exclude a specific user (e.g. the caller, for a "find someone else" search)
        if (null !== $excludeUserId) {
            $qb->andWhere('u.id != :excludeUserId')
                ->setParameter('excludeUserId', $excludeUserId);
        }

        // Apply pagination
        $offset = ($page - 1) * $limit;
        $qb->setFirstResult($offset)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Count users with filters.
     *
     * @param string|null $search         Search in email
     * @param int|null    $excludeGroupId Exclude users already in this group
     * @param int|null    $excludeUserId  Exclude this specific user (e.g. the current caller)
     */
    public function countWithFilters(
        ?string $search = null,
        ?int $excludeGroupId = null,
        ?int $excludeUserId = null,
    ): int {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)');

        // Apply search filter
        if (null !== $search && '' !== $search) {
            $qb->andWhere('u.email LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        // Exclude users already in group
        if (null !== $excludeGroupId) {
            $qb->andWhere('u.id NOT IN (
                SELECT IDENTITY(uhg.user)
                FROM App\Entity\UserHasGroup uhg
                WHERE uhg.group = :groupId
            )')
            ->setParameter('groupId', $excludeGroupId);
        }

        // Exclude a specific user (e.g. the caller, for a "find someone else" search)
        if (null !== $excludeUserId) {
            $qb->andWhere('u.id != :excludeUserId')
                ->setParameter('excludeUserId', $excludeUserId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
