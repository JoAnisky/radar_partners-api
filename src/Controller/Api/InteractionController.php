<?php

namespace App\Controller\Api;

use App\Api\CompanyPresenter;
use App\Api\InteractionInput;
use App\Api\InteractionUpdater;
use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\Interaction;
use App\Repository\InteractionRepository;
use App\Service\PipelineStatusResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class InteractionController extends AbstractController
{
    public function __construct(
        private readonly InteractionRepository $interactions,
        private readonly InteractionUpdater $updater,
        private readonly PipelineStatusResolver $statusResolver,
        private readonly CompanyPresenter $presenter,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/companies/{id}/interactions', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function create(Company $company, #[MapRequestPayload] InteractionInput $input): JsonResponse
    {
        $contact = null;
        if (null !== $input->contactId) {
            $contact = $company->getContacts()->findFirst(static fn (int $key, Contact $c): bool => $c->getId() === $input->contactId);
            if (null === $contact) {
                throw new UnprocessableEntityHttpException("Ce contact n'appartient pas à cette entreprise.");
            }
        }

        $previous = $this->interactions->findByCompany($company);
        $interaction = (new Interaction($company, $input->channel, $input->occurredAt ?? new \DateTimeImmutable()))
            ->setContact($contact)
            ->setOutcome($input->outcome)
            ->setOrigin($input->origin)
            ->setNotes($input->notes)
            ->setNextActionAt($input->nextActionAt);
        $this->em->persist($interaction);
        $this->statusResolver->register($company, $interaction, $previous);
        $this->em->flush();

        $last = array_reduce(
            $previous,
            static fn (\DateTimeImmutable $carry, Interaction $i): \DateTimeImmutable => max($carry, $i->getOccurredAt()),
            $interaction->getOccurredAt(),
        );

        return $this->json([
            'interaction' => $this->presenter->interaction($interaction),
            'company' => $this->presenter->summary($company, $last),
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/interactions/{id}', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(Interaction $interaction, Request $request): JsonResponse
    {
        $company = $interaction->getCompany();
        $interactions = $this->interactions->findByCompany($company);
        $before = $this->statusResolver->derive($interactions);

        if ($this->updater->update($interaction, $request->toArray())) {
            $this->statusResolver->followEdit($company, $before, $this->statusResolver->derive($interactions));
        }
        $this->em->flush();

        $last = array_reduce(
            $interactions,
            static fn (?\DateTimeImmutable $carry, Interaction $i): \DateTimeImmutable => null === $carry ? $i->getOccurredAt() : max($carry, $i->getOccurredAt()),
        );

        return $this->json([
            'interaction' => $this->presenter->interaction($interaction),
            'company' => $this->presenter->summary($company, $last),
        ]);
    }
}
