<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Security\DevHeaderAuthenticator;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(DevHeaderAuthenticator::class)]
class DevHeaderAuthenticatorTest extends DatabaseTestCase
{
    public function testAuthenticatesWithValidUser(): void
    {
        $client = static::createClient();
        $client->request('GET', '/auth/me', [], [], ['HTTP_X_DEV_USER' => 'admin@example.com']);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('admin@example.com', $data['email']);
    }

    public function testReturns401ForUnknownEmail(): void
    {
        $client = static::createClient();
        $client->request('GET', '/auth/me', [], [], ['HTTP_X_DEV_USER' => 'nobody@example.com']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data);
    }

    public function testSkipsWhenHeaderAbsent(): void
    {
        $client = static::createClient();
        $client->request('GET', '/auth/me');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testAuthenticatesRegularUser(): void
    {
        $client = static::createClient();
        $client->request('GET', '/auth/me', [], [], ['HTTP_X_DEV_USER' => 'user1@example.com']);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('user1@example.com', $data['email']);
        $this->assertNotContains('ROLE_ADMIN', $data['roles']);
    }
}
