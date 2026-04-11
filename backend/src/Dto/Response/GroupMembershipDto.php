<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Entity\UserHasGroup;

class GroupMembershipDto
{
    public function __construct(
        public readonly int $id,
        public readonly UserListItemDto $user,
        public readonly int $groupId,
        public readonly string $groupName,
        public readonly string $role,
        public readonly ?UserListItemDto $addedBy,
    ) {
    }

    public static function fromEntity(UserHasGroup $membership): self
    {
        $group = $membership->getGroup() ?? throw new \LogicException('Membership must have a group.');
        $user = $membership->getUser() ?? throw new \LogicException('Membership must have a user.');

        return new self(
            id: $membership->getId() ?? throw new \LogicException('Membership must have an ID.'),
            user: UserListItemDto::fromEntity($user),
            groupId: $group->getId() ?? throw new \LogicException('Group must have an ID.'),
            groupName: $group->getName() ?? throw new \LogicException('Group must have a name.'),
            role: $membership->getRole()->value,
            addedBy: $membership->getAddedBy()
                ? UserListItemDto::fromEntity($membership->getAddedBy())
                : null,
        );
    }
}
