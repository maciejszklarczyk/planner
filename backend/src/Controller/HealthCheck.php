<?php

declare(strict_types=1);

namespace App\Controller;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

class HealthCheck extends AbstractController
{
    #[OA\Get(
        path: '/health',
        summary: 'Health check',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Application is healthy',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        new OA\Property(property: 'timestamp', type: 'integer', example: 1744000000),
                    ]
                )
            ),
        ]
    )]
    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function healthCheck(): Response
    {
        return $this->json([
            'status' => 'ok',
            'timestamp' => time(),
        ]);
    }

    #[OA\Get(
        path: '/version',
        summary: 'Get application version',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Application version',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'version', type: 'string', example: '0.0.3'),
                    ]
                )
            ),
        ]
    )]
    #[Route('/version', name: 'app_version', methods: ['GET'])]
    public function version(KernelInterface $kernel): Response
    {
        $composerJson = $kernel->getProjectDir().'/composer.json';
        $data = json_decode((string) file_get_contents($composerJson), true);

        return $this->json([
            'version' => $data['version'] ?? 'unknown',
        ]);
    }
}
