<?php

declare(strict_types=1);

namespace App\Exception;

class FriendRequestNotFoundException extends \RuntimeException implements ApiExceptionInterface
{
    public function getErrorCode(): string
    {
        return 'FRIEND_REQUEST_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return 404;
    }
}
