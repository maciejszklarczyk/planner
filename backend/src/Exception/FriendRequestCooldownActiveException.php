<?php

declare(strict_types=1);

namespace App\Exception;

class FriendRequestCooldownActiveException extends \RuntimeException implements ApiExceptionInterface
{
    public function getErrorCode(): string
    {
        return 'FRIEND_REQUEST_COOLDOWN_ACTIVE';
    }

    public function getStatusCode(): int
    {
        return 429;
    }
}
