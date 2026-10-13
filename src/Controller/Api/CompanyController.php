<?php

namespace App\Controller\Api;

use App\Api\CompanyInput;
use App\Api\CompanyListQuery;
use App\Api\CompanyPresenter;
use App\Api\CompanyUpdater;
use App\Entity\Company;
use App\Repository\CompanyRepository;
use App\Repository\InteractionRepository;
use App\Repository\SignalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/companies')]
final class CompanyController extends AbstractController
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly InteractionRepository $interactions,
        private readonly SignalRepository $signals,
        private readonly CompanyPresenter $presenter,
        private readonly CompanyUpdater $updater,
        private readonly EntityManagerInterface $em,
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

    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CompanyInput $input): JsonResponse
    {
        $existing = $this->companies->findOneByName($input->name);
        if (null !== $existing) {
            return $this->json(['error' => 'Une entreprise porte déjà ce nom.', 'id' => $existing->getId()], Response::HTTP_CONFLICT);
        }

        $company = (new Company(trim($input->name)))
            ->setScope($input->scope)
            ->setType($input->type)
            ->setWebsite($input->website)
            ->setCity($input->city)
            ->setNotes($input->notes);
        $this->em->persist($company);
        $this->em->flush();

        return $this->json($this->presenter->detail($company, [], []), Response::HTTP_CREATED);
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(Company $company, Request $request): JsonResponse
    {
        $this->updater->update($company, $request->toArray(), new \DateTimeImmutable());
        $this->em->flush();

        return $this->show($company);
    }
}
