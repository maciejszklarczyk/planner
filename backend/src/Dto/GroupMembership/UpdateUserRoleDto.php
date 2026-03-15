<?php

namespace App\Dto\GroupMembership;

use App\Entity\Enum\UserGroupRoleEnum;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateUserRoleDto
{
    #[Assert\NotNull(message: 'Role must be one of: owner, member')]
    public ?UserGroupRoleEnum $role;

    public function __construct(string $role)
    {
        $this->role = UserGroupRoleEnum::tryFrom(strtolower($role));
    }
}
