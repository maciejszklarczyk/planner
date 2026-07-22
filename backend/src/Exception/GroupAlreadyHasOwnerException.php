<?php

declare(strict_types=1);

namespace App\Exception;

class GroupAlreadyHasOwnerException extends \RuntimeException implements ApiExceptionInterface
{
    public function __construct(int $groupId)
    {
        parent::__construct("Group {$groupId} already has an owner. Remove or change the current owner's role first.");
    }

    public function getErrorCode(): string
    {
        return 'GROUP_ALREADY_HAS_OWNER';
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
