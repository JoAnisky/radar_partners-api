<?php

namespace App\Import;

use App\Entity\Company;
use App\Enum\CompanyType;
use App\Referential\CompanyNameMatcher;
use App\Repository\CompanyRepository;
use App\Scoring\CompanyScorer;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AgencyImporter
{
    public function __construct(
        private EntityManagerInterface $em,
        private CompanyRepository $companies,
        private CompanyScorer $scorer,
    ) {
    }

    /**
     * Crée l'agence, ou complète seulement les champs vides d'une entreprise existante (ses données priment).
     *
     * @return array{string, string} résultat (created, updated, unchanged, ambiguous) et détail
     */
    public function import(AgencyRow $row): array
    {
        $matches = array_values(array_filter(
            $this->companies->findAll(),
            static fn (Company $c): bool => CompanyNameMatcher::matches($row->name, $c->getName()),
        ));

        if (\count($matches) > 1) {
            return ['ambiguous', implode(', ', array_map(static fn (Company $c): string => $c->getName(), $matches))];
        }

        if ([] === $matches) {
            $company = (new Company($row->name))
                ->setCity($row->city)
                ->setZone($row->zone)
                ->setScope($row->scope)
                ->setType($row->type)
                ->setNotes($row->notes);
            $this->em->persist($company);
            $this->em->flush();
            $this->scorer->rescore($company);
            $this->em->flush();

            return ['created', ''];
        }

        $company = $matches[0];
        $filled = [];
        if (CompanyType::Unknown === $company->getType() && CompanyType::Unknown !== $row->type) {
            $company->setType($row->type);
            $filled[] = 'type';
        }
        if (null === $company->getZone()) {
            $company->setZone($row->zone);
            $filled[] = 'zone';
        }
        if (null === $company->getCity() && null !== $row->city) {
            $company->setCity($row->city);
            $filled[] = 'ville';
        }
        if (null === $company->getNotes() || '' === trim($company->getNotes())) {
            $company->setNotes($row->notes);
            $filled[] = 'notes';
        }

        if ([] === $filled) {
            return ['unchanged', $company->getName()];
        }

        $this->em->flush();
        $this->scorer->rescore($company);
        $this->em->flush();

        return ['updated', sprintf('%s : %s', $company->getName(), implode(', ', $filled))];
    }
}
