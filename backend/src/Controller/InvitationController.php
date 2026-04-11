<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\User\InvitationCompleteDto;
use App\Entity\Enum\UserStatusEnum;
use App\Entity\UserInvitationToken;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
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
        $invitationToken = $this->entityManager->getRepository(UserInvitationToken::class)->findOneBy(['token' => $token]);
        if (!$invitationToken) {
            return $this->json(['valid' => false, 'message' => 'Invalid token.'], 400);
        }

        if ($invitationToken->getUsedAt()) {
            return $this->json(['valid' => false, 'message' => 'Token already used.'], 400);
        }

        if ($invitationToken->getExpiresAt() < new \DateTimeImmutable()) {
            return $this->json(['valid' => false, 'message' => 'Token expired.'], 400);
        }

        return $this->json(['valid' => true]);
    }

    #[Route('/invitation/complete', name: 'complete-invitation', methods: ['POST'])]
    public function complete(#[MapRequestPayload] InvitationCompleteDto $dto): JsonResponse
    {
        $invitationToken = $this->entityManager->getRepository(UserInvitationToken::class)->findOneBy(['token' => $dto->token]);
        if (!$invitationToken) {
            return $this->json(['valid' => false, 'message' => 'Invalid token.'], 400);
        }

        if ($invitationToken->getUsedAt()) {
            return $this->json(['valid' => false, 'message' => 'Token already used.'], 400);
        }

        if ($invitationToken->getExpiresAt() < new \DateTimeImmutable()) {
            return $this->json(['valid' => false, 'message' => 'Token expired.'], 400);
        }

        $user = $this->userRepository->findOneBy(['email' => $invitationToken->getEmail()]);
        if (!$user) {
            return $this->json(['valid' => false, 'message' => 'User not found.'], 404);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));
        $user->setStatus(UserStatusEnum::ACTIVE);
        $invitationToken->setUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json(['valid' => true]);
    }
}
