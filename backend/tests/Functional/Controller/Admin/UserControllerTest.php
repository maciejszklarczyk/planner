<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Controller\Admin\UserController;
use App\Entity\Group;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(UserController::class)]
final class UserControllerTest extends DatabaseTestCase
{
    // Fixture data:
    // group_1: admin=owner, user_1=member, user_2=member
    // user_5: status=NEW (default, not set in fixture)
    // user_1: status=active

    private const ADMIN_EMAIL = 'admin@example.com';
    private const USER1_EMAIL = 'user1@example.com';

    public function testListReturnsUsersWithPaginationForAdmin(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('GET', '/admin/users', [], [], ['HTTP_X_DEV_USER' => self::ADMIN_EMAIL]);

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $data);
        self::assertArrayHasKey('pagination', $data);
        self::assertIsArray($data['data']);
        self::assertArrayHasKey('page', $data['pagination']);
        self::assertArrayHasKey('limit', $data['pagination']);
        self::assertArrayHasKey('total', $data['pagination']);
        self::assertArrayHasKey('pages', $data['pagination']);
    }

    public function testListRequiresAdminRole(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('GET', '/admin/users', [], [], ['HTTP_X_DEV_USER' => self::USER1_EMAIL]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testListWithSearchFilterReturnsMatchingUsers(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request(
            'GET',
            '/admin/users?search=user1%40example.com',
            [],
            [],
            ['HTTP_X_DEV_USER' => self::ADMIN_EMAIL]
        );

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $data['data']);
        self::assertSame('user1@example.com', $data['data'][0]['email']);
    }

    public function testListWithPaginationParams(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request(
            'GET',
            '/admin/users?page=1&limit=2',
            [],
            [],
            ['HTTP_X_DEV_USER' => self::ADMIN_EMAIL]
        );

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(2, $data['data']);
        self::assertSame(1, $data['pagination']['page']);
        self::assertSame(2, $data['pagination']['limit']);
    }

    public function testListExcludesGroupMembers(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $group = $em->getRepository(Group::class)->findOneBy(['name' => 'Group 1']);

        $client->request(
            'GET',
            '/admin/users?excludeGroupId='.$group->getId(),
            [],
            [],
            ['HTTP_X_DEV_USER' => self::ADMIN_EMAIL]
        );

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $emails = array_column($data['data'], 'email');
        self::assertNotContains('admin@example.com', $emails);
        self::assertNotContains('user1@example.com', $emails);
        self::assertNotContains('user2@example.com', $emails);
        self::assertContains('user3@example.com', $emails);
    }

    public function testSendUserInviteCreatesUser(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest(
            'POST',
            '/admin/user-invite',
            ['email' => 'invite-new@example.com'],
            ['HTTP_X_DEV_USER' => self::ADMIN_EMAIL]
        );

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('ok', $data['status']);
        self::assertSame('invite-new@example.com', $data['email']);
    }

    public function testSendUserInviteFailsIfUserAlreadyExists(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest(
            'POST',
            '/admin/user-invite',
            ['email' => self::ADMIN_EMAIL],
            ['HTTP_X_DEV_USER' => self::ADMIN_EMAIL]
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('USER_ALREADY_EXISTS', $data['error']);
    }

    public function testSendUserInviteRequiresAdmin(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest(
            'POST',
            '/admin/user-invite',
            ['email' => 'someone@example.com'],
            ['HTTP_X_DEV_USER' => self::USER1_EMAIL]
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testResendUserInviteSuccessForNewUser(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        // user5@example.com has no status set in fixtures → defaults to NEW
        $client->jsonRequest(
            'POST',
            '/admin/user-invite/resend',
            ['email' => 'user5@example.com'],
            ['HTTP_X_DEV_USER' => self::ADMIN_EMAIL]
        );

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('ok', $data['status']);
    }

    public function testResendUserInviteReturnsNotFoundForUnknownEmail(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest(
            'POST',
            '/admin/user-invite/resend',
            ['email' => 'nonexistent@example.com'],
            ['HTTP_X_DEV_USER' => self::ADMIN_EMAIL]
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('NOT_FOUND', $data['error']);
    }

    public function testResendUserInviteFailsForAlreadyActiveUser(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        // user1@example.com has status=active in fixtures
        $client->jsonRequest(
            'POST',
            '/admin/user-invite/resend',
            ['email' => self::USER1_EMAIL],
            ['HTTP_X_DEV_USER' => self::ADMIN_EMAIL]
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('USER_ALREADY_COMPLETED_REGISTRATION', $data['error']);
    }

    public function testResendUserInviteRequiresAdmin(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->jsonRequest(
            'POST',
            '/admin/user-invite/resend',
            ['email' => 'user5@example.com'],
            ['HTTP_X_DEV_USER' => self::USER1_EMAIL]
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
