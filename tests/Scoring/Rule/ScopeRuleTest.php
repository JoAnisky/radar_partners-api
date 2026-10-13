<?php

namespace App\Tests\Scoring\Rule;

use App\Entity\Company;
use App\Enum\Scope;
use App\Scoring\Rule\ScopeRule;

final class ScopeRuleTest extends RuleTestCase
{
    public function testLocalScores(): void
    {
        self::assertSame(10, self::summary(new ScopeRule(), (new Company('A'))->setScope(Scope::Local))[0][0]);
    }

    public function testNationalDoesNot(): void
    {
        self::assertSame([], self::summary(new ScopeRule(), (new Company('A'))->setScope(Scope::National)));
    }
}
