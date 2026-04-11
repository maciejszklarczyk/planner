<?php

declare(strict_types=1);

namespace App\Exception;

class UserAlreadyInGroupException extends \RuntimeException
{
    public function __construct(int $userId, int $groupId)
    {
        parent::__construct("User with ID {$userId} is already a member of group {$groupId}");
    }
}
