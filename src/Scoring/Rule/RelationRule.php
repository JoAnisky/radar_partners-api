<?php

namespace App\Scoring\Rule;

use App\Entity\Company;
use App\Enum\InteractionChannel;
use App\Enum\InteractionOutcome;
use App\Scoring\ScoreContribution;
use App\Scoring\ScoringContext;
use App\Scoring\ScoringRuleInterface;

/** Une relation déjà engagée (échange positif, rencontre, recommandation) rend la prise de contact plus facile. */
final class RelationRule implements ScoringRuleInterface
{
    private const POSITIVE_POINTS = 25;
    private const IN_PERSON_POINTS = 10;
    private const RECOMMENDATION_POINTS = 10;

    public function evaluate(Company $company, ScoringContext $context): array
    {
        $positive = $inPerson = $recommended = false;

        foreach ($context->interactions as $interaction) {
            $outcome = $interaction->getOutcome();
            $positive = $positive || \in_array($outcome, [InteractionOutcome::Positive, InteractionOutcome::MeetingScheduled], true);
            $inPerson = $inPerson || (InteractionChannel::InPerson === $interaction->getChannel()
                && !\in_array($outcome, [InteractionOutcome::NobodyOnSite, InteractionOutcome::NoFreelancePolicy], true));
            $recommended = $recommended || 1 === preg_match('/reco/iu', (string) $interaction->getOrigin());
        }

        return array_values(array_filter([
            $positive ? new ScoreContribution(self::POSITIVE_POINTS, 'Échange positif ou rendez-vous') : null,
            $inPerson ? new ScoreContribution(self::IN_PERSON_POINTS, 'Contact physique') : null,
            $recommended ? new ScoreContribution(self::RECOMMENDATION_POINTS, 'Recommandation') : null,
        ]));
    }
}
