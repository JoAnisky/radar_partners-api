<?php

namespace App\Scoring\Rule;

use App\Entity\Company;
use App\Enum\PipelineStatus;
use App\Scoring\ScoreContribution;
use App\Scoring\ScoringContext;
use App\Scoring\ScoringRuleInterface;

final class StatusRule implements ScoringRuleInterface
{
    private const PARTNER_POINTS = 30;
    private const ON_HOLD_POINTS = -15;

    public function evaluate(Company $company, ScoringContext $context): array
    {
        return match ($company->getStatus()) {
            PipelineStatus::Excluded => [new ScoreContribution(0, 'Exclue du suivi', true)],
            PipelineStatus::Partner => [new ScoreContribution(self::PARTNER_POINTS, 'Partenaire')],
            PipelineStatus::OnHold => $this->onHold($company, $context),
            default => [],
        };
    }

    /** @return list<ScoreContribution> */
    private function onHold(Company $company, ScoringContext $context): array
    {
        $recontact = $company->getRecontactAfter();
        if (null !== $recontact && $recontact <= $context->now) {
            return [];
        }

        return [new ScoreContribution(
            self::ON_HOLD_POINTS,
            null === $recontact ? 'En attente' : sprintf('En attente jusqu\'au %s', $recontact->format('d/m/Y')),
        )];
    }
}
