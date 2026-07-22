<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\User\EditUserDto;
use App\Entity\User;
use App\Exception\AuthenticationRequiredException;
use App\Exception\InsufficientPermissionException;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
            throw new AuthenticationRequiredException('Missing credentials');
        }

        if ($user->hasRole('ROLE_ADMIN') || $user->getId() === $dto->id) {
            $userToEdit = $this->entityManager->find(User::class, $dto->id);

            if (!$userToEdit) {
                throw new NotFoundHttpException('User not found');
            }

            $userToEdit->updateFromDto($dto);
            $this->entityManager->flush();

            return $this->json([
                'user' => EditUserDto::fromEntity($userToEdit),
            ]);
        }

        throw new InsufficientPermissionException('Missing permission to update user entity.');
    }

    #[Route('/user/{userId}', name: 'delete_user', methods: ['DELETE'])]
    public function deleteUser(int $userId, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            throw new AuthenticationRequiredException('Missing credentials');
        }

        if ($user->hasRole('ROLE_ADMIN')) {
            $userToRemove = $this->entityManager->find(User::class, $userId);

            if (!$userToRemove) {
                throw new NotFoundHttpException('User not found');
            }

            $userToRemove->setDeletedAt(new \DateTime());
            $this->entityManager->flush();

            return $this->json([]);
        }

        throw new InsufficientPermissionException('Missing permission to remove user entity.');
    }
}
