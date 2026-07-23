<?php

declare(strict_types=1);

namespace App\Exception;

interface ApiExceptionInterface
{
    public function getErrorCode(): string;

    public function getStatusCode(): int;
}
