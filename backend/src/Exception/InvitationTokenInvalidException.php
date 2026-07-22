<?php

declare(strict_types=1);

namespace App\Exception;

class InvitationTokenInvalidException extends \RuntimeException implements ApiExceptionInterface
{
    public function getErrorCode(): string
    {
        return 'INVITATION_TOKEN_INVALID';
    }

    public function getStatusCode(): int
    {
        return 400;
    }
}
