<?php

namespace App\Tests\Scoring\Rule;

use App\Entity\Company;
use App\Scoring\Rule\HeadcountRule;
use PHPUnit\Framework\Attributes\DataProvider;

final class HeadcountRuleTest extends RuleTestCase
{
    /** @return iterable<string, array{?string, ?int}> */
    public static function ranges(): iterable
    {
        yield 'micro' => ['1-2', 3];
        yield 'petite agence' => ['3-5', 8];
        yield 'cible basse' => ['6-9', 15];
        yield 'cible' => ['10-19', 15];
        yield 'cible haute' => ['20-49', 15];
        yield 'moyenne' => ['50-99', 8];
        yield 'grande structure' => ['100-199', null];
        yield 'inconnu' => [null, null];
    }

    #[DataProvider('ranges')]
    public function testPointsByRange(?string $range, ?int $expected): void
    {
        $result = self::summary(new HeadcountRule(), (new Company('A'))->setHeadcountRange($range));

        self::assertSame($expected, $result[0][0] ?? null);
    }
}
