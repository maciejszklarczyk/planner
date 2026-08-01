<?php

declare(strict_types=1);

namespace App\Exception;

class AlreadyFriendsException extends \RuntimeException implements ApiExceptionInterface
{
    public function getErrorCode(): string
    {
        return 'ALREADY_FRIENDS';
    }

    public function getStatusCode(): int
    {
        return 409;
    }
}
