<?php

namespace App\Dto\GroupMembership;

use App\Entity\Enum\UserGroupRoleEnum;
use Symfony\Component\Validator\Constraints as Assert;

class AddUserToGroupDto
{
    #[Assert\NotBlank(message: 'User ID is required')]
    #[Assert\Type(type: 'integer', message: 'User ID must be an integer')]
    #[Assert\Positive(message: 'User ID must be a positive integer')]
    public int $userId;

    #[Assert\NotNull(message: 'Role is required')]
    public UserGroupRoleEnum $role = UserGroupRoleEnum::MEMBER;

    public function __construct(
        int $userId,
        ?string $role = null,
    ) {
        $this->userId = $userId;

        if (null !== $role) {
            // Convert to lowercase to match enum backing values
            $this->role = UserGroupRoleEnum::from(strtolower($role));
        }
    }
}
