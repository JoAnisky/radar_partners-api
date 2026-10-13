<?php

namespace App\Controller\Api;

use App\Api\CompanyPresenter;
use App\Api\SignalListQuery;
use App\Entity\Company;
use App\Entity\Signal;
use App\Enum\PipelineStatus;
use App\Enum\SignalType;
use App\Repository\CompanyRepository;
use App\Repository\SignalRepository;
use App\Scoring\CompanyScorer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/signals')]
final class SignalController extends AbstractController
{
    public function __construct(
        private readonly SignalRepository $signals,
        private readonly CompanyRepository $companies,
        private readonly CompanyPresenter $presenter,
        private readonly CompanyScorer $scorer,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(#[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ?SignalListQuery $query = null): JsonResponse
    {
        $query ??= new SignalListQuery();
        ['items' => $items, 'total' => $total] = $this->signals->search($query);

        return $this->json([
            'items' => array_map($this->presenter->signal(...), $items),
            'total' => $total,
            'page' => $query->page,
            'perPage' => $query->perPage,
        ]);
    }

    /** Rattache le signal à une entreprise (`{"companyId": 12}`) ou le détache (`{"companyId": null}`). */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function attach(Signal $signal, Request $request): JsonResponse
    {
        $payload = $request->toArray();
        if (!\array_key_exists('companyId', $payload) || 1 !== \count($payload)) {
            throw new UnprocessableEntityHttpException('Le corps doit contenir uniquement companyId.');
        }

        $company = null;
        if (null !== $payload['companyId']) {
            $company = \is_int($payload['companyId']) ? $this->companies->find($payload['companyId']) : null;
            if (!$company instanceof Company) {
                throw new UnprocessableEntityHttpException('Entreprise introuvable.');
            }
        }

        $previous = $signal->getCompany();
        $changed = $company !== $previous;
        $signal->setCompany($company);

        // Une offre sur une entreprise en attente signale un nouveau besoin : elle remonte dans « à relancer ».
        if ($changed && null !== $company && PipelineStatus::OnHold === $company->getStatus()
            && \in_array($signal->getType(), [SignalType::JobPosting, SignalType::FreelanceMission], true)) {
            $company->setRecontactAfter(new \DateTimeImmutable());
        }

        $this->em->flush();
        foreach (array_filter([$company, $previous !== $company ? $previous : null]) as $affected) {
            $this->scorer->rescore($affected);
        }
        $this->em->flush();

        return $this->json($this->presenter->signal($signal));
    }
}
