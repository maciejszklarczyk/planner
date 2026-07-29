<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\AdminBootstrapCommand;
use App\Entity\Enum\UserStatusEnum;
use App\Entity\UserInvitationToken;
use App\Repository\UserRepository;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(AdminBootstrapCommand::class)]
final class AdminBootstrapCommandTest extends DatabaseTestCase
{
    private EntityManagerInterface $em;
    private UserRepository $userRepository;
    private CommandTester $commandTester;

    private const TEST_EMAIL = 'newadmin-bootstrap-test@example.com';

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $kernel = self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->userRepository = self::getContainer()->get(UserRepository::class);

        $this->em->createQuery('DELETE FROM App\Entity\UserInvitationToken t WHERE t.email = :email')
            ->setParameter('email', self::TEST_EMAIL)
            ->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User u WHERE u.email = :email')
            ->setParameter('email', self::TEST_EMAIL)
            ->execute();
        $this->em->clear();

        $application = new Application($kernel);
        $command = $application->find('app:admin:bootstrap');
        $this->commandTester = new CommandTester($command);
    }

    public function testCreatesAdminUserAndPrintsInvitationLink(): void
    {
        $exitCode = $this->commandTester->execute(['email' => self::TEST_EMAIL]);

        self::assertSame(0, $exitCode);

        $user = $this->userRepository->findOneBy(['email' => self::TEST_EMAIL]);
        self::assertNotNull($user);
        self::assertTrue($user->hasRole('ROLE_ADMIN'));
        self::assertSame(UserStatusEnum::NEW, $user->getStatus());

        $token = $this->em->getRepository(UserInvitationToken::class)->findOneBy(['email' => self::TEST_EMAIL]);
        self::assertNotNull($token);
        self::assertNull($token->getUsedAt());
        self::assertEqualsWithDelta(
            (new \DateTimeImmutable('+1 day'))->getTimestamp(),
            $token->getExpiresAt()->getTimestamp(),
            5,
        );

        self::assertStringContainsString('/set-password?token=', $this->commandTester->getDisplay());
    }

    public function testRejectsExistingEmailWithoutCreatingDuplicate(): void
    {
        $exitCode = $this->commandTester->execute(['email' => self::TEST_EMAIL]);
        self::assertSame(0, $exitCode);

        $exitCode = $this->commandTester->execute(['email' => self::TEST_EMAIL]);
        self::assertNotSame(0, $exitCode);

        $users = $this->userRepository->findBy(['email' => self::TEST_EMAIL]);
        self::assertCount(1, $users);
    }
}
