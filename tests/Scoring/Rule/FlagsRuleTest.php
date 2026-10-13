<?php

namespace App\Tests\Scoring\Rule;

use App\Entity\Company;
use App\Scoring\Rule\FlagsRule;

final class FlagsRuleTest extends RuleTestCase
{
    public function testPenalizesDifficultyAndInactivity(): void
    {
        $company = (new Company('A'))->setFlags(['IN_DIFFICULTY', 'MAYBE_INACTIVE']);

        self::assertSame([[-15, 'En difficulté'], [-15, 'Peut-être inactive']], self::summary(new FlagsRule(), $company));
    }

    public function testOtherFlagsAreNeutral(): void
    {
        self::assertSame([], self::summary(new FlagsRule(), (new Company('A'))->setFlags(['BROKEN_CONTACT_FORM'])));
    }
}
