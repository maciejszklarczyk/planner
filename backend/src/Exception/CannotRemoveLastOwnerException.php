<?php

declare(strict_types=1);

namespace App\Exception;

class CannotRemoveLastOwnerException extends \RuntimeException implements ApiExceptionInterface
{
    public function __construct(int $groupId)
    {
        parent::__construct("Cannot remove the last owner from group {$groupId}");
    }

    public function getErrorCode(): string
    {
        return 'CANNOT_REMOVE_LAST_OWNER';
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
