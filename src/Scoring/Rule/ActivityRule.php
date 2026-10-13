<?php

namespace App\Scoring\Rule;

use App\Entity\Company;
use App\Enum\CompanyType;
use App\Scoring\ScoreContribution;
use App\Scoring\ScoringContext;
use App\Scoring\ScoringRuleInterface;

/** Quand le type est inconnu, le code NAF indique une activité proche de la cible. */
final class ActivityRule implements ScoringRuleInterface
{
    private const TARGET_NAF = ['62.01Z', '62.02A', '62.02B', '62.03Z', '62.09Z', '63.11Z', '63.12Z', '73.11Z'];
    private const TARGET_POINTS = 15;
    private const PUBLISHER_NAF = ['58.29A', '58.29C'];
    private const PUBLISHER_POINTS = 5;

    public function evaluate(Company $company, ScoringContext $context): array
    {
        $naf = $company->getNafCode();
        if (CompanyType::Unknown !== $company->getType() || null === $naf) {
            return [];
        }

        if (\in_array($naf, self::TARGET_NAF, true)) {
            return [new ScoreContribution(self::TARGET_POINTS, sprintf('Activité cible (NAF %s)', $naf))];
        }

        if (\in_array($naf, self::PUBLISHER_NAF, true)) {
            return [new ScoreContribution(self::PUBLISHER_POINTS, sprintf('Édition de logiciels (NAF %s)', $naf))];
        }

        return [];
    }
}
