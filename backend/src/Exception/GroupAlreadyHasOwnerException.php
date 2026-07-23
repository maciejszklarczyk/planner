<?php

declare(strict_types=1);

namespace App\Exception;

class GroupAlreadyHasOwnerException extends \RuntimeException implements ApiExceptionInterface
{
    public function getErrorCode(): string
    {
        return 'GROUP_ALREADY_HAS_OWNER';
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
