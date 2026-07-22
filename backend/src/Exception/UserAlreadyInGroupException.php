<?php

declare(strict_types=1);

namespace App\Exception;

class UserAlreadyInGroupException extends \RuntimeException implements ApiExceptionInterface
{
    public function __construct(int $userId, int $groupId)
    {
        parent::__construct("User with ID {$userId} is already a member of group {$groupId}");
    }

    public function getErrorCode(): string
    {
        return 'USER_ALREADY_IN_GROUP';
    }

    public function getStatusCode(): int
    {
        return 400;
    }
}
