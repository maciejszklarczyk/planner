<?php

declare(strict_types=1);

namespace App\Dto\Event;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateEventDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,

        #[Assert\NotBlank]
        public \DateTimeImmutable $startDate,
    ) {
    }
}
