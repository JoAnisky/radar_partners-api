<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Sonde de vivacité (Kubernetes, CI) : ne touche pas à la base. */
final class HealthController extends AbstractController
{
    #[Route('/api/health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json(['status' => 'ok']);
    }
}
