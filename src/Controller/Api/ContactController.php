<?php

namespace App\Controller\Api;

use App\Api\CompanyPresenter;
use App\Api\ContactListQuery;
use App\Api\ContactUpdater;
use App\Entity\Company;
use App\Entity\Contact;
use App\Repository\ContactRepository;
use App\Repository\InteractionRepository;
use App\Scoring\CompanyScorer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    public function __construct(
        private readonly ContactRepository $contacts,
        private readonly InteractionRepository $interactions,
        private readonly ContactUpdater $updater,
        private readonly CompanyPresenter $presenter,
        private readonly CompanyScorer $scorer,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/contacts', methods: ['GET'])]
    public function list(#[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ?ContactListQuery $query = null): JsonResponse
    {
        $query ??= new ContactListQuery();
        ['items' => $items, 'total' => $total] = $this->contacts->search($query);
        $lastDates = $this->contacts->lastInteractionDates($items);

        return $this->json([
            'items' => array_map(fn (Contact $c): array => $this->presenter->contactListItem($c, $lastDates[$c->getId()] ?? null), $items),
            'total' => $total,
            'page' => $query->page,
            'perPage' => $query->perPage,
        ]);
    }

    #[Route('/api/companies/{id}/contacts', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function create(Company $company, Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $contact = new Contact($company, ContactUpdater::requiredName($payload));

        try {
            $this->updater->update($contact, $payload);
        } catch (\Throwable $e) {
            $company->removeContact($contact);
            throw $e;
        }

        $this->em->persist($contact);
        $this->em->flush();
        $this->scorer->rescore($company);
        $this->em->flush();

        return $this->json($this->presenter->contact($contact), Response::HTTP_CREATED);
    }

    #[Route('/api/contacts/{id}', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(Contact $contact, Request $request): JsonResponse
    {
        $this->updater->update($contact, $request->toArray());
        $this->em->flush();
        $this->scorer->rescore($contact->getCompany());
        $this->em->flush();

        return $this->json($this->presenter->contact($contact));
    }

    /** Suppression d'un contact (RGPD) : ses interactions sont conservées, simplement détachées. */
    #[Route('/api/contacts/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Contact $contact): Response
    {
        foreach ($this->interactions->findByContact($contact) as $interaction) {
            $interaction->setContact(null);
        }

        $company = $contact->getCompany();
        $company->removeContact($contact);
        $this->em->remove($contact);
        $this->em->flush();
        $this->scorer->rescore($company);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
