<?php

declare(strict_types=1);

namespace App\Exception;

class InvitationTokenExpiredException extends \RuntimeException implements ApiExceptionInterface
{
    public function getErrorCode(): string
    {
        return 'INVITATION_TOKEN_EXPIRED';
    }

    public function getStatusCode(): int
    {
        return 400;
    }
}
