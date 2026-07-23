<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\ApiErrorEnvelopeFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class JsonAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly ApiErrorEnvelopeFactory $envelopeFactory,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): JsonResponse
    {
        $envelope = $this->envelopeFactory->build(
            'AUTHENTICATION_REQUIRED',
            'Full authentication is required to access this resource',
            $request,
        );

        return new JsonResponse($envelope, Response::HTTP_UNAUTHORIZED);
    }
}
