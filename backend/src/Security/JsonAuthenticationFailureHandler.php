<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\ApiErrorEnvelopeFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

class JsonAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private readonly ApiErrorEnvelopeFactory $envelopeFactory,
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        $envelope = $this->envelopeFactory->build(
            'AUTHENTICATION_FAILED',
            'Invalid credentials.',
            $request,
        );

        return new JsonResponse($envelope, Response::HTTP_UNAUTHORIZED);
    }
}
