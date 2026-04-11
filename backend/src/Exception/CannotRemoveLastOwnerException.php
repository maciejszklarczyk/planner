<?php

declare(strict_types=1);

namespace App\Exception;

class CannotRemoveLastOwnerException extends \RuntimeException
{
    public function __construct(int $groupId)
    {
        parent::__construct("Cannot remove the last owner from group {$groupId}");
    }
}
