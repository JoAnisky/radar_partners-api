<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\Interaction;
use App\Enum\InteractionOutcome;
use App\Enum\PipelineStatus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PipelineStatusResolver
{
    public function __construct(
        #[Autowire('%app.recontact_delay%')]
        private string $recontactDelay,
    ) {
    }

    /** @param list<Interaction> $interactions */
    public function apply(Company $company, array $interactions): void
    {
        usort($interactions, static fn (Interaction $a, Interaction $b): int => $b->getOccurredAt() <=> $a->getOccurredAt());

        $company->setRecontactAfter(null);
        $company->setStatus([] === $interactions ? PipelineStatus::ToContact : PipelineStatus::Contacted);

        foreach ($interactions as $interaction) {
            $status = match ($interaction->getOutcome()) {
                InteractionOutcome::NoFreelancePolicy => PipelineStatus::Excluded,
                InteractionOutcome::MeetingScheduled => PipelineStatus::Meeting,
                InteractionOutcome::JustHired, InteractionOutcome::NoNeed => PipelineStatus::OnHold,
                InteractionOutcome::Positive => PipelineStatus::FollowUp,
                default => null,
            };

            if (null === $status) {
                continue;
            }

            $company->setStatus($status);
            if (PipelineStatus::OnHold === $status) {
                $company->setRecontactAfter($interaction->getOccurredAt()->add(new \DateInterval($this->recontactDelay)));
            }

            return;
        }
    }
}
