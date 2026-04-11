<?php

declare(strict_types=1);

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
            id: $group->getId() ?? throw new \LogicException('Group must have an ID.'),
            name: $group->getName() ?? throw new \LogicException('Group must have a name.'),
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
