<?php

declare(strict_types=1);

namespace App\Exception;

class AvatarFileTooLargeException extends \RuntimeException implements ApiExceptionInterface
{
    public function getErrorCode(): string
    {
        return 'AVATAR_FILE_TOO_LARGE';
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
