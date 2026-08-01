<?php

declare(strict_types=1);

namespace App\Exception;

class FriendRequestNotPendingException extends \RuntimeException implements ApiExceptionInterface
{
    public function getErrorCode(): string
    {
        return 'FRIEND_REQUEST_NOT_PENDING';
    }

    public function getStatusCode(): int
    {
        return 409;
    }
}
