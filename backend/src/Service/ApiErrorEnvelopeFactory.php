<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

class ApiErrorEnvelopeFactory
{
    /**
     * @param list<array{field: string, message: string}>|null $violations
     *
     * @return array{error: string, message: string, timestamp: string, path: string, violations?: list<array{field: string, message: string}>}
     */
    public function build(string $errorCode, string $message, Request $request, ?array $violations = null): array
    {
        $envelope = [
            'error' => $errorCode,
            'message' => $message,
            'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'path' => $request->getPathInfo(),
        ];

        if (null !== $violations) {
            $envelope['violations'] = $violations;
        }

        return $envelope;
    }
}
