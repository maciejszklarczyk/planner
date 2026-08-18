<?php

declare(strict_types=1);

namespace App\Exception;

class CannotFriendSelfException extends \RuntimeException implements ApiExceptionInterface
{
    public function getErrorCode(): string
    {
        return 'CANNOT_FRIEND_SELF';
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
