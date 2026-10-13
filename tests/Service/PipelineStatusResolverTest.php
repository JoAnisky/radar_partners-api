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

    public function testRegisterAppliesALatestSignificantInteraction(): void
    {
        $company = new Company('Acme');
        $previous = [$this->interaction($company, '2026-09-01', InteractionOutcome::NoAnswer)];
        $new = $this->interaction($company, '2026-10-01', InteractionOutcome::JustHired);

        (new PipelineStatusResolver('P6M'))->register($company, $new, $previous);

        self::assertSame(PipelineStatus::OnHold, $company->getStatus());
        self::assertSame('2027-04-01', $company->getRecontactAfter()?->format('Y-m-d'));
    }

    public function testRegisterKeepsAManualStatusForNonSignificantInteraction(): void
    {
        $company = (new Company('Acme'))->setStatus(PipelineStatus::Partner);

        (new PipelineStatusResolver('P6M'))->register($company, $this->interaction($company, '2026-10-01', InteractionOutcome::Voicemail), []);

        self::assertSame(PipelineStatus::Partner, $company->getStatus());
    }

    public function testRegisterMovesToContactedOnlyFromToContact(): void
    {
        $company = new Company('Acme');

        (new PipelineStatusResolver('P6M'))->register($company, $this->interaction($company, '2026-10-01', InteractionOutcome::Voicemail), []);

        self::assertSame(PipelineStatus::Contacted, $company->getStatus());
    }

    public function testRegisterIgnoresSignificantInteractionOlderThanExistingOnes(): void
    {
        $company = (new Company('Acme'))->setStatus(PipelineStatus::Meeting);
        $previous = [$this->interaction($company, '2026-10-10', InteractionOutcome::MeetingScheduled)];

        (new PipelineStatusResolver('P6M'))->register($company, $this->interaction($company, '2026-09-01', InteractionOutcome::NoFreelancePolicy), $previous);

        self::assertSame(PipelineStatus::Meeting, $company->getStatus());
    }

    public function testRegisterClearsRecontactDateWhenLeavingOnHold(): void
    {
        $company = (new Company('Acme'))->setStatus(PipelineStatus::OnHold)->setRecontactAfter(new \DateTimeImmutable('2027-01-01'));

        (new PipelineStatusResolver('P6M'))->register($company, $this->interaction($company, '2026-10-01', InteractionOutcome::Positive), []);

        self::assertSame(PipelineStatus::FollowUp, $company->getStatus());
        self::assertNull($company->getRecontactAfter());
    }

    public function testDeriveUsesTheLatestSignificantInteraction(): void
    {
        $company = new Company('Acme');
        $interactions = [
            $this->interaction($company, '2026-09-01', InteractionOutcome::NoNeed),
            $this->interaction($company, '2026-10-01', InteractionOutcome::MeetingScheduled),
            $this->interaction($company, '2026-10-05', InteractionOutcome::NoAnswer),
        ];

        $derived = (new PipelineStatusResolver('P6M'))->derive($interactions);

        self::assertSame(PipelineStatus::Meeting, $derived['status']);
        self::assertNull($derived['recontactAfter']);
    }

    public function testDeriveWithoutInteractions(): void
    {
        self::assertSame(PipelineStatus::ToContact, (new PipelineStatusResolver('P6M'))->derive([])['status']);
    }

    public function testFollowEditUpdatesOnlyAStatusThatFollowedTheDerivation(): void
    {
        $resolver = new PipelineStatusResolver('P6M');
        $before = ['status' => PipelineStatus::Contacted, 'recontactAfter' => null];
        $after = ['status' => PipelineStatus::OnHold, 'recontactAfter' => new \DateTimeImmutable('2027-04-01')];

        $following = (new Company('A'))->setStatus(PipelineStatus::Contacted);
        $resolver->followEdit($following, $before, $after);
        self::assertSame(PipelineStatus::OnHold, $following->getStatus());
        self::assertSame('2027-04-01', $following->getRecontactAfter()?->format('Y-m-d'));

        $manual = (new Company('B'))->setStatus(PipelineStatus::Partner);
        $resolver->followEdit($manual, $before, $after);
        self::assertSame(PipelineStatus::Partner, $manual->getStatus());
        self::assertNull($manual->getRecontactAfter());
    }

    public function testFollowEditDoesNothingWhenNothingChanged(): void
    {
        $company = (new Company('A'))->setStatus(PipelineStatus::OnHold)->setRecontactAfter(new \DateTimeImmutable('2027-01-15'));
        $derived = ['status' => PipelineStatus::OnHold, 'recontactAfter' => new \DateTimeImmutable('2027-04-01')];

        (new PipelineStatusResolver('P6M'))->followEdit($company, $derived, $derived);

        self::assertSame('2027-01-15', $company->getRecontactAfter()?->format('Y-m-d'));
    }
}
