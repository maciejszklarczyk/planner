<?php

declare(strict_types=1);

namespace App\Exception;

class InsufficientPermissionException extends \RuntimeException implements ApiExceptionInterface
{
    public function getErrorCode(): string
    {
        return 'INSUFFICIENT_PERMISSION';
    }

    public function getStatusCode(): int
    {
        return 403;
    }
}
