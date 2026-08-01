<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Entity\User;

class UserSearchResultDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $avatar,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->getId() ?? throw new \LogicException('User must have an ID.'),
            name: $user->getName() ?? '',
            email: $user->getEmail() ?? throw new \LogicException('User must have an email.'),
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
