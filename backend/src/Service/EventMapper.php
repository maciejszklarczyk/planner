<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Event\CreateEventDto;
use App\Dto\Event\EventDto;
use App\Dto\Event\UpdateEventDto;
use App\Entity\Event;

final class EventMapper
{
    public function fromDto(CreateEventDto $dto): Event
    {
        $event = new Event();
        $this->setBaseFields($event, $dto);

        return $event;
    }

    public function updateFromDto(Event $event, UpdateEventDto $dto): void
    {
        $this->setBaseFields($event, $dto);
    }

    private function setBaseFields(Event $event, EventDto $dto): void
    {
        $event->setName($dto->name);
        $event->setStartDate($dto->startDate);
        $event->setEndDate($dto->endDate);
        $event->setLocation($dto->location);
    }
}
