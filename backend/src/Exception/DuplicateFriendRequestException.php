<?php

declare(strict_types=1);

namespace App\Exception;

class DuplicateFriendRequestException extends \RuntimeException implements ApiExceptionInterface
{
    public function getErrorCode(): string
    {
        return 'DUPLICATE_FRIEND_REQUEST';
    }

    public function getStatusCode(): int
    {
        return 409;
    }
}
