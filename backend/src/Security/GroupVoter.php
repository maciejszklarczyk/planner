<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Group;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class GroupVoter extends Voter
{
    public function __construct(private readonly AccessDecisionManagerInterface $accessDecisionManager)
    {
    }
    public const VIEW = 'view';
    public const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::DELETE])) {
            return false;
        }

        if (!$subject instanceof Group) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            $vote?->addReason('The user is not logged in.');

            return false;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        /** @var Group $group */
        $group = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($group, $user, $vote),
            self::DELETE => $this->canDelete($group, $user, $vote),
            default => throw new \LogicException('This code should not be reached!'),
        };
    }

    private function canView(Group $group, User $user, ?Vote $vote): bool
    {
        if ($user === $group->getGroupOwnerUser()) {
            return true;
        }

        if ($user->isMemberOf($group)) {
            return true;
        }

        $vote?->addReason(sprintf(
            'The logged in user (username: %s) is not the owner of this group (id: %d).',
            $user->getEmail(),
            $group->getId()
        ));

        return false;
    }

    private function canDelete(Group $group, User $user, ?Vote $vote): bool
    {
        if ($user === $group->getGroupOwnerUser()) {
            return true;
        }

        $vote?->addReason(sprintf(
            'The logged in user (username: %s) is not the owner of this group (id: %d).',
            $user->getEmail(),
            $group->getId()
        ));

        return false;
    }
}
