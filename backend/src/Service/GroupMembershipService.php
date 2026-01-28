<?php

namespace App\Service;

use App\Entity\Enum\UserGroupRoleEnum;
use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserHasGroup;
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
     * @throws NotFoundHttpException       if user or group not found
     * @throws UserAlreadyInGroupException if user is already in the group
     */
    public function addUserToGroup(
        int $userId,
        int $groupId,
        UserGroupRoleEnum $role,
        User $addedBy,
    ): UserHasGroup {
        // Find user
        $user = $this->userRepository->find($userId);
        if (!$user) {
            throw new NotFoundHttpException("User with ID {$userId} not found");
        }

        // Find group
        $group = $this->groupRepository->find($groupId);
        if (!$group) {
            throw new NotFoundHttpException("Group with ID {$groupId} not found");
        }

        // Check if user is already in group
        if ($this->userHasGroupRepository->isUserInGroup($userId, $groupId)) {
            throw new UserAlreadyInGroupException($userId, $groupId);
        }

        // Create membership
        $membership = new UserHasGroup();
        $membership->setUser($user);
        $membership->setGroup($group);
        $membership->setRole($role);
        $membership->setAddedBy($addedBy);

        $this->em->persist($membership);
        $this->em->flush();

        return $membership;
    }

    // TODO: Implement business logic methods
    // - removeUserFromGroup()
    // - updateUserRole()
    // - getGroupMembers()
    // - validateLastOwnerNotRemoved()
}
