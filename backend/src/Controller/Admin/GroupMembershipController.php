<?php

namespace App\Controller\Admin;

use App\Dto\GroupMembership\AddUserToGroupDto;
use App\Dto\Response\GroupMembershipDto;
use App\Entity\User;
use App\Exception\UserAlreadyInGroupException;
use App\Service\GroupMembershipService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/groups')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Admin')]
class GroupMembershipController extends AbstractController
{
    public function __construct(
        private readonly GroupMembershipService $groupMembershipService,
    ) {
    }

    #[Route('/{groupId}/users', name: 'admin_group_add_user', methods: ['POST'])]
    public function addUser(
        int $groupId,
        #[MapRequestPayload] AddUserToGroupDto $dto,
        #[CurrentUser] User $currentUser,
    ): JsonResponse {
        try {
            $membership = $this->groupMembershipService->addUserToGroup(
                userId: $dto->userId,
                groupId: $groupId,
                role: $dto->role,
                addedBy: $currentUser
            );

            $membershipDto = GroupMembershipDto::fromEntity($membership);

            return $this->json($membershipDto, Response::HTTP_CREATED);
        } catch (NotFoundHttpException $e) {
            return $this->json([
                'error' => 'NOT_FOUND',
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (UserAlreadyInGroupException $e) {
            return $this->json([
                'error' => 'USER_ALREADY_IN_GROUP',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    // TODO: Implement endpoints:
    // - GET /admin/groups/{groupId}/users
    // - PATCH /admin/groups/{groupId}/users/{userId}/role
    // - DELETE /admin/groups/{groupId}/users/{userId}
}
