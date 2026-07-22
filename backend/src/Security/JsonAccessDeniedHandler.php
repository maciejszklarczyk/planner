<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\ApiErrorEnvelopeFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class JsonAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private readonly ApiErrorEnvelopeFactory $envelopeFactory,
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): JsonResponse
    {
        $envelope = $this->envelopeFactory->build(
            'ACCESS_DENIED',
            'You do not have sufficient permissions to access this resource',
            $request,
        );

        return new JsonResponse($envelope, Response::HTTP_FORBIDDEN);
    }
}
