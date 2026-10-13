<?php

namespace App\Tests\Scoring\Rule;

use App\Entity\Company;
use App\Entity\Contact;
use App\Scoring\Rule\ContactRule;

final class ContactRuleTest extends RuleTestCase
{
    public function testReachableContactScores(): void
    {
        $company = new Company('A');
        (new Contact($company, 'Jane'))->setEmail('jane@acme.test');

        self::assertSame([[5, 'Contact direct identifié']], self::summary(new ContactRule(), $company));
    }

    public function testContactWithoutPhoneNorEmailScoresNothing(): void
    {
        $company = new Company('A');
        new Contact($company, 'Jane');

        self::assertSame([], self::summary(new ContactRule(), $company));
        self::assertSame([], self::summary(new ContactRule(), new Company('B')));
    }
}
