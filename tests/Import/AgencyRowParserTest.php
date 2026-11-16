<?php

namespace App\Tests\Import;

use App\Enum\CompanyType;
use App\Enum\Scope;
use App\Enum\Zone;
use App\Import\AgencyRowParser;
use PHPUnit\Framework\TestCase;

final class AgencyRowParserTest extends TestCase
{
    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private static function row(array $overrides = []): array
    {
        return $overrides + [
            'Agence' => 'Acme Studio', 'Ville' => 'Besançon / Vesoul', 'Zone' => 'Franche-Comté', 'Priorité' => 'Haute',
            'Statut' => 'À appeler', 'Date contact' => '', 'Prochaine action' => 'Appeler le gérant', 'Interlocuteur' => '',
            'Infos (stack, taille)' => 'PHP, Symfony', 'Notes' => '',
        ];
    }

    public function testParsesALocalWebAgency(): void
    {
        $row = (new AgencyRowParser())->parse(self::row());

        self::assertNotNull($row);
        self::assertSame('Besançon', $row->city);
        self::assertSame(Zone::FrancheComte, $row->zone);
        self::assertSame(Scope::Local, $row->scope);
        self::assertSame(CompanyType::WebAgency, $row->type);
        self::assertSame("Priorité : Haute\nInfos : PHP, Symfony\nProchaine action : Appeler le gérant\nVilles : Besançon / Vesoul", $row->notes);
    }

    public function testSwissAgencyIsNational(): void
    {
        $row = (new AgencyRowParser())->parse(self::row(['Zone' => 'Suisse – Vaud', 'Ville' => 'Lausanne']));

        self::assertNotNull($row);
        self::assertSame(Zone::SwissRomande, $row->zone);
        self::assertSame(Scope::National, $row->scope);
    }

    public function testCommunicationAgencyType(): void
    {
        self::assertSame(CompanyType::ComAgency, (new AgencyRowParser())->parse(self::row(['Infos (stack, taille)' => 'Agence de com depuis 2002']))?->type);
        self::assertSame(CompanyType::Unknown, (new AgencyRowParser())->parse(self::row(['Infos (stack, taille)' => 'Agence 360°']))?->type);
    }

    public function testUncertainCityIsDroppedAndReported(): void
    {
        $row = (new AgencyRowParser())->parse(self::row(['Ville' => 'À vérifier (Nord ?)']));

        self::assertNotNull($row);
        self::assertNull($row->city);
        self::assertStringContainsString('Ville à vérifier', (string) $row->notes);
        self::assertNotSame([], $row->warnings);
    }

    public function testIgnoredContactDataIsReported(): void
    {
        self::assertNotSame([], (new AgencyRowParser())->parse(self::row(['Interlocuteur' => 'Jane']))?->warnings);
    }

    public function testBlankNameIsSkipped(): void
    {
        self::assertNull((new AgencyRowParser())->parse(self::row(['Agence' => ' '])));
    }
}
