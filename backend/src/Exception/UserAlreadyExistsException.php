<?php

declare(strict_types=1);

namespace App\Exception;

class UserAlreadyExistsException extends \RuntimeException implements ApiExceptionInterface
{
    public function getErrorCode(): string
    {
        return 'USER_ALREADY_EXISTS';
    }

    public function getStatusCode(): int
    {
        return 400;
    }
}
