<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Friendship\SendFriendRequestDto;
use App\Dto\Response\FriendRequestDto;
use App\Dto\Response\UserListItemDto;
use App\Entity\FriendRequest;
use App\Entity\User;
use App\Exception\FriendRequestNotFoundException;
use App\Repository\FriendRequestRepository;
use App\Security\FriendshipVoter;
use App\Service\FriendshipService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[OA\Tag(name: 'Friendship')]
class FriendshipController extends AbstractController
{
    public function __construct(
        private readonly FriendshipService $friendshipService,
        private readonly FriendRequestRepository $friendRequestRepository,
    ) {
    }

    #[Route('/friend-requests', name: 'send_friend_request', methods: ['POST'])]
    public function send(
        #[MapRequestPayload] SendFriendRequestDto $dto,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $request = $this->friendshipService->sendRequest($user, $dto->email);

        return $this->json(FriendRequestDto::fromEntity($request, $user), Response::HTTP_CREATED);
    }

    #[Route('/friend-requests', name: 'list_friend_requests', methods: ['GET'])]
    public function listPending(#[CurrentUser] User $user): JsonResponse
    {
        $pending = $this->friendshipService->listPending($user);

        return $this->json([
            'incoming' => FriendRequestDto::fromEntities($pending['incoming'], $user),
            'outgoing' => FriendRequestDto::fromEntities($pending['outgoing'], $user),
        ]);
    }

    #[Route('/friend-requests/{id}/accept', name: 'accept_friend_request', methods: ['POST'])]
    public function accept(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $request = $this->resolveFriendRequest($id, $user);
        $this->denyAccessUnlessGranted(FriendshipVoter::ACCEPT, $request);

        $accepted = $this->friendshipService->acceptRequest($request);

        return $this->json(FriendRequestDto::fromEntity($accepted, $user));
    }

    #[Route('/friend-requests/{id}/decline', name: 'decline_friend_request', methods: ['POST'])]
    public function decline(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $request = $this->resolveFriendRequest($id, $user);
        $this->denyAccessUnlessGranted(FriendshipVoter::DECLINE, $request);

        $declined = $this->friendshipService->declineRequest($request);

        return $this->json(FriendRequestDto::fromEntity($declined, $user));
    }

    #[Route('/friend-requests/{id}/cancel', name: 'cancel_friend_request', methods: ['POST'])]
    public function cancel(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $request = $this->resolveFriendRequest($id, $user);
        $this->denyAccessUnlessGranted(FriendshipVoter::CANCEL, $request);

        $cancelled = $this->friendshipService->cancelRequest($request);

        return $this->json(FriendRequestDto::fromEntity($cancelled, $user));
    }

    #[Route('/friends', name: 'list_friends', methods: ['GET'])]
    public function listFriends(#[CurrentUser] User $user): JsonResponse
    {
        $friends = $this->friendshipService->listFriends($user);

        return $this->json(['data' => UserListItemDto::fromEntities($friends)]);
    }

    /**
     * A request id that exists but belongs to neither side of the current user is treated the same
     * as one that doesn't exist at all, so a non-participant can't distinguish the two via 404 vs 403.
     */
    private function resolveFriendRequest(int $id, User $user): FriendRequest
    {
        $request = $this->friendRequestRepository->find($id);
        if (!$request || ($request->getRequester() !== $user && $request->getAddressee() !== $user)) {
            throw new FriendRequestNotFoundException("Friend request {$id} not found.");
        }

        return $request;
    }
}
