<?php

declare(strict_types=1);

namespace App\Exception;

class AvatarFileTypeInvalidException extends \RuntimeException implements ApiExceptionInterface
{
    public function getErrorCode(): string
    {
        return 'AVATAR_FILE_TYPE_INVALID';
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
