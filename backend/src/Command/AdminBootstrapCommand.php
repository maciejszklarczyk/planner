<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Exception\UserAlreadyExistsException;
use App\Repository\UserRepository;
use App\Service\InvitationTokenService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:admin:bootstrap', description: 'Create a ROLE_ADMIN user and print an invitation link (recovery tool for a wiped database).')]
class AdminBootstrapCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly InvitationTokenService $invitationTokenService,
        #[Autowire(env: 'FRONTEND_URL')]
        private readonly string $frontendUrl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email address for the new ROLE_ADMIN user');
    }

    /**
     * @throws \Random\RandomException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error(sprintf('"%s" is not a valid email address.', $email));

            return Command::FAILURE;
        }

        try {
            $existingUser = $this->userRepository->findOneBy(['email' => $email]);
            if ($existingUser) {
                throw new UserAlreadyExistsException('User already exists.');
            }

            $newUser = new User();
            $newUser->setEmail($email);
            $newUser->setRoles(['ROLE_ADMIN']);
            $this->entityManager->persist($newUser);

            [$rawToken] = $this->invitationTokenService->createToken($email);
            $this->entityManager->flush();
        } catch (UserAlreadyExistsException|UniqueConstraintViolationException) {
            $io->error('User already exists.');

            return Command::FAILURE;
        }

        $io->success('Admin user created.');
        $io->writeln(sprintf('Email: %s', $email));
        $io->writeln(sprintf('Link:  %s/set-password?token=%s', $this->frontendUrl, $rawToken));

        return Command::SUCCESS;
    }
}
