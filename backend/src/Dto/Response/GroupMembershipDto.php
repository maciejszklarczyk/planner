<?php

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
        return new self(
            id: $membership->getId(),
            user: UserListItemDto::fromEntity($membership->getUser()),
            groupId: $membership->getGroup()->getId(),
            groupName: $membership->getGroup()->getName(),
            role: $membership->getRole()->value,
            addedBy: $membership->getAddedBy()
                ? UserListItemDto::fromEntity($membership->getAddedBy())
                : null,
        );
    }
}
