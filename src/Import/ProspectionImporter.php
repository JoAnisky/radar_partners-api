<?php

namespace App\Import;

use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\Interaction;
use App\Enum\Scope;
use App\Repository\CompanyRepository;
use App\Service\PipelineStatusResolver;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProspectionImporter
{
    public function __construct(
        private EntityManagerInterface $em,
        private CompanyRepository $companies,
        private PipelineStatusResolver $statusResolver,
    ) {
    }

    /** Retourne null si l'entreprise existe déjà. */
    public function import(ProspectionRow $row): ?Company
    {
        if (null !== $this->companies->findOneBy(['name' => $row->name])) {
            return null;
        }

        $company = (new Company($row->name))
            ->setScope(Scope::Local)
            ->setWebsite($row->website)
            ->setAddress($row->address)
            ->setPostalCode($row->postalCode)
            ->setCity($row->city)
            ->setPhone($row->phone ?? $row->contactPhone)
            ->setEmail($row->email)
            ->setNotes($row->notes)
            ->setFlags($row->flags);
        $this->em->persist($company);

        $contact = null;
        if (null !== $row->contactName) {
            $contact = (new Contact($company, $row->contactName))->setPhone($row->contactPhone);
            $this->em->persist($contact);
        }

        $interactions = [];
        foreach ($row->interactions as $data) {
            $interaction = (new Interaction($company, $data->channel, $data->occurredAt))
                ->setContact($contact)
                ->setOutcome($data->outcome)
                ->setOrigin($data->origin)
                ->setNotes($data->notes);
            $this->em->persist($interaction);
            $interactions[] = $interaction;
        }

        $this->statusResolver->apply($company, $interactions);

        return $company;
    }
}
