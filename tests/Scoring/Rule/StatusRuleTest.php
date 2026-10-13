<?php

namespace App\Tests\Scoring\Rule;

use App\Entity\Company;
use App\Enum\PipelineStatus;
use App\Scoring\Rule\StatusRule;

final class StatusRuleTest extends RuleTestCase
{
    public function testExcludedIsEliminated(): void
    {
        $contributions = (new StatusRule())->evaluate((new Company('A'))->setStatus(PipelineStatus::Excluded), self::context());

        self::assertTrue($contributions[0]->eliminatory);
    }

    public function testPartnerScores(): void
    {
        self::assertSame(30, self::summary(new StatusRule(), (new Company('A'))->setStatus(PipelineStatus::Partner))[0][0]);
    }

    public function testOnHoldIsPenalizedUntilTheRecontactDate(): void
    {
        $company = (new Company('A'))->setStatus(PipelineStatus::OnHold)->setRecontactAfter(new \DateTimeImmutable('2027-01-07'));

        self::assertSame([[-15, "En attente jusqu'au 07/01/2027"]], self::summary(new StatusRule(), $company));
    }

    public function testOnHoldWhoseDateHasPassedIsNotPenalized(): void
    {
        $company = (new Company('A'))->setStatus(PipelineStatus::OnHold)->setRecontactAfter(new \DateTimeImmutable('2026-10-01'));

        self::assertSame([], self::summary(new StatusRule(), $company));
    }

    public function testOtherStatusesScoreNothing(): void
    {
        foreach ([PipelineStatus::ToContact, PipelineStatus::Contacted, PipelineStatus::FollowUp, PipelineStatus::Meeting] as $status) {
            self::assertSame([], self::summary(new StatusRule(), (new Company('A'))->setStatus($status)), $status->value);
        }
    }
}
