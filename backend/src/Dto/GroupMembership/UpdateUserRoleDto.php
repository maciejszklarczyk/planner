<?php

namespace App\Dto\GroupMembership;

use App\Entity\Enum\UserGroupRoleEnum;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateUserRoleDto
{
    #[Assert\NotBlank(message: 'Role is required')]
    public UserGroupRoleEnum $role;

    public function __construct(string $role)
    {
        // Convert to lowercase to match enum backing values
        $this->role = UserGroupRoleEnum::from(strtolower($role));
    }
}
