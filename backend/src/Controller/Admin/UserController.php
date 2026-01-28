<?php

namespace App\Controller\Admin;

use App\Dto\Response\UserListItemDto;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('', name: 'admin_users_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // Get query parameters
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 50))); // max 100 items per page
        $search = $request->query->get('search');
        $excludeGroupId = $request->query->get('excludeGroupId')
            ? (int) $request->query->get('excludeGroupId')
            : null;

        // Fetch users with pagination and filters
        $users = $this->userRepository->findWithPagination(
            page: $page,
            limit: $limit,
            search: $search,
            excludeGroupId: $excludeGroupId
        );

        // Count total for pagination
        $total = $this->userRepository->countWithFilters(
            search: $search,
            excludeGroupId: $excludeGroupId
        );

        $userDtos = UserListItemDto::fromEntities($users);

        return $this->json([
            'data' => $userDtos,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => $total > 0 ? (int) ceil($total / $limit) : 0,
            ],
        ]);
    }
}
