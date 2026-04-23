<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Event\CreateEventDto;
use App\Dto\Event\UpdateEventDto;
use App\Dto\Response\EventItemDto;
use App\Entity\Event;
use App\Repository\EventRepository;
use App\Service\EventMapper;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Event')]
final class EventController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
        private readonly EventMapper $eventMapper,
    ) {
    }

    #[Route('/events', name: 'get_events', methods: ['GET'])]
    public function getEventsCollection(): Response
    {
        $events = $this->eventRepository->findAll();

        return $this->json([
            'data' => EventItemDto::fromEntities($events),
        ]);
    }

    #[Route('/events/{event}', name: 'get_event', methods: ['GET'])]
    public function getEvent(#[MapEntity(id: 'event')] Event $event): JsonResponse
    {
        return $this->json(EventItemDto::fromEntity($event));
    }

    #[Route('/events', name: 'post_event', methods: ['POST'])]
    public function createEvent(#[MapRequestPayload] CreateEventDto $dto): JsonResponse
    {
        $event = $this->eventMapper->fromDto($dto);
        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $this->json(['id' => $event->getId()], Response::HTTP_CREATED);
    }

    #[Route('/events/{event}', name: 'put_event', methods: ['PUT'])]
    public function updateEvent(#[MapEntity(id: 'event')] Event $event, #[MapRequestPayload] UpdateEventDto $dto): JsonResponse
    {
        $this->eventMapper->updateFromDto($event, $dto);
        $this->entityManager->flush();

        return $this->json(EventItemDto::fromEntity($event), Response::HTTP_OK);
    }

    #[Route('/events/{event}', name: 'delete_event', methods: ['DELETE'])]
    public function deleteEvent(#[MapEntity(id: 'event')] Event $event): JsonResponse
    {
        $event->setDeletedAt(new \DateTime());
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
