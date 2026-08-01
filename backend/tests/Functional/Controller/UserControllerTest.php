<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\UserController;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\HttpFoundation\Response;

#[UsesClass(UserController::class)]
final class UserControllerTest extends DatabaseTestCase
{
    public function testSearchByPartialEmailMatchReturnsMatchingUsers(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();

        $client->jsonRequest('GET', '/users?search=user1', [], ['HTTP_X_DEV_USER' => 'user2@example.com']);

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $data['data']);
        self::assertSame('user1@example.com', $data['data'][0]['email']);
    }

    public function testSearchExcludesTheCallersOwnAccount(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();

        $client->jsonRequest('GET', '/users?search=user', [], ['HTTP_X_DEV_USER' => 'user1@example.com']);

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $emails = array_column($data['data'], 'email');
        self::assertNotContains('user1@example.com', $emails, 'Caller must be excluded from their own search results.');
        self::assertContains('user2@example.com', $emails);
    }

    public function testSearchResultOmitsRolesAndStatus(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();

        $client->jsonRequest('GET', '/users?search=user1', [], ['HTTP_X_DEV_USER' => 'user2@example.com']);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayNotHasKey('roles', $data['data'][0]);
        self::assertArrayNotHasKey('status', $data['data'][0]);
        self::assertArrayHasKey('id', $data['data'][0]);
        self::assertArrayHasKey('name', $data['data'][0]);
        self::assertArrayHasKey('avatar', $data['data'][0]);
    }

    public function testSearchWithBlankQueryReturnsEmptyList(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();

        $client->jsonRequest('GET', '/users?search=', [], ['HTTP_X_DEV_USER' => 'user1@example.com']);

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame([], $data['data']);
    }

    public function testSearchWithMissingQueryReturnsEmptyList(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();

        $client->jsonRequest('GET', '/users', [], ['HTTP_X_DEV_USER' => 'user1@example.com']);

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame([], $data['data']);
    }

    public function testSearchWithoutAuthenticationReturns401(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();

        $client->jsonRequest('GET', '/users?search=user1');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
