<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Controller\Admin\GroupMembershipController;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\HttpFoundation\Response;

#[UsesClass(GroupMembershipController::class)]
class GroupMembershipControllerTest extends DatabaseTestCase
{
    private const ADMIN_EMAIL = 'admin@example.com';
    private const ADMIN_PASSWORD = 'password';
    private const USER_EMAIL = 'user1@example.com';
    private const USER_PASSWORD = 'password';

    // Fixture data (deterministic):
    // group_1: admin=owner, user_1=member, user_2=member
    // group_2: admin=member, user_1=owner, user_3=member
    // group_3: user_2=owner, user_3=owner, user_4=member
    // group_4: user_4=owner, user_5=member
    // group_5: user_5=owner

    private function loginAsAdmin(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/auth/login', [
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
        ]);
        $this->assertResponseIsSuccessful();

        return $client;
    }

    private function getAdminId(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): int
    {
        $client->request('GET', '/auth/me');
        $data = json_decode($client->getResponse()->getContent(), true);

        return $data['id'];
    }

    private function getGroupIdByName(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $name): int
    {
        $client->request('GET', '/groups');
        $data = json_decode($client->getResponse()->getContent(), true);
        foreach ($data['data'] as $group) {
            if ($group['name'] === $name) {
                return $group['id'];
            }
        }
        $this->fail("Group '{$name}' not found in fixture data");
    }

    private function getUserIdByEmail(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $email): int
    {
        $client->request('GET', '/admin/users');
        $data = json_decode($client->getResponse()->getContent(), true);
        foreach ($data['data'] as $user) {
            if ($user['email'] === $email) {
                return $user['id'];
            }
        }
        $this->fail("User '{$email}' not found in fixture data");
    }

    // -------------------------------------------------------------------------
    // GET /admin/groups/{groupId}/users
    // -------------------------------------------------------------------------

    public function testListUsersReturnsMembers(): void
    {
        $client = $this->loginAsAdmin();
        $groupId = $this->getGroupIdByName($client, 'Group 1');

        $client->request('GET', "/admin/groups/{$groupId}/users");

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertNotEmpty($data['data']);

        $first = $data['data'][0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('role', $first);
        $this->assertArrayHasKey('user', $first);
    }

    public function testListUsersReturnsNotFoundForUnknownGroup(): void
    {
        $client = $this->loginAsAdmin();

        $client->request('GET', '/admin/groups/99999/users');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[DataProvider('unauthenticatedRequestsProvider')]
    public function testEndpointRequiresAuthentication(string $method, string $url): void
    {
        $client = static::createClient();
        $client->request($method, $url);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public static function unauthenticatedRequestsProvider(): array
    {
        return [
            'list users' => ['GET',    '/admin/groups/1/users'],
            'remove user' => ['DELETE', '/admin/groups/1/users/1'],
            'update role' => ['PATCH',  '/admin/groups/1/users/1/role'],
        ];
    }

    // -------------------------------------------------------------------------
    // DELETE /admin/groups/{groupId}/users/{userId}
    // -------------------------------------------------------------------------

    public function testRemoveUserFromGroup(): void
    {
        $client = $this->loginAsAdmin();
        $groupId = $this->getGroupIdByName($client, 'Group 2');
        $adminId = $this->getAdminId($client);

        // admin is a member (not owner) of group 2 — safe to remove
        $client->request('DELETE', "/admin/groups/{$groupId}/users/{$adminId}");

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Verify the user is no longer in the group
        $client->request('GET', "/admin/groups/{$groupId}/users");
        $data = json_decode($client->getResponse()->getContent(), true);
        $memberIds = array_column(array_column($data['data'], 'user'), 'id');
        $this->assertNotContains($adminId, $memberIds);
    }

    public function testRemoveLastOwnerReturnsUnprocessableEntity(): void
    {
        $client = $this->loginAsAdmin();
        $groupId = $this->getGroupIdByName($client, 'Group 1');
        $adminId = $this->getAdminId($client);

        // group_1 has exactly one owner (admin) per fixtures
        $client->request('DELETE', "/admin/groups/{$groupId}/users/{$adminId}");

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('CANNOT_REMOVE_LAST_OWNER', $body['error']);
    }

    public function testRemoveUserNotInGroupReturnsNotFound(): void
    {
        $client = $this->loginAsAdmin();
        $groupId = $this->getGroupIdByName($client, 'Group 1');

        $client->request('DELETE', "/admin/groups/{$groupId}/users/99999");

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('NOT_FOUND', $body['error']);
    }

    public function testRemoveUserFromUnknownGroupReturnsNotFound(): void
    {
        $client = $this->loginAsAdmin();

        $client->request('DELETE', '/admin/groups/99999/users/1');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('NOT_FOUND', $body['error']);
    }

    #[DataProvider('nonAdminRequestsProvider')]
    public function testEndpointRequiresAdminRole(string $method, string $url): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/auth/login', [
            'email' => self::USER_EMAIL,
            'password' => self::USER_PASSWORD,
        ]);
        $client->request($method, $url);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public static function nonAdminRequestsProvider(): array
    {
        return [
            'remove user' => ['DELETE', '/admin/groups/1/users/1'],
            'update role' => ['PATCH',  '/admin/groups/1/users/1/role'],
        ];
    }

    // -------------------------------------------------------------------------
    // PATCH /admin/groups/{groupId}/users/{userId}/role
    // -------------------------------------------------------------------------

    public function testUpdateUserRoleToOwner(): void
    {
        $client = $this->loginAsAdmin();
        $groupId = $this->getGroupIdByName($client, 'Group 1');
        $adminId = $this->getAdminId($client);

        $client->jsonRequest('PATCH', "/admin/groups/{$groupId}/users/{$adminId}/role", [
            'role' => 'owner',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('owner', $body['role']);
        $this->assertEquals($adminId, $body['user']['id']);
    }

    public function testUpdateUserRoleAcceptsUppercase(): void
    {
        $client = $this->loginAsAdmin();
        $groupId = $this->getGroupIdByName($client, 'Group 1');
        $adminId = $this->getAdminId($client);

        $client->jsonRequest('PATCH', "/admin/groups/{$groupId}/users/{$adminId}/role", [
            'role' => 'OWNER',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('owner', $body['role']);
    }

    public function testUpdateRoleDowngradeLastOwnerReturnsUnprocessableEntity(): void
    {
        $client = $this->loginAsAdmin();
        $groupId = $this->getGroupIdByName($client, 'Group 1');
        $adminId = $this->getAdminId($client);

        // group_1 has exactly one owner (admin) per fixtures
        $client->jsonRequest('PATCH', "/admin/groups/{$groupId}/users/{$adminId}/role", [
            'role' => 'member',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('CANNOT_REMOVE_LAST_OWNER', $body['error']);
    }

    public function testUpdateRoleForUserNotInGroupReturnsNotFound(): void
    {
        $client = $this->loginAsAdmin();
        $groupId = $this->getGroupIdByName($client, 'Group 1');

        $client->jsonRequest('PATCH', "/admin/groups/{$groupId}/users/99999/role", [
            'role' => 'member',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('NOT_FOUND', $body['error']);
    }

    public function testUpdateRoleForUnknownGroupReturnsNotFound(): void
    {
        $client = $this->loginAsAdmin();

        $client->jsonRequest('PATCH', '/admin/groups/99999/users/1/role', [
            'role' => 'member',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('NOT_FOUND', $body['error']);
    }

    public function testUpdateRoleWithInvalidValueReturnsUnprocessableEntity(): void
    {
        $client = $this->loginAsAdmin();
        $groupId = $this->getGroupIdByName($client, 'Group 1');
        $adminId = $this->getAdminId($client);

        $client->jsonRequest('PATCH', "/admin/groups/{$groupId}/users/{$adminId}/role", [
            'role' => 'superadmin',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // -------------------------------------------------------------------------
    // GROUP_ALREADY_HAS_OWNER — add as owner when group already has one
    // -------------------------------------------------------------------------

    public function testAddUserAsOwnerWhenGroupAlreadyHasOwnerReturnsUnprocessableEntity(): void
    {
        $client = $this->loginAsAdmin();
        // group_1 already has admin as owner; try to add user_3 (not in group_1) as owner
        $groupId = $this->getGroupIdByName($client, 'Group 1');
        $user3Id = $this->getUserIdByEmail($client, 'user3@example.com');

        $client->jsonRequest('POST', "/admin/groups/{$groupId}/users", [
            'userId' => $user3Id,
            'role' => 'owner',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('GROUP_ALREADY_HAS_OWNER', $body['error']);
    }

    public function testAddUserAsMemberWhenGroupAlreadyHasOwnerSucceeds(): void
    {
        $client = $this->loginAsAdmin();
        // group_5 has only user_5 as owner — add user_4 (not in group_5) as member
        $groupId = $this->getGroupIdByName($client, 'Group 5');
        $user4Id = $this->getUserIdByEmail($client, 'user4@example.com');

        $client->jsonRequest('POST', "/admin/groups/{$groupId}/users", [
            'userId' => $user4Id,
            'role' => 'member',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    #[DataProvider('promoteToOwnerWithExistingOwnerProvider')]
    public function testPromoteToOwnerInGroupWithExistingOwnerFails(string $groupName, string $userEmail): void
    {
        $client = $this->loginAsAdmin();
        $groupId = $this->getGroupIdByName($client, $groupName);
        $userId = $this->getUserIdByEmail($client, $userEmail);

        $client->jsonRequest('PATCH', "/admin/groups/{$groupId}/users/{$userId}/role", ['role' => 'owner']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('GROUP_ALREADY_HAS_OWNER', $body['error']);
    }

    public static function promoteToOwnerWithExistingOwnerProvider(): array
    {
        return [
            'group_1 promote user_1' => ['Group 1', 'user1@example.com'],
            'group_4 promote user_5' => ['Group 4', 'user5@example.com'],
        ];
    }
}
