<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Enum\UserGroupRoleEnum;
use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserHasGroup;
use App\Security\GroupVoter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

#[CoversClass(GroupVoter::class)]
class GroupVoterTest extends TestCase
{
    private AccessDecisionManagerInterface&Stub $accessDecisionManager;
    private GroupVoter $voter;

    protected function setUp(): void
    {
        $this->accessDecisionManager = $this->createStub(AccessDecisionManagerInterface::class);
        $this->voter = new GroupVoter($this->accessDecisionManager);
    }

    #[DataProvider('voteDataProvider')]
    public function testVote(
        string $attribute,
        string $subjectType,
        ?string $userRole,
        bool $isAdmin,
        int $expected,
    ): void {
        $user = null !== $userRole ? new User() : null;
        $group = new Group();
        $group->setName('Test Group');

        if (null !== $user && in_array($userRole, ['owner', 'member'], true)) {
            $role = 'owner' === $userRole ? UserGroupRoleEnum::OWNER : UserGroupRoleEnum::MEMBER;
            $uhg = new UserHasGroup();
            $uhg->setRole($role);
            $group->addUserHasGroup($uhg);
            $user->addUserHasGroup($uhg);
        }

        $subject = 'group' === $subjectType ? $group : new \stdClass();

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->accessDecisionManager
            ->method('decide')
            ->willReturn($isAdmin);

        $result = $this->voter->vote($token, $subject, [$attribute]);

        $this->assertSame($expected, $result);
    }

    public static function voteDataProvider(): array
    {
        return [
            'abstain: unsupported attribute' => ['edit',   'group',     'user',   false, VoterInterface::ACCESS_ABSTAIN],
            'abstain: non-Group subject' => ['view',   'non-group', 'user',   false, VoterInterface::ACCESS_ABSTAIN],
            'deny: unauthenticated + view' => ['view',   'group',     null,     false, VoterInterface::ACCESS_DENIED],
            'deny: unauthenticated + delete' => ['delete', 'group',     null,     false, VoterInterface::ACCESS_DENIED],
            'grant: admin + view' => ['view',   'group',     'user',   true,  VoterInterface::ACCESS_GRANTED],
            'grant: admin + delete' => ['delete', 'group',     'user',   true,  VoterInterface::ACCESS_GRANTED],
            'grant: view → owner' => ['view',   'group',     'owner',  false, VoterInterface::ACCESS_GRANTED],
            'grant: view → member' => ['view',   'group',     'member', false, VoterInterface::ACCESS_GRANTED],
            'deny: view → non-member' => ['view',   'group',     'user',   false, VoterInterface::ACCESS_DENIED],
            'grant: delete → owner' => ['delete', 'group',     'owner',  false, VoterInterface::ACCESS_GRANTED],
            'deny: delete → member' => ['delete', 'group',     'member', false, VoterInterface::ACCESS_DENIED],
            'deny: delete → non-member' => ['delete', 'group',     'user',   false, VoterInterface::ACCESS_DENIED],
        ];
    }
}
