<?php

namespace App\Tests\Enum;

use App\Enum\Zone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ZoneTest extends TestCase
{
    /** @return iterable<string, array{?string, ?Zone}> */
    public static function postalCodes(): iterable
    {
        yield 'Doubs' => ['25000', Zone::FrancheComte];
        yield 'Jura' => ['39100', Zone::FrancheComte];
        yield 'Territoire de Belfort' => ['90000', Zone::FrancheComte];
        yield 'Côte-d\'Or' => ['21000', Zone::Bourgogne];
        yield 'Paris' => ['75003', Zone::Other];
        yield 'Swiss code' => ['2800', null];
        yield 'missing' => [null, null];
    }

    #[DataProvider('postalCodes')]
    public function testFromPostalCode(?string $postalCode, ?Zone $expected): void
    {
        self::assertSame($expected, Zone::fromPostalCode($postalCode));
    }
}
