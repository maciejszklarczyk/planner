<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\User\EditUserDto;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[OA\Tag(name: 'User')]
class UserController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/user', name: 'edit_user', methods: ['PUT'])]
    public function editUser(#[MapRequestPayload] EditUserDto $dto, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->hasRole('ROLE_ADMIN') || $user->getId() === $dto->id) {
            $userToEdit = $this->entityManager->find(User::class, $dto->id);

            if (!$userToEdit) {
                return $this->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
            }

            $userToEdit->updateFromDto($dto);
            $this->entityManager->flush();

            return $this->json([
                'user' => EditUserDto::fromEntity($userToEdit),
            ]);
        }

        return $this->json(['message' => 'Missing permission to update user entity.'], Response::HTTP_FORBIDDEN);
    }

    #[Route('/user/{userId}', name: 'delete_user', methods: ['DELETE'])]
    public function deleteUser(int $userId, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->hasRole('ROLE_ADMIN')) {
            $userToRemove = $this->entityManager->find(User::class, $userId);

            if (!$userToRemove) {
                return $this->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
            }

            $userToRemove->setDeletedAt(new \DateTime());
            $this->entityManager->flush();

            return $this->json([]);
        }

        return $this->json(['message' => 'Missing permission to remove user entity.'], Response::HTTP_FORBIDDEN);
    }
}
