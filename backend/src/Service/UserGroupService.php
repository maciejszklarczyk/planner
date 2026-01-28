<?php

namespace App\Service;

use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserHasGroup;
use App\Repository\UserHasGroupRepository;
use Doctrine\ORM\EntityManagerInterface;

class UserGroupService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserHasGroupRepository $userHasGroupRepository,
    ) {
    }

    public function addUserToGroup(User $user, Group $group, ?User $addedBy = null): UserHasGroup
    {
        // sprawdź czy już nie należy
        if ($this->isMember($user, $group)) {
            throw new \LogicException('User is already a member of this group');
        }

        $uhg = new UserHasGroup();
        $uhg->setUser($user);
        $uhg->setGroup($group);

        if ($addedBy) {
            $uhg->setAddedBy($addedBy);
        }

        $this->em->persist($uhg);
        $this->em->flush();

        return $uhg;
    }
}
