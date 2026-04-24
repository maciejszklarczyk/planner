<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\EventController;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\HttpFoundation\Response;

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

    public function testItemCreateRequest(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('POST', '/events', [
            'name' => 'New Event Name',
            'startDate' => '2024-01-01',
        ], [], ['HTTP_X_DEV_USER' => 'user1@example.com']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testItemUpdateRequest(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('PUT', '/events/1', [
            'name' => 'Updated Event Name',
            'startDate' => '2024-01-01',
        ], [], ['HTTP_X_DEV_USER' => 'user1@example.com']);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testItemDeleteRequest(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('DELETE', '/events/1', [], [], ['HTTP_X_DEV_USER' => 'user1@example.com']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }
}
