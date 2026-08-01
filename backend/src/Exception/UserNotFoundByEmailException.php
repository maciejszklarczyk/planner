<?php

declare(strict_types=1);

namespace App\Exception;

class UserNotFoundByEmailException extends \RuntimeException implements ApiExceptionInterface
{
    public function getErrorCode(): string
    {
        return 'USER_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return 404;
    }
}
