<?php

namespace App\Dto\Response;

use App\Entity\Group;

class GroupListItemDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $membersCount,
    ) {
    }

    public static function fromEntity(Group $group): self
    {
        return new self(
            id: $group->getId(),
            name: $group->getName(),
            description: $group->getDescription(),
            membersCount: $group->getUserHasGroups()->count(),
        );
    }

    /**
     * @param Group[] $groups
     *
     * @return self[]
     */
    public static function fromEntities(array $groups): array
    {
        return array_map(
            fn (Group $group) => self::fromEntity($group),
            $groups
        );
    }
}
