<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HealthCheck extends AbstractController
{
    #[Route('/api', methods: ['GET'])]
    public function healthCheck(): Response
    {
        return $this->json([
            'status' => 'ok'
        ]);
    }
}
