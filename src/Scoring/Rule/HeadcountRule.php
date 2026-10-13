<?php

namespace App\Scoring\Rule;

use App\Entity\Company;
use App\Scoring\ScoreContribution;
use App\Scoring\ScoringContext;
use App\Scoring\ScoringRuleInterface;

/** Les structures de taille moyenne (6 à 49 salariés) sont les plus propices ; les petites agences comptent aussi. */
final class HeadcountRule implements ScoringRuleInterface
{
    private const POINTS = [
        '1-2' => 3,
        '3-5' => 8,
        '6-9' => 15,
        '10-19' => 15,
        '20-49' => 15,
        '50-99' => 8,
    ];

    public function evaluate(Company $company, ScoringContext $context): array
    {
        $range = $company->getHeadcountRange();
        $points = null === $range ? null : (self::POINTS[$range] ?? null);

        return null === $points ? [] : [new ScoreContribution($points, sprintf('Effectif %s', $range))];
    }
}
