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

    /**
     * Statuses other users cannot find or send friend requests to.
     *
     * @return list<self>
     */
    public static function hiddenFromOtherUsers(): array
    {
        return [self::INACTIVE, self::BLOCKED, self::DELETED];
    }

    public function isHiddenFromOtherUsers(): bool
    {
        return in_array($this, self::hiddenFromOtherUsers(), true);
    }
}
