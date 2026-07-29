<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\UserInvitationToken;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;

class InvitationTokenService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{string, UserInvitationToken} [rawToken, persistedEntity]
     *
     * @throws RandomException
     */
    public function createToken(string $email): array
    {
        $rawToken = bin2hex(random_bytes(32));
        $token = new UserInvitationToken(
            token: hash('sha256', $rawToken),
            email: $email,
        );
        $this->entityManager->persist($token);

        return [$rawToken, $token];
    }
}
