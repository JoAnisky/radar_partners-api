<?php

namespace App\Tests\Util;

use App\Util\NoteDateExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoteDateExtractorTest extends TestCase
{
    /** @return iterable<string, array{string, list<string>}> */
    public static function texts(): iterable
    {
        yield 'points' => ['Réponse : RDV le 17.09.2026 à 11h', ['2026-09-17']];
        yield 'slashs' => ['rappeler le 3/10/2026', ['2026-10-03']];
        yield 'tirets' => ['vu le 03-10-2026', ['2026-10-03']];
        yield 'iso' => ['échéance 2026-12-01', ['2026-12-01']];
        yield 'mois en lettres' => ['RDV le 3 octobre 2026 puis le 1er décembre 2026', ['2026-10-03', '2026-12-01']];
        yield 'mois accentué' => ['le 12 février 2027 et le 30 août 2026', ['2027-02-12', '2026-08-30']];
        yield 'sans année ignoré' => ['RDV le 17/09 à 11h', []];
        yield 'date impossible ignorée' => ['le 31.02.2026', []];
        yield 'doublon' => ['le 17.09.2026, confirmé le 17 septembre 2026', ['2026-09-17']];
        yield 'numéro de téléphone ignoré' => ['Tel 03.81.12.34.56', []];
        yield 'vide' => ['', []];
    }

    /** @param list<string> $expected */
    #[DataProvider('texts')]
    public function testExtractsFullDates(string $text, array $expected): void
    {
        self::assertSame($expected, array_map(static fn (array $found): string => $found['date']->format('Y-m-d'), NoteDateExtractor::extract($text)));
    }
}
