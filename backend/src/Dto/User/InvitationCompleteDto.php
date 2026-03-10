<?php

namespace App\Dto\User;

use Symfony\Component\Validator\Constraints as Assert;

class InvitationCompleteDto
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $token,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8)]
        public readonly string $password,
    ) {
    }
}
