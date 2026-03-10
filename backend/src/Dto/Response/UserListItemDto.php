<?php

namespace App\Dto\Response;

use App\Entity\Enum\UserStatusEnum;
use App\Entity\User;

class UserListItemDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $name,
        public readonly array $roles,
        public readonly UserStatusEnum $status,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->getId(),
            email: $user->getEmail(),
            name: $user->getName() ?? '',
            roles: $user->getRoles(),
            status: $user->getStatus(),
        );
    }

    /**
     * @param User[] $users
     *
     * @return self[]
     */
    public static function fromEntities(array $users): array
    {
        return array_map(
            fn (User $user) => self::fromEntity($user),
            $users
        );
    }
}
