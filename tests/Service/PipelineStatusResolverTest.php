<?php

namespace App\Tests\Service;

use App\Entity\Company;
use App\Entity\Interaction;
use App\Enum\InteractionChannel;
use App\Enum\InteractionOutcome;
use App\Enum\PipelineStatus;
use App\Service\PipelineStatusResolver;
use PHPUnit\Framework\TestCase;

final class PipelineStatusResolverTest extends TestCase
{
    private function interaction(Company $company, string $date, InteractionOutcome $outcome): Interaction
    {
        return (new Interaction($company, InteractionChannel::Phone, new \DateTimeImmutable($date)))->setOutcome($outcome);
    }

    public function testNoInteractionMeansToContact(): void
    {
        $company = new Company('Acme');
        (new PipelineStatusResolver('P6M'))->apply($company, []);

        self::assertSame(PipelineStatus::ToContact, $company->getStatus());
    }

    public function testInsignificantOutcomesMeanContacted(): void
    {
        $company = new Company('Acme');
        (new PipelineStatusResolver('P6M'))->apply($company, [$this->interaction($company, '2026-10-01', InteractionOutcome::Voicemail)]);

        self::assertSame(PipelineStatus::Contacted, $company->getStatus());
    }

    public function testNoFreelancePolicyExcludes(): void
    {
        $company = new Company('Acme');
        (new PipelineStatusResolver('P6M'))->apply($company, [$this->interaction($company, '2026-10-01', InteractionOutcome::NoFreelancePolicy)]);

        self::assertSame(PipelineStatus::Excluded, $company->getStatus());
    }

    public function testNoNeedPutsOnHoldWithRecontactDate(): void
    {
        $company = new Company('Acme');
        (new PipelineStatusResolver('P6M'))->apply($company, [$this->interaction($company, '2026-10-01', InteractionOutcome::NoNeed)]);

        self::assertSame(PipelineStatus::OnHold, $company->getStatus());
        self::assertSame('2027-04-01', $company->getRecontactAfter()?->format('Y-m-d'));
    }

    public function testLatestSignificantInteractionWins(): void
    {
        $company = new Company('Acme');
        (new PipelineStatusResolver('P6M'))->apply($company, [
            $this->interaction($company, '2026-09-01', InteractionOutcome::NoNeed),
            $this->interaction($company, '2026-10-01', InteractionOutcome::MeetingScheduled),
            $this->interaction($company, '2026-10-05', InteractionOutcome::NoAnswer),
        ]);

        self::assertSame(PipelineStatus::Meeting, $company->getStatus());
        self::assertNull($company->getRecontactAfter());
    }
}
