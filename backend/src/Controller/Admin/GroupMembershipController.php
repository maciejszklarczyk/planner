<?php

namespace App\Controller\Admin;

use App\Dto\GroupMembership\AddUserToGroupDto;
use App\Dto\Response\GroupMembershipDto;
use App\Entity\User;
use App\Entity\UserHasGroup;
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

    // TODO: Implement endpoints:
    // - PATCH /admin/groups/{groupId}/users/{userId}/role
    // - DELETE /admin/groups/{groupId}/users/{userId}
}
