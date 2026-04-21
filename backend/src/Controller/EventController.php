<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Response\EventItemDto;
use App\Dto\Response\GroupListItemDto;
use App\Entity\Event;
use App\Entity\Group;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EventController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly EventRepository $eventRepository)
    {
    }

    #[Route('/events', name: 'get_events', methods: ['GET'])]
    public function index(): Response
    {
        $events = $this->eventRepository->findAll();
        return $this->json([
            'data' => EventItemDto::fromEntities($events),
        ]);
    }

    #[Route('/events/{event}', name: 'get_event', methods: ['GET'])]
    public function getGroup(#[MapEntity(id: 'event')] Event $event): JsonResponse
    {
        return $this->json(EventItemDto::fromEntity($event));
    }
}
