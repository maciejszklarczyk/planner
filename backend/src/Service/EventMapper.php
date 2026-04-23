<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Event\CreateEventDto;
use App\Dto\Event\UpdateEventDto;
use App\Entity\Event;

final class EventMapper
{
    public function fromDto(CreateEventDto $dto): Event
    {
        $event = new Event();
        $event->setName($dto->name);
        $event->setStartDate($dto->startDate);

        return $event;
    }

    public function updateFromDto(Event $event, UpdateEventDto $dto): void
    {
        $event->setName($dto->name);
        $event->setStartDate($dto->startDate);
    }
}
