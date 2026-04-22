<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\EventController;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(EventController::class)]
final class EventControllerTest extends DatabaseTestCase
{
    public function testCollectionGetRequest(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('GET', '/events', [], [], ['HTTP_X_DEV_USER' => 'user1@example.com']);

        self::assertResponseIsSuccessful();
    }

    public function testItemGetRequest(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('GET', '/events/1', [], [], ['HTTP_X_DEV_USER' => 'user1@example.com']);

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertIsInt($data['id']);
        self::assertIsString($data['name']);
        self::assertIsString($data['startDate']);
    }
}
