<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class JsonAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): JsonResponse
    {
        return new JsonResponse(
            [
                'error' => 'AUTHENTICATION_REQUIRED',
                'message' => 'Full authentication is required to access this resource',
            ],
            Response::HTTP_UNAUTHORIZED
        );
    }
}
