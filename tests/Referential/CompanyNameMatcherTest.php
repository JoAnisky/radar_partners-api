<?php

namespace App\Tests\Referential;

use App\Referential\CompanyNameMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CompanyNameMatcherTest extends TestCase
{
    /** @return iterable<string, array{string, string, bool}> */
    public static function pairs(): iterable
    {
        yield 'casse et accents' => ['Tête de com', 'TETE DE COM', true];
        yield 'ponctuation' => ['IDXX', 'ID.XX', true];
        yield 'forme juridique' => ['Acme Web', 'ACME WEB SAS', true];
        yield 'nom commercial entre parenthèses' => ['Beta Studio', 'BETA SOLUTIONS ET SERVICES (BETA STUDIO, BETA)', true];
        yield 'alias principal' => ['Gamma', 'GAMMA (GAMMA CONSULTING)', true];
        yield 'mot entier contenu' => ['Delta Conseil', 'DELTA CONSEIL ET FORMATION', true];
        yield 'sous-chaîne non alignée sur un mot' => ['ADEO informatique', 'INFOR (FRANCE) SAS', false];
        yield 'alias générique' => ['Orange France', 'INFOR (FRANCE) SAS', false];
        yield 'noms différents' => ['Acme Web', 'Zeta Agency', false];
        yield 'trop court pour la contenance' => ['Zed', 'Zed Production', false];
    }

    #[DataProvider('pairs')]
    public function testMatches(string $a, string $b, bool $expected): void
    {
        self::assertSame($expected, CompanyNameMatcher::matches($a, $b));
        self::assertSame($expected, CompanyNameMatcher::matches($b, $a));
    }
}
