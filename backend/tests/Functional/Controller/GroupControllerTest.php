<?php

namespace App\Tests\Functional\Controller;

use App\Controller\GroupController;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

#[UsesClass(GroupController::class)]
class GroupControllerTest extends DatabaseTestCase
{
    private const ADMIN_EMAIL = 'admin@example.com';
    private const ADMIN_PASSWORD = 'password';
    private const USER1_EMAIL = 'user1@example.com';
    private const USER1_PASSWORD = 'password';
    private const USER2_EMAIL = 'user2@example.com';
    private const USER2_PASSWORD = 'password';
    private const USER5_EMAIL = 'user5@example.com';
    private const USER5_PASSWORD = 'password';

    // Fixture data (deterministic):
    // group_1: admin=owner, user_1=member, user_2=member
    // group_2: admin=member, user_1=owner, user_3=member
    // group_3: user_2=owner, user_3=member, user_4=member
    // group_4: user_4=owner, user_5=member
    // group_5: user_5=owner

    private function loginAsAdmin(): KernelBrowser
    {
        static::ensureKernelShutdown();
        $client = static::createClient();
        $client->jsonRequest('POST', '/auth/login', [
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
        ]);
        $this->assertResponseIsSuccessful();

        return $client;
    }

    private function loginAs(string $email, string $password): KernelBrowser
    {
        static::ensureKernelShutdown();
        $client = static::createClient();
        $client->jsonRequest('POST', '/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        $this->assertResponseIsSuccessful();

        return $client;
    }

    private function getGroupIdByName(string $name): int
    {
        $client = $this->loginAsAdmin();
        $client->request('GET', '/groups');
        $data = json_decode($client->getResponse()->getContent(), true);
        foreach ($data['data'] as $group) {
            if ($group['name'] === $name) {
                return $group['id'];
            }
        }
        $this->fail("Group '{$name}' not found in fixture data");
    }

    // -------------------------------------------------------------------------
    // GET /groups
    // -------------------------------------------------------------------------

    public function testGetGroupsAsAdminReturnsOk(): void
    {
        $client = $this->loginAsAdmin();
        $client->request('GET', '/groups');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertNotEmpty($data['data']);
    }

    public function testGetGroupsAsNonAdminReturnsForbidden(): void
    {
        $client = $this->loginAs(self::USER1_EMAIL, self::USER1_PASSWORD);
        $client->request('GET', '/groups');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testGetGroupsUnauthenticatedReturnsUnauthorized(): void
    {
        $client = static::createClient();
        $client->request('GET', '/groups');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    // -------------------------------------------------------------------------
    // GET /groups/{group}
    // -------------------------------------------------------------------------

    public function testGetGroupAsOwnerReturnsOk(): void
    {
        $groupId = $this->getGroupIdByName('Group 1'); // admin=owner
        $client = $this->loginAsAdmin();
        $client->request('GET', "/groups/{$groupId}");

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals($groupId, $data['id']);
    }

    public function testGetGroupAsMemberReturnsOk(): void
    {
        $groupId = $this->getGroupIdByName('Group 1'); // user_1=member
        $client = $this->loginAs(self::USER1_EMAIL, self::USER1_PASSWORD);
        $client->request('GET', "/groups/{$groupId}");

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testGetGroupAsNonMemberReturnsForbidden(): void
    {
        $groupId = $this->getGroupIdByName('Group 1'); // user_5 not in group_1
        $client = $this->loginAs(self::USER5_EMAIL, self::USER5_PASSWORD);
        $client->request('GET', "/groups/{$groupId}");

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testGetGroupUnauthenticatedReturnsUnauthorized(): void
    {
        $groupId = $this->getGroupIdByName('Group 1');
        static::ensureKernelShutdown();
        $client = static::createClient();
        $client->request('GET', "/groups/{$groupId}");

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetGroupNotFoundReturnsNotFound(): void
    {
        $client = $this->loginAsAdmin();
        $client->request('GET', '/groups/99999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // -------------------------------------------------------------------------
    // DELETE /groups/{group}
    // -------------------------------------------------------------------------

    public function testDeleteGroupAsNonOwnerReturnsForbidden(): void
    {
        $groupId = $this->getGroupIdByName('Group 1'); // user_1=member, admin=owner
        $client = $this->loginAs(self::USER1_EMAIL, self::USER1_PASSWORD);
        $client->request('DELETE', "/groups/{$groupId}");

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteGroupUnauthenticatedReturnsUnauthorized(): void
    {
        $groupId = $this->getGroupIdByName('Group 3');
        static::ensureKernelShutdown();
        $client = static::createClient();
        $client->request('DELETE', "/groups/{$groupId}");

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testDeleteGroupNotFoundReturnsNotFound(): void
    {
        $client = $this->loginAsAdmin();
        $client->request('DELETE', '/groups/99999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // Uruchamiamy jako ostatni — soft-delete grupy 3 (nie używanej przez inne testy)
    public function testDeleteGroupAsOwnerReturnsNoContent(): void
    {
        $groupId = $this->getGroupIdByName('Group 3'); // user_2=owner
        $client = $this->loginAs(self::USER2_EMAIL, self::USER2_PASSWORD);
        $client->request('DELETE', "/groups/{$groupId}");

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }
}
