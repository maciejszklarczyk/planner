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
        public readonly \DateTimeImmutable $endDate,
        public readonly string $location,
        public readonly int $attendees = 0,
        public readonly string $category = 'Cat 1',
    ) {
    }

    public static function fromEntity(Event $event): self
    {
        return new self(
            id: $event->getId() ?? throw new \LogicException('Event must have an ID.'),
            name: $event->getName() ?? throw new \LogicException('Event must have a name.'),
            startDate: $event->getStartDate(),
            endDate: $event->getEndDate(),
            location: $event->getLocation(),
            attendees: rand(10, 20),
            category: sprintf('Cat %d', rand(1, 5)),
        );
    }

    /**
     * @param Event[] $events
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
