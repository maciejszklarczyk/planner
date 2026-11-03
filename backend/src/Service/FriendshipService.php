<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Enum\FriendshipStatusEnum;
use App\Entity\FriendRequest;
use App\Entity\User;
use App\Exception\AlreadyFriendsException;
use App\Exception\CannotFriendSelfException;
use App\Exception\DuplicateFriendRequestException;
use App\Exception\FriendRequestCooldownActiveException;
use App\Exception\FriendRequestNotPendingException;
use App\Exception\UserNotFoundByEmailException;
use App\Repository\FriendRequestRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class FriendshipService
{
    public function __construct(
        private EntityManagerInterface $em,
        private FriendRequestRepository $friendRequestRepository,
        private UserRepository $userRepository,
        private ClockInterface $clock,
        #[Autowire(env: 'int:FRIEND_REQUEST_COOLDOWN_DAYS')]
        private int $cooldownDays,
        private ManagerRegistry $registry,
    ) {
    }

    /**
     * @throws UserNotFoundByEmailException         if no user has that email, or that user is hidden from other users
     * @throws CannotFriendSelfException            if requesting oneself
     * @throws AlreadyFriendsException              if the pair is already friends
     * @throws DuplicateFriendRequestException      if a pending request already exists in the same direction
     * @throws FriendRequestCooldownActiveException if a recent decline is still within the cooldown window
     */
    public function sendRequest(User $requester, string $addresseeEmail): FriendRequest
    {
        $requesterId = $requester->getId() ?? throw new \LogicException('Requester must have an ID.');

        try {
            return $this->applySendRules($this->em, $this->friendRequestRepository, $this->userRepository, $requester, $addresseeEmail);
        } catch (UniqueConstraintViolationException) {
            // A concurrent send for the same pair got past our active-row check first; the DB index
            // rejected our insert. Doctrine has closed the EntityManager, so re-run the rules once on a
            // fresh one — they now see the competing row (e.g. a crossed request to auto-accept).
            $this->registry->resetManager();
            $em = $this->registry->getManagerForClass(FriendRequest::class);
            \assert($em instanceof EntityManagerInterface);
            $friendRequestRepository = $em->getRepository(FriendRequest::class);
            $userRepository = $em->getRepository(User::class);
            \assert($friendRequestRepository instanceof FriendRequestRepository);
            \assert($userRepository instanceof UserRepository);
            $freshRequester = $em->find(User::class, $requesterId) ?? throw new \LogicException('Requester disappeared during retry.');

            try {
                return $this->applySendRules($em, $friendRequestRepository, $userRepository, $freshRequester, $addresseeEmail);
            } catch (UniqueConstraintViolationException) {
                throw new DuplicateFriendRequestException("A pending friend request already exists between {$requesterId} and {$addresseeEmail}.");
            }
        }
    }

    /**
     * The send rule chain: resolve addressee -> self -> hidden status -> active row -> cooldown -> create.
     *
     * @throws UniqueConstraintViolationException if a concurrent send created an active row for the pair
     */
    private function applySendRules(
        EntityManagerInterface $em,
        FriendRequestRepository $friendRequestRepository,
        UserRepository $userRepository,
        User $requester,
        string $addresseeEmail,
    ): FriendRequest {
        $addressee = $userRepository->findOneBy(['email' => $addresseeEmail]);
        if (!$addressee) {
            throw $this->userNotFound($addresseeEmail);
        }

        $requesterId = $requester->getId() ?? throw new \LogicException('Requester must have an ID.');
        $addresseeId = $addressee->getId() ?? throw new \LogicException('Addressee must have an ID.');

        if ($requesterId === $addresseeId) {
            throw new CannotFriendSelfException('Cannot send a friend request to yourself.');
        }

        // Must run before the active-row check: a pending request *from* a now-hidden user
        // must not be auto-accepted. Same error as an unknown email, so status isn't revealed.
        if ($addressee->getStatus()?->isHiddenFromOtherUsers()) {
            throw $this->userNotFound($addresseeEmail);
        }

        $active = $friendRequestRepository->findActiveBetween($requesterId, $addresseeId);
        if ($active) {
            if (FriendshipStatusEnum::ACCEPTED === $active->getStatus()) {
                throw new AlreadyFriendsException("Users {$requesterId} and {$addresseeId} are already friends.");
            }

            // Pending row already exists. If it was sent by the current addressee to the current requester
            // (i.e. reversed), this send auto-accepts it instead of creating a duplicate.
            if ($active->getRequester()?->getId() === $addresseeId) {
                $active->setStatus(FriendshipStatusEnum::ACCEPTED);
                $active->setRespondedAt($this->clock->now());
                $em->flush();

                return $active;
            }

            throw new DuplicateFriendRequestException("A pending friend request already exists between {$requesterId} and {$addresseeId}.");
        }

        $latestDeclined = $friendRequestRepository->findLatestDeclinedBySender($requesterId, $addresseeId);
        if ($latestDeclined) {
            $respondedAt = $latestDeclined->getRespondedAt() ?? throw new \LogicException('Declined FriendRequest must have a respondedAt.');
            $cooldownEnd = $respondedAt->modify("+{$this->cooldownDays} days");
            if ($this->clock->now() < $cooldownEnd) {
                throw new FriendRequestCooldownActiveException("You must wait until {$cooldownEnd->format(DATE_ATOM)} before re-sending a request to {$addresseeId}.");
            }
        }

        $friendRequest = new FriendRequest();
        $friendRequest->setRequester($requester);
        $friendRequest->setAddressee($addressee);
        $friendRequest->setStatus(FriendshipStatusEnum::PENDING);

        $em->persist($friendRequest);
        $em->flush();

        return $friendRequest;
    }

    /**
     * @throws FriendRequestNotPendingException if the request is not pending
     */
    public function acceptRequest(FriendRequest $request): FriendRequest
    {
        if (FriendshipStatusEnum::PENDING !== $request->getStatus()) {
            throw new FriendRequestNotPendingException("Friend request {$request->getId()} is not pending.");
        }

        $request->setStatus(FriendshipStatusEnum::ACCEPTED);
        $request->setRespondedAt($this->clock->now());
        $this->em->flush();

        return $request;
    }

    /**
     * @throws FriendRequestNotPendingException if the request is not pending
     */
    public function declineRequest(FriendRequest $request): FriendRequest
    {
        if (FriendshipStatusEnum::PENDING !== $request->getStatus()) {
            throw new FriendRequestNotPendingException("Friend request {$request->getId()} is not pending.");
        }

        $request->setStatus(FriendshipStatusEnum::DECLINED);
        $request->setRespondedAt($this->clock->now());
        $this->em->flush();

        return $request;
    }

    /**
     * Withdraws a still-pending request sent by its own requester. Deliberately does not
     * touch the decline cooldown — findLatestDeclinedBySender() only reads `status = declined`,
     * so a cancelled row is invisible to the cooldown check.
     *
     * @throws FriendRequestNotPendingException if the request is not pending
     */
    public function cancelRequest(FriendRequest $request): FriendRequest
    {
        if (FriendshipStatusEnum::PENDING !== $request->getStatus()) {
            throw new FriendRequestNotPendingException("Friend request {$request->getId()} is not pending.");
        }

        $request->setStatus(FriendshipStatusEnum::CANCELLED);
        $request->setRespondedAt($this->clock->now());
        $this->em->flush();

        return $request;
    }

    private function userNotFound(string $email): UserNotFoundByEmailException
    {
        return new UserNotFoundByEmailException("No user found with email {$email}");
    }

    /**
     * @return User[]
     */
    public function listFriends(User $user): array
    {
        $userId = $user->getId() ?? throw new \LogicException('User must have an ID.');

        return array_map(
            fn (FriendRequest $fr) => $fr->getRequester()?->getId() === $userId ? $fr->getAddressee() : $fr->getRequester(),
            $this->friendRequestRepository->findAcceptedForUser($userId)
        );
    }

    /**
     * @return array{incoming: FriendRequest[], outgoing: FriendRequest[]}
     */
    public function listPending(User $user): array
    {
        $userId = $user->getId() ?? throw new \LogicException('User must have an ID.');

        return $this->friendRequestRepository->findPendingForUser($userId);
    }
}
