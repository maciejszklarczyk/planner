<?php

declare(strict_types=1);

namespace App\Exception;

class AuthenticationRequiredException extends \RuntimeException implements ApiExceptionInterface
{
    public function getErrorCode(): string
    {
        return 'AUTHENTICATION_REQUIRED';
    }

    public function getStatusCode(): int
    {
        return 401;
    }
}
