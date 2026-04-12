<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

class HealthCheck extends AbstractController
{
    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function healthCheck(): Response
    {
        return $this->json([
            'status' => 'ok',
            'timestamp' => time(),
        ]);
    }

    #[Route('/version', name: 'app_version', methods: ['GET'])]
    public function version(KernelInterface $kernel): Response
    {
        $composerJson = $kernel->getProjectDir() . '/composer.json';
        $data = json_decode((string) file_get_contents($composerJson), true);

        return $this->json([
            'version' => $data['version'] ?? 'unknown',
        ]);
    }
}
