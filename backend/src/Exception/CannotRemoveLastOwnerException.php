<?php

declare(strict_types=1);

namespace App\Exception;

class CannotRemoveLastOwnerException extends \RuntimeException implements ApiExceptionInterface
{
    public function getErrorCode(): string
    {
        return 'CANNOT_REMOVE_LAST_OWNER';
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
