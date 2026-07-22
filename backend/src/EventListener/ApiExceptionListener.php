<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\ApiExceptionInterface;
use App\Service\ApiErrorEnvelopeFactory;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
class ApiExceptionListener
{
    private const STATUS_CODE_MAP = [
        400 => 'BAD_REQUEST',
        401 => 'AUTHENTICATION_REQUIRED',
        403 => 'FORBIDDEN',
        404 => 'NOT_FOUND',
        405 => 'METHOD_NOT_ALLOWED',
        409 => 'CONFLICT',
        422 => 'VALIDATION_ERROR',
        429 => 'TOO_MANY_REQUESTS',
    ];

    public function __construct(
        private readonly ApiErrorEnvelopeFactory $envelopeFactory,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        if ($exception instanceof ApiExceptionInterface) {
            $envelope = $this->envelopeFactory->build(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $request,
            );

            $event->setResponse(new JsonResponse($envelope, $exception->getStatusCode()));

            return;
        }

        if ($exception instanceof HttpExceptionInterface
            && $exception->getPrevious() instanceof ValidationFailedException) {
            $violations = [];
            foreach ($exception->getPrevious()->getViolations() as $violation) {
                $violations[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                ];
            }

            $envelope = $this->envelopeFactory->build(
                'VALIDATION_ERROR',
                $exception->getMessage(),
                $request,
                $violations,
            );

            $event->setResponse(new JsonResponse($envelope, 422));

            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            // getMessage() is passed through verbatim here — only throw HttpExceptionInterface
            // with messages safe to expose to the client.
            $statusCode = $exception->getStatusCode();
            $errorCode = self::STATUS_CODE_MAP[$statusCode] ?? 'HTTP_ERROR';

            $envelope = $this->envelopeFactory->build(
                $errorCode,
                $exception->getMessage(),
                $request,
            );

            $event->setResponse(new JsonResponse($envelope, $statusCode));

            return;
        }

        $envelope = $this->envelopeFactory->build(
            'INTERNAL_ERROR',
            'An unexpected error occurred.',
            $request,
        );

        $event->setResponse(new JsonResponse($envelope, 500));
    }
}
