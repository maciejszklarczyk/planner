<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\HealthCheckController;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(HealthCheckController::class)]
final class HealthCheckControllerTest extends DatabaseTestCase
{
    public function testHealthCheck(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('GET', '/health', [], [], []);

        self::assertResponseIsSuccessful();
    }

    public function testItemGetRequest(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('GET', '/version', [], [], []);

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertArrayHasKey('version', $data);
    }
}
