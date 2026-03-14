<?php

namespace App\Controller\Admin;

use App\Dto\GroupMembership\AddUserToGroupDto;
use App\Dto\GroupMembership\UpdateUserRoleDto;
use App\Dto\Response\GroupMembershipDto;
use App\Entity\User;
use App\Entity\UserHasGroup;
use App\Exception\CannotRemoveLastOwnerException;
use App\Exception\GroupAlreadyHasOwnerException;
use App\Exception\UserAlreadyInGroupException;
use App\Repository\GroupRepository;
use App\Repository\UserHasGroupRepository;
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
        private readonly GroupRepository $groupRepository,
        private readonly UserHasGroupRepository $userHasGroupRepository,
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
        } catch (GroupAlreadyHasOwnerException $e) {
            return $this->json([
                'error' => 'GROUP_ALREADY_HAS_OWNER',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/{groupId}/users', name: 'admin_group_list_users', methods: ['GET'])]
    public function listUsers(int $groupId): JsonResponse
    {
        $group = $this->groupRepository->find($groupId);
        if (!$group) {
            return $this->json(['error' => 'NOT_FOUND', 'message' => 'Group not found.'], Response::HTTP_NOT_FOUND);
        }

        $memberships = $this->userHasGroupRepository->findByGroup($groupId);

        return $this->json([
            'data' => array_map(
                fn (UserHasGroup $m) => GroupMembershipDto::fromEntity($m),
                $memberships
            ),
        ]);
    }

    #[Route('/{groupId}/users/{userId}', name: 'admin_group_remove_user', methods: ['DELETE'])]
    public function removeUser(int $groupId, int $userId): JsonResponse
    {
        try {
            $this->groupMembershipService->removeUserFromGroup($groupId, $userId);

            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (NotFoundHttpException $e) {
            return $this->json([
                'error' => 'NOT_FOUND',
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (CannotRemoveLastOwnerException $e) {
            return $this->json([
                'error' => 'CANNOT_REMOVE_LAST_OWNER',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/{groupId}/users/{userId}/role', name: 'admin_group_update_user_role', methods: ['PATCH'])]
    public function updateUserRole(
        int $groupId,
        int $userId,
        #[MapRequestPayload] UpdateUserRoleDto $dto,
    ): JsonResponse {
        try {
            $membership = $this->groupMembershipService->updateUserRole($groupId, $userId, $dto->role);

            return $this->json(GroupMembershipDto::fromEntity($membership));
        } catch (NotFoundHttpException $e) {
            return $this->json([
                'error' => 'NOT_FOUND',
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (CannotRemoveLastOwnerException $e) {
            return $this->json([
                'error' => 'CANNOT_REMOVE_LAST_OWNER',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (GroupAlreadyHasOwnerException $e) {
            return $this->json([
                'error' => 'GROUP_ALREADY_HAS_OWNER',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
