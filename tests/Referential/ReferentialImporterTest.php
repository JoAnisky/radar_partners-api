<?php

namespace App\Tests\Referential;

use App\Entity\Company;
use App\Enum\Scope;
use App\Referential\ReferentialCompany;
use App\Referential\ReferentialEstablishment;
use App\Referential\ReferentialImporter;
use App\Referential\ReferentialImportOutcome;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ReferentialImporterTest extends TestCase
{
    private function establishment(string $siret, string $postalCode, string $city, bool $headOffice = false): ReferentialEstablishment
    {
        return new ReferentialEstablishment($siret, '12 RUE DES FLEURS', $postalCode, $city, $headOffice, true);
    }

    private function source(string $name, string $postalCode = '25000', string $city = 'BESANCON', bool $headOfficeElsewhere = false): ReferentialCompany
    {
        $local = $this->establishment('11111111100029', $postalCode, $city);
        $headOffice = $headOfficeElsewhere
            ? $this->establishment('11111111100011', '75001', 'PARIS', true)
            : $local;

        return new ReferentialCompany('111111111', $name, '62.01Z', '10-19', $headOffice, [$local]);
    }

    /** @param list<Company> $existing */
    private function importer(array $existing = [], ?Company $knownBySiren = null): ReferentialImporter
    {
        $repository = $this->createStub(CompanyRepository::class);
        $repository->method('findOneBy')->willReturn($knownBySiren);
        $repository->method('findBy')->willReturn($existing);

        return new ReferentialImporter($this->createStub(EntityManagerInterface::class), $repository);
    }

    public function testCreatesCompanyFromLocalEstablishment(): void
    {
        $result = $this->importer()->import($this->source('ACME WEB SAS'));

        self::assertSame(ReferentialImportOutcome::Created, $result->outcome);
        $company = $result->company;
        self::assertNotNull($company);
        self::assertSame('111111111', $company->getSiren());
        self::assertSame('62.01Z', $company->getNafCode());
        self::assertSame('10-19', $company->getHeadcountRange());
        self::assertSame('BESANCON', $company->getCity());
        self::assertSame(Scope::Local, $company->getScope());
        self::assertNull($company->getNotes());
    }

    public function testNotesHeadOfficeWhenElsewhere(): void
    {
        $result = $this->importer()->import($this->source('ACME WEB', headOfficeElsewhere: true));

        self::assertSame('Siège : 75001 PARIS', $result->company?->getNotes());
    }

    public function testEnrichesExistingCompanyWithSameNameAndLocation(): void
    {
        $existing = (new Company('Acme Web'))->setPostalCode('25000');
        $importer = $this->importer([$existing]);

        $result = $importer->import($this->source('ACME WEB SAS'));

        self::assertSame(ReferentialImportOutcome::Enriched, $result->outcome);
        self::assertSame($existing, $result->company);
        self::assertSame('111111111', $existing->getSiren());
        self::assertSame('62.01Z', $existing->getNafCode());
        self::assertSame('Acme Web', $existing->getName());
        self::assertSame([], $importer->unmatched());
    }

    public function testSameNameInAnotherCityIsAmbiguous(): void
    {
        $existing = (new Company('Acme Web'))->setPostalCode('70200')->setCity('Lure');

        $result = $this->importer([$existing])->import($this->source('ACME WEB'));

        self::assertSame(ReferentialImportOutcome::Ambiguous, $result->outcome);
        self::assertNull($existing->getSiren());
    }

    public function testSubstringOfAnotherWordDoesNotMatch(): void
    {
        $existing = (new Company('ADEO informatique'))->setPostalCode('25000');
        $source = new ReferentialCompany('222222222', 'INFOR (FRANCE) SAS', '62.01Z', '50-99', null, [$this->establishment('22222222200010', '25000', 'BESANCON')]);

        $result = $this->importer([$existing])->import($source);

        self::assertSame(ReferentialImportOutcome::Created, $result->outcome);
        self::assertNull($existing->getSiren());
    }

    public function testKnownSirenIsSkipped(): void
    {
        $result = $this->importer([], new Company('Acme Web'))->import($this->source('ACME WEB'));

        self::assertSame(ReferentialImportOutcome::AlreadyKnown, $result->outcome);
        self::assertNull($result->company);
    }
}
