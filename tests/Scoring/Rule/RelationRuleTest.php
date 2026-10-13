<?php

namespace App\Tests\Scoring\Rule;

use App\Entity\Company;
use App\Enum\InteractionChannel;
use App\Enum\InteractionOutcome;
use App\Scoring\Rule\RelationRule;

final class RelationRuleTest extends RuleTestCase
{
    public function testNoInteractionScoresNothing(): void
    {
        self::assertSame([], self::summary(new RelationRule(), new Company('A')));
    }

    public function testPositiveOutcomeAndMeetingScore(): void
    {
        $company = new Company('A');

        foreach ([InteractionOutcome::Positive, InteractionOutcome::MeetingScheduled] as $outcome) {
            $context = self::context([self::interaction($company, outcome: $outcome)]);
            self::assertSame(25, self::summary(new RelationRule(), $company, $context)[0][0], $outcome->value);
        }
    }

    public function testInPersonContactScoresUnlessNobodyWasThere(): void
    {
        $company = new Company('A');

        $met = self::context([self::interaction($company, InteractionChannel::InPerson, InteractionOutcome::NoNeed)]);
        self::assertSame([[10, 'Contact physique']], self::summary(new RelationRule(), $company, $met));

        $absent = self::context([self::interaction($company, InteractionChannel::InPerson, InteractionOutcome::NobodyOnSite)]);
        self::assertSame([], self::summary(new RelationRule(), $company, $absent));
    }

    public function testRecommendationScores(): void
    {
        $company = new Company('A');
        $context = self::context([self::interaction($company, origin: 'Tel suite reco Julien')]);

        self::assertSame([[10, 'Recommandation']], self::summary(new RelationRule(), $company, $context));
    }

    public function testPhoneAttemptsAloneScoreNothing(): void
    {
        $company = new Company('A');
        $context = self::context([self::interaction($company, outcome: InteractionOutcome::NoAnswer), self::interaction($company, outcome: InteractionOutcome::Voicemail)]);

        self::assertSame([], self::summary(new RelationRule(), $company, $context));
    }
}
