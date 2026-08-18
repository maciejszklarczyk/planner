<?php

declare(strict_types=1);

namespace App\Dto\Friendship;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SendFriendRequestDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
    ) {
    }
}
