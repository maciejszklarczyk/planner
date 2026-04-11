<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Response\GroupListItemDto;
use App\Entity\Group;
use App\Repository\GroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Groups')]
class GroupController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly GroupRepository $groupRepository)
    {
    }

    #[Route('/groups', name: 'get_groups', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getGroups(Request $request): JsonResponse
    {
        $groups = $this->groupRepository->findAll();

        return $this->json([
            'data' => GroupListItemDto::fromEntities($groups),
        ]);
    }

    #[Route('/groups/{group}', name: 'get_group', methods: ['GET'])]
    #[IsGranted('view', 'group')]
    public function getGroup(#[MapEntity(id: 'group')] Group $group): JsonResponse
    {
        return $this->json(GroupListItemDto::fromEntity($group));
    }

    #[Route('/groups/{group}', name: 'delete_group', methods: ['DELETE'])]
    #[IsGranted('delete', 'group')]
    public function deleteGroup(#[MapEntity(id: 'group')] Group $group): JsonResponse
    {
        $group->setDeletedAt(new \DateTime());
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
