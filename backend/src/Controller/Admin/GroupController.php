<?php

namespace App\Controller\Admin;

use App\Dto\Response\GroupListItemDto;
use App\Entity\Group;
use App\Repository\GroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Admin')]
class GroupController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly GroupRepository $groupRepository)
    {
    }

    #[Route('/admin/groups', name: 'get_groups', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getGroups(Request $request): JsonResponse
    {
        $groups = $this->groupRepository->findAll();

        return $this->json([
            'data' => GroupListItemDto::fromEntities($groups),
        ]);
    }

    #[Route('/admin/groups/{groupId}', name: 'delete_group', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteGroup(int $groupId): JsonResponse
    {
        $groupToRemove = $this->entityManager->find(Group::class, $groupId);

        if (!$groupToRemove) {
            return $this->json(['message' => 'Group not found'], Response::HTTP_NOT_FOUND);
        }

        $groupToRemove->setDeletedAt(new \DateTime());
        $this->entityManager->flush();

        return $this->json([]);
    }
}
