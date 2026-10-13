<?php

namespace App\Tests\Scoring\Rule;

use App\Entity\Company;
use App\Enum\CompanyType;
use App\Scoring\Rule\CompanyTypeRule;

final class CompanyTypeRuleTest extends RuleTestCase
{
    public function testTargetTypesScore(): void
    {
        foreach ([CompanyType::Esn, CompanyType::WebAgency, CompanyType::ComAgency] as $type) {
            $result = self::summary(new CompanyTypeRule(), (new Company('A'))->setType($type));
            self::assertSame(20, $result[0][0], $type->value);
        }
    }

    public function testEndClientIsEliminated(): void
    {
        $contributions = (new CompanyTypeRule())->evaluate((new Company('A'))->setType(CompanyType::EndClient), self::context());

        self::assertTrue($contributions[0]->eliminatory);
    }

    public function testUnknownTypeScoresNothing(): void
    {
        self::assertSame([], self::summary(new CompanyTypeRule(), new Company('A')));
    }
}
