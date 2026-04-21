<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Entity\Event;

class EventItemDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly \DateTimeImmutable $startDate,
    ) {
    }

    public static function fromEntity(Event $event): self
    {
        return new self(
            id: $event->getId() ?? throw new \LogicException('Event must have an ID.'),
            name: $event->getName() ?? throw new \LogicException('Event must have a name.'),
            startDate: $event->getStartDate(),
        );
    }

    /**
     * @param Events[] $events
     *
     * @return self[]
     */
    public static function fromEntities(array $events): array
    {
        return array_map(
            fn (Event $events) => self::fromEntity($events),
            $events
        );
    }
}
