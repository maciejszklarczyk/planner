<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Event::class)]
final class EventTest extends TestCase
{
    public function testIdIsNullByDefault(): void
    {
        $event = new Event();

        self::assertNull($event->getId());
    }

    public function testSetAndGetName(): void
    {
        $event = new Event();
        $event->setName('Sprint Review');

        self::assertSame('Sprint Review', $event->getName());
    }

    public function testSetAndGetStartDate(): void
    {
        $event = new Event();
        $date = new \DateTimeImmutable('2025-06-01');
        $event->setStartDate($date);

        self::assertSame($date, $event->getStartDate());
    }

    public function testSetAndGetEndDate(): void
    {
        $event = new Event();
        $date = new \DateTimeImmutable('2025-06-01');
        $event->setEndDate($date);

        self::assertSame($date, $event->getEndDate());
    }

    public function testSetAndGetLocation(): void
    {
        $event = new Event();
        $location = 'Krzysztoforzyce';
        $event->setLocation($location);

        self::assertSame($location, $event->getLocation());
    }
}
