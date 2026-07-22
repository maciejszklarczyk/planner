<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Dto\GroupMembership\AddUserToGroupDto;
use App\Dto\GroupMembership\UpdateUserRoleDto;
use App\Dto\Response\GroupMembershipDto;
use App\Entity\User;
use App\Entity\UserHasGroup;
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
        $membership = $this->groupMembershipService->addUserToGroup(
            userId: $dto->userId,
            groupId: $groupId,
            role: $dto->role,
            addedBy: $currentUser
        );

        $membershipDto = GroupMembershipDto::fromEntity($membership);

        return $this->json($membershipDto, Response::HTTP_CREATED);
    }

    #[Route('/{groupId}/users', name: 'admin_group_list_users', methods: ['GET'])]
    public function listUsers(int $groupId): JsonResponse
    {
        $group = $this->groupRepository->find($groupId);
        if (!$group) {
            throw new NotFoundHttpException('Group not found.');
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
        $this->groupMembershipService->removeUserFromGroup($groupId, $userId);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{groupId}/users/{userId}/role', name: 'admin_group_update_user_role', methods: ['PATCH'])]
    public function updateUserRole(
        int $groupId,
        int $userId,
        #[MapRequestPayload] UpdateUserRoleDto $dto,
    ): JsonResponse {
        $membership = $this->groupMembershipService->updateUserRole($groupId, $userId, $dto->role);

        return $this->json(GroupMembershipDto::fromEntity($membership));
    }
}
