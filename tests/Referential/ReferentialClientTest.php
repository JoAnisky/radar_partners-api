<?php

namespace App\Tests\Referential;

use App\Referential\ReferentialClient;
use App\Referential\ReferentialCompany;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ReferentialClientTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function item(string $siren, string $name = 'ACME WEB'): array
    {
        return [
            'siren' => $siren,
            'nom_complet' => $name,
            'activite_principale' => '62.01Z',
            'tranche_effectif_salarie' => '11',
            'siege' => [
                'siret' => $siren.'00011',
                'adresse' => '1 RUE DES LILAS 75001 PARIS',
                'code_postal' => '75001',
                'libelle_commune' => 'PARIS',
                'est_siege' => true,
                'etat_administratif' => 'A',
            ],
            'matching_etablissements' => [[
                'siret' => $siren.'00029',
                'adresse' => '12 RUE DES FLEURS 25000 BESANCON',
                'code_postal' => '25000',
                'libelle_commune' => 'BESANCON',
                'est_siege' => false,
                'etat_administratif' => 'A',
            ]],
        ];
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @param list<string>               $requests
     */
    private function client(array $pages, array &$requests = []): ReferentialClient
    {
        $responses = array_map(
            static fn (array $page) => new MockResponse((string) json_encode($page)),
            $pages,
        );

        $http = new MockHttpClient(static function (string $method, string $url) use (&$requests, &$responses) {
            $requests[] = $url;

            return array_shift($responses);
        });

        return new ReferentialClient($http, 0);
    }

    public function testParsesCompanyAndEstablishments(): void
    {
        $client = $this->client([['results' => [self::item('111111111')], 'total_pages' => 1]]);

        $companies = iterator_to_array($client->search(['departement' => '25']), false);

        self::assertCount(1, $companies);
        $company = $companies[0];
        self::assertInstanceOf(ReferentialCompany::class, $company);
        self::assertSame('111111111', $company->siren);
        self::assertSame('62.01Z', $company->nafCode);
        self::assertSame('10-19', $company->headcountRange);
        self::assertSame('PARIS', $company->headOffice?->city);
        self::assertSame('12 RUE DES FLEURS', $company->establishments[0]->address);
        self::assertSame('25000', $company->establishments[0]->postalCode);
        self::assertFalse($company->establishments[0]->isHeadOffice);
        self::assertTrue($company->establishments[0]->isActive);
    }

    public function testFollowsPagination(): void
    {
        $requests = [];
        $client = $this->client([
            ['results' => [self::item('111111111')], 'total_pages' => 2],
            ['results' => [self::item('222222222')], 'total_pages' => 2],
        ], $requests);

        $companies = iterator_to_array($client->search(['departement' => '25']), false);

        self::assertSame(['111111111', '222222222'], array_map(static fn ($c) => $c->siren, $companies));
        self::assertCount(2, $requests);
        self::assertStringContainsString('page=2', $requests[1]);
    }

    public function testStopsAtLimit(): void
    {
        $requests = [];
        $client = $this->client([
            ['results' => [self::item('111111111'), self::item('222222222')], 'total_pages' => 5],
        ], $requests);

        $companies = iterator_to_array($client->search(['departement' => '25'], null, 1), false);

        self::assertCount(1, $companies);
        self::assertCount(1, $requests);
    }

    public function testSendsFiltersAndActiveStateOnly(): void
    {
        $requests = [];
        $client = $this->client([['results' => [], 'total_pages' => 0]], $requests);

        iterator_to_array($client->search(['activite_principale' => '62.01Z,73.11Z'], 'acme'), false);

        self::assertStringContainsString('q=acme', $requests[0]);
        self::assertStringContainsString('activite_principale=62.01Z%2C73.11Z', $requests[0]);
        self::assertStringContainsString('etat_administratif=A', $requests[0]);
    }

    public function testSkipsMalformedItemsAndUnknownHeadcount(): void
    {
        $unknown = self::item('333333333');
        $unknown['tranche_effectif_salarie'] = 'NN';
        $client = $this->client([['results' => [['nom_complet' => 'Sans SIREN'], 'not-an-array', $unknown], 'total_pages' => 1]]);

        $companies = iterator_to_array($client->search(['departement' => '25']), false);

        self::assertCount(1, $companies);
        self::assertNull($companies[0]->headcountRange);
    }
}
