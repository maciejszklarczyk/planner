<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Enum\UserGroupRoleEnum;
use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserHasGroup;
use App\Exception\CannotRemoveLastOwnerException;
use App\Exception\GroupAlreadyHasOwnerException;
use App\Exception\UserAlreadyInGroupException;
use App\Repository\GroupRepository;
use App\Repository\UserHasGroupRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GroupMembershipService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserHasGroupRepository $userHasGroupRepository,
        private UserRepository $userRepository,
        private GroupRepository $groupRepository,
    ) {
    }

    /**
     * Add user to group with specified role.
     *
     * @throws NotFoundHttpException         if user or group not found
     * @throws UserAlreadyInGroupException   if user is already in the group
     * @throws GroupAlreadyHasOwnerException if adding as owner and group already has one
     */
    public function addUserToGroup(
        int $userId,
        int $groupId,
        UserGroupRoleEnum $role,
        User $addedBy,
    ): UserHasGroup {
        $user = $this->userRepository->find($userId);
        if (!$user) {
            throw new NotFoundHttpException("User with ID {$userId} not found");
        }

        $group = $this->groupRepository->find($groupId);
        if (!$group) {
            throw new NotFoundHttpException("Group with ID {$groupId} not found");
        }

        if ($this->userHasGroupRepository->isUserInGroup($userId, $groupId)) {
            throw new UserAlreadyInGroupException("User with ID {$userId} is already a member of group {$groupId}");
        }

        if (UserGroupRoleEnum::OWNER === $role && $this->userHasGroupRepository->countOwnersByGroup($groupId) > 0) {
            throw new GroupAlreadyHasOwnerException("Group {$groupId} already has an owner. Remove or change the current owner's role first.");
        }

        $membership = new UserHasGroup();
        $membership->setUser($user);
        $membership->setGroup($group);
        $membership->setRole($role);
        $membership->setAddedBy($addedBy);

        $this->em->persist($membership);
        $this->em->flush();

        return $membership;
    }

    /**
     * Remove user from group.
     *
     * @throws NotFoundHttpException          if group or membership not found
     * @throws CannotRemoveLastOwnerException if user is the last owner of the group
     */
    public function removeUserFromGroup(int $groupId, int $userId): void
    {
        $group = $this->groupRepository->find($groupId);
        if (!$group) {
            throw new NotFoundHttpException("Group with ID {$groupId} not found");
        }

        $membership = $this->userHasGroupRepository->findByUserAndGroup($userId, $groupId);
        if (!$membership) {
            throw new NotFoundHttpException("User with ID {$userId} is not a member of group {$groupId}");
        }

        if (UserGroupRoleEnum::OWNER === $membership->getRole()) {
            $ownerCount = $this->userHasGroupRepository->countOwnersByGroup($groupId);
            if ($ownerCount <= 1) {
                throw new CannotRemoveLastOwnerException("Cannot remove the last owner from group {$groupId}");
            }
        }

        $this->em->remove($membership);
        $this->em->flush();
    }

    /**
     * Update user role in group.
     *
     * @throws NotFoundHttpException          if group or membership not found
     * @throws CannotRemoveLastOwnerException if downgrading the last owner
     * @throws GroupAlreadyHasOwnerException  if promoting to owner and group already has one
     */
    public function updateUserRole(int $groupId, int $userId, UserGroupRoleEnum $newRole): UserHasGroup
    {
        $group = $this->groupRepository->find($groupId);
        if (!$group) {
            throw new NotFoundHttpException("Group with ID {$groupId} not found");
        }

        $membership = $this->userHasGroupRepository->findByUserAndGroup($userId, $groupId);
        if (!$membership) {
            throw new NotFoundHttpException("User with ID {$userId} is not a member of group {$groupId}");
        }

        $currentRole = $membership->getRole();

        if (UserGroupRoleEnum::OWNER === $currentRole && UserGroupRoleEnum::OWNER !== $newRole) {
            if ($this->userHasGroupRepository->countOwnersByGroup($groupId) <= 1) {
                throw new CannotRemoveLastOwnerException("Cannot remove the last owner from group {$groupId}");
            }
        }

        if (UserGroupRoleEnum::OWNER !== $currentRole && UserGroupRoleEnum::OWNER === $newRole) {
            if ($this->userHasGroupRepository->countOwnersByGroup($groupId) > 0) {
                throw new GroupAlreadyHasOwnerException("Group {$groupId} already has an owner. Remove or change the current owner's role first.");
            }
        }

        $membership->setRole($newRole);
        $this->em->flush();

        return $membership;
    }
}
