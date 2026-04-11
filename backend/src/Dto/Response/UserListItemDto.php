<?php

declare(strict_types=1);

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
        public readonly ?string $avatar,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->getId() ?? throw new \LogicException('User must have an ID.'),
            email: $user->getEmail() ?? throw new \LogicException('User must have an email.'),
            name: $user->getName() ?? '',
            roles: $user->getRoles(),
            status: $user->getStatus() ?? throw new \LogicException('User must have a status.'),
            avatar: $user->getAvatar(),
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
