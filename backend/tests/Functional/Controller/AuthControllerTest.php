<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\AuthController;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\HttpFoundation\Response;

#[UsesClass(AuthController::class)]
class AuthControllerTest extends DatabaseTestCase
{
    private const ADMIN_EMAIL = 'admin@example.com';
    private const ADMIN_PASSWORD = 'password';
    private const USER_EMAIL = 'user1@example.com';
    private const USER_PASSWORD = 'password';

    public function testLoginWithValidCredentials(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/auth/login', [
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $responseData = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('user', $responseData);
        $this->assertArrayHasKey('id', $responseData['user']);
        $this->assertArrayHasKey('email', $responseData['user']);
        $this->assertArrayHasKey('roles', $responseData['user']);
        $this->assertEquals(self::ADMIN_EMAIL, $responseData['user']['email']);
        $this->assertContains('ROLE_ADMIN', $responseData['user']['roles']);
    }

    public function testLoginWithInvalidPassword(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/auth/login', [
            'email' => self::ADMIN_EMAIL,
            'password' => 'wrong_password',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testLoginWithInvalidEmail(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => self::ADMIN_PASSWORD,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testLoginWithMissingCredentials(): void
    {
        $client = static::createClient();

        $client->request('POST', '/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{}');

        // Empty JSON body will result in 400 Bad Request or 401 Unauthorized
        $this->assertContains(
            $client->getResponse()->getStatusCode(),
            [Response::HTTP_BAD_REQUEST, Response::HTTP_UNAUTHORIZED]
        );
    }

    public function testLoginWithMissingEmail(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/auth/login', [
            'password' => self::ADMIN_PASSWORD,
        ]);

        // Missing email will result in 400 Bad Request
        $this->assertContains(
            $client->getResponse()->getStatusCode(),
            [Response::HTTP_BAD_REQUEST, Response::HTTP_UNAUTHORIZED]
        );
    }

    public function testLoginWithMissingPassword(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/auth/login', [
            'email' => self::ADMIN_EMAIL,
        ]);

        // Missing password will result in 400 Bad Request
        $this->assertContains(
            $client->getResponse()->getStatusCode(),
            [Response::HTTP_BAD_REQUEST, Response::HTTP_UNAUTHORIZED]
        );
    }

    public function testMeEndpointWithoutAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/auth/me');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertSame('AUTHENTICATION_REQUIRED', $responseData['error']);
    }

    public function testMeEndpointWithAuthentication(): void
    {
        $client = static::createClient();

        // Login first
        $client->jsonRequest('POST', '/auth/login', [
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
        ]);

        $this->assertResponseIsSuccessful();

        // Now call /auth/me
        $client->request('GET', '/auth/me');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $responseData = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('id', $responseData);
        $this->assertArrayHasKey('email', $responseData);
        $this->assertArrayHasKey('roles', $responseData);
        $this->assertEquals(self::ADMIN_EMAIL, $responseData['email']);
        $this->assertContains('ROLE_ADMIN', $responseData['roles']);
    }

    public function testRegularUserLogin(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/auth/login', [
            'email' => self::USER_EMAIL,
            'password' => self::USER_PASSWORD,
        ]);

        $this->assertResponseIsSuccessful();

        $responseData = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals(self::USER_EMAIL, $responseData['user']['email']);
        $this->assertContains('ROLE_USER', $responseData['user']['roles']);
        $this->assertNotContains('ROLE_ADMIN', $responseData['user']['roles']);
    }

    public function testLogout(): void
    {
        $client = static::createClient();

        // Login first
        $client->jsonRequest('POST', '/auth/login', [
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
        ]);

        $this->assertResponseIsSuccessful();

        // Verify we're logged in
        $client->request('GET', '/auth/me');
        $this->assertResponseIsSuccessful();

        // Logout
        $client->request('POST', '/auth/logout');

        // Verify we're logged out
        $client->request('GET', '/auth/me');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
