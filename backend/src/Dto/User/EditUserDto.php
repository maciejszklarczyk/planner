<?php

namespace App\Dto\User;

use App\Entity\User;

class EditUserDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $name,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->getId(),
            email: $user->getEmail(),
            name: $user->getName()
        );
    }
}
