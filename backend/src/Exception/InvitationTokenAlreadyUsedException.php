<?php

declare(strict_types=1);

namespace App\Exception;

class InvitationTokenAlreadyUsedException extends \RuntimeException implements ApiExceptionInterface
{
    public function getErrorCode(): string
    {
        return 'INVITATION_TOKEN_ALREADY_USED';
    }

    public function getStatusCode(): int
    {
        return 400;
    }
}
