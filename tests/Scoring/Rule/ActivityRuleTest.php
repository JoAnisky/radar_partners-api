<?php

namespace App\Tests\Scoring\Rule;

use App\Entity\Company;
use App\Enum\CompanyType;
use App\Scoring\Rule\ActivityRule;

final class ActivityRuleTest extends RuleTestCase
{
    public function testTargetNafScoresWhenTypeIsUnknown(): void
    {
        self::assertSame([[15, 'Activité cible (NAF 62.01Z)']], self::summary(new ActivityRule(), (new Company('A'))->setNafCode('62.01Z')));
    }

    public function testSoftwarePublisherScoresLess(): void
    {
        self::assertSame(5, self::summary(new ActivityRule(), (new Company('A'))->setNafCode('58.29C'))[0][0]);
    }

    public function testOtherNafOrMissingNafScoresNothing(): void
    {
        self::assertSame([], self::summary(new ActivityRule(), (new Company('A'))->setNafCode('47.11A')));
        self::assertSame([], self::summary(new ActivityRule(), new Company('A')));
    }

    public function testKnownTypeIsNotCountedTwice(): void
    {
        $company = (new Company('A'))->setNafCode('62.01Z')->setType(CompanyType::WebAgency);

        self::assertSame([], self::summary(new ActivityRule(), $company));
    }
}
