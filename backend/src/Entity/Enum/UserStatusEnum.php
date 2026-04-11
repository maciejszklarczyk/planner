<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum UserStatusEnum: string
{
    case NEW = 'new';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case BLOCKED = 'blocked';
    case DELETED = 'deleted';
}
