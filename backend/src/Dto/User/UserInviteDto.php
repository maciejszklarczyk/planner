<?php

namespace App\Dto\User;

use Symfony\Component\Validator\Constraints as Assert;

class UserInviteDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public readonly string $email,
    ) {
    }
}
