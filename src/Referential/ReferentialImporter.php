<?php

namespace App\Referential;

use App\Entity\Company;
use App\Enum\Scope;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ReferentialImporter
{
    /** @var list<Company>|null entreprises existantes sans SIREN, non encore rapprochées */
    private ?array $unmatched = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CompanyRepository $companies,
    ) {
    }

    public function import(ReferentialCompany $source): ReferentialImportResult
    {
        if (null !== $this->companies->findOneBy(['siren' => $source->siren])) {
            return new ReferentialImportResult(ReferentialImportOutcome::AlreadyKnown, $source);
        }

        $candidates = array_values(array_filter(
            $this->unmatched(),
            static fn (Company $c): bool => CompanyNameMatcher::matches($source->name, $c->getName()),
        ));

        if ([] === $candidates) {
            return new ReferentialImportResult(ReferentialImportOutcome::Created, $source, $this->create($source));
        }

        if (1 === \count($candidates) && $this->sameLocation($source, $candidates[0])) {
            $this->enrich($candidates[0], $source);
            $this->unmatched = array_values(array_filter($this->unmatched(), static fn (Company $c): bool => $c !== $candidates[0]));

            return new ReferentialImportResult(ReferentialImportOutcome::Enriched, $source, $candidates[0]);
        }

        $names = implode(', ', array_map(static fn (Company $c): string => $c->getName(), $candidates));

        return new ReferentialImportResult(ReferentialImportOutcome::Ambiguous, $source, null, 'Existant(s) : '.$names);
    }

    /** @return list<Company> entreprises existantes sans SIREN et sans correspondance */
    public function unmatched(): array
    {
        return $this->unmatched ??= $this->companies->findBy(['siren' => null]);
    }

    private function create(ReferentialCompany $source): Company
    {
        $company = new Company($source->name);
        $company->setScope(Scope::Local);
        $this->enrich($company, $source);

        $local = $this->localEstablishment($source);
        if (null !== $source->headOffice && null !== $local && $source->headOffice->siret !== $local->siret) {
            $company->setNotes(sprintf('Siège : %s %s', $source->headOffice->postalCode, $source->headOffice->city));
        }

        $this->em->persist($company);

        return $company;
    }

    private function enrich(Company $company, ReferentialCompany $source): void
    {
        $local = $this->localEstablishment($source);

        $company->setSiren($source->siren);
        $company->setNafCode($company->getNafCode() ?? $source->nafCode);
        $company->setHeadcountRange($company->getHeadcountRange() ?? $source->headcountRange);
        $company->setAddress($company->getAddress() ?? $local?->address);
        $company->setPostalCode($company->getPostalCode() ?? $local?->postalCode);
        $company->setCity($company->getCity() ?? $local?->city);
    }

    private function localEstablishment(ReferentialCompany $source): ?ReferentialEstablishment
    {
        foreach ($source->establishments as $establishment) {
            if ($establishment->isActive) {
                return $establishment;
            }
        }

        return $source->headOffice;
    }

    private function sameLocation(ReferentialCompany $source, Company $company): bool
    {
        $establishments = [...$source->establishments, ...(null === $source->headOffice ? [] : [$source->headOffice])];
        $city = null === $company->getCity() ? null : CompanyNameMatcher::normalize($company->getCity());

        foreach ($establishments as $establishment) {
            if (null !== $company->getPostalCode() && $establishment->postalCode === $company->getPostalCode()) {
                return true;
            }
            if (null !== $city && null !== $establishment->city && CompanyNameMatcher::normalize($establishment->city) === $city) {
                return true;
            }
        }

        return false;
    }
}
