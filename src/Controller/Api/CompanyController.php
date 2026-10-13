<?php

namespace App\Controller\Api;

use App\Api\CompanyListQuery;
use App\Api\CompanyPresenter;
use App\Entity\Company;
use App\Repository\CompanyRepository;
use App\Repository\InteractionRepository;
use App\Repository\SignalRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/companies')]
final class CompanyController extends AbstractController
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly InteractionRepository $interactions,
        private readonly SignalRepository $signals,
        private readonly CompanyPresenter $presenter,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(#[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ?CompanyListQuery $query = null): JsonResponse
    {
        $query ??= new CompanyListQuery();
        ['items' => $items, 'total' => $total] = $this->companies->search($query, new \DateTimeImmutable());
        $lastDates = $this->companies->lastInteractionDates($items);

        return $this->json([
            'items' => array_map(fn (Company $c): array => $this->presenter->summary($c, $lastDates[$c->getId()] ?? null), $items),
            'total' => $total,
            'page' => $query->page,
            'perPage' => $query->perPage,
        ]);
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Company $company): JsonResponse
    {
        return $this->json($this->presenter->detail(
            $company,
            $this->interactions->findByCompany($company),
            $this->signals->findByCompany($company),
        ));
    }
}
