<?php

namespace App\Controller\Api;

use App\Stats\StatsProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StatsController extends AbstractController
{
    public function __construct(private readonly StatsProvider $stats)
    {
    }

    #[Route('/api/stats', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json($this->stats->build(new \DateTimeImmutable()));
    }
}
