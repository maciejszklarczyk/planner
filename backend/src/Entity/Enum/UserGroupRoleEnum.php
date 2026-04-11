<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum UserGroupRoleEnum: string
{
    case OWNER = 'owner';
    case MEMBER = 'member';
}
