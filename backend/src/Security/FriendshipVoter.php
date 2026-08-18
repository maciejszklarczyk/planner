<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\FriendRequest;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class FriendshipVoter extends Voter
{
    public const ACCEPT = 'accept';
    public const DECLINE = 'decline';
    public const CANCEL = 'cancel';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::ACCEPT, self::DECLINE, self::CANCEL], true)) {
            return false;
        }

        return $subject instanceof FriendRequest;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            $vote?->addReason('The user is not logged in.');

            return false;
        }

        /** @var FriendRequest $friendRequest */
        $friendRequest = $subject;

        return match ($attribute) {
            self::ACCEPT, self::DECLINE => $this->canRespond($friendRequest, $user, $vote),
            self::CANCEL => $this->canCancel($friendRequest, $user, $vote),
            default => throw new \LogicException('This code should not be reached!'),
        };
    }

    private function canRespond(FriendRequest $friendRequest, User $user, ?Vote $vote): bool
    {
        if ($user === $friendRequest->getAddressee()) {
            return true;
        }

        $vote?->addReason(sprintf(
            'The logged in user (username: %s) is not the addressee of this friend request (id: %d).',
            $user->getEmail(),
            $friendRequest->getId()
        ));

        return false;
    }

    private function canCancel(FriendRequest $friendRequest, User $user, ?Vote $vote): bool
    {
        if ($user === $friendRequest->getRequester()) {
            return true;
        }

        $vote?->addReason(sprintf(
            'The logged in user (username: %s) is not the requester of this friend request (id: %d).',
            $user->getEmail(),
            $friendRequest->getId()
        ));

        return false;
    }
}
