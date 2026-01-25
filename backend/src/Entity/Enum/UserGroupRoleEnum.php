<?php

namespace App\Entity\Enum;

enum UserGroupRoleEnum: string
{
    case OWNER = 'owner';
    case MEMBER = 'member';
}
