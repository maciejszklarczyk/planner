<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\User\InvitationCompleteDto;
use App\Entity\Enum\UserStatusEnum;
use App\Entity\UserInvitationToken;
use App\Exception\InvitationTokenAlreadyUsedException;
use App\Exception\InvitationTokenExpiredException;
use App\Exception\InvitationTokenInvalidException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Invitation')]
class InvitationController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/invitation/verify', name: 'verify-invitation', methods: ['GET'])]
    public function verify(#[MapQueryParameter] string $token): JsonResponse
    {
        $invitationToken = $this->entityManager->getRepository(UserInvitationToken::class)->findOneBy(['token' => hash('sha256', $token)]);
        if (!$invitationToken) {
            throw new InvitationTokenInvalidException('Invalid token.');
        }

        if ($invitationToken->getUsedAt()) {
            throw new InvitationTokenAlreadyUsedException('Token already used.');
        }

        if ($invitationToken->getExpiresAt() < new \DateTimeImmutable()) {
            throw new InvitationTokenExpiredException('Token expired.');
        }

        return $this->json(['valid' => true]);
    }

    #[Route('/invitation/complete', name: 'complete-invitation', methods: ['POST'])]
    public function complete(#[MapRequestPayload] InvitationCompleteDto $dto): JsonResponse
    {
        $invitationToken = $this->entityManager->getRepository(UserInvitationToken::class)->findOneBy(['token' => hash('sha256', $dto->token)]);
        if (!$invitationToken) {
            throw new InvitationTokenInvalidException('Invalid token.');
        }

        if ($invitationToken->getUsedAt()) {
            throw new InvitationTokenAlreadyUsedException('Token already used.');
        }

        if ($invitationToken->getExpiresAt() < new \DateTimeImmutable()) {
            throw new InvitationTokenExpiredException('Token expired.');
        }

        $user = $this->userRepository->findOneBy(['email' => $invitationToken->getEmail()]);
        if (!$user) {
            throw new NotFoundHttpException('User not found.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));
        $user->setStatus(UserStatusEnum::ACTIVE);
        $invitationToken->setUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json(['valid' => true]);
    }
}
