<?php

declare(strict_types=1);

namespace App\Exception;

class UserAlreadyCompletedRegistrationException extends \RuntimeException implements ApiExceptionInterface
{
    public function getErrorCode(): string
    {
        return 'USER_ALREADY_COMPLETED_REGISTRATION';
    }

    public function getStatusCode(): int
    {
        return 400;
    }
}
