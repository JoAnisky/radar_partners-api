<?php

namespace App\Scoring\Rule;

use App\Entity\Company;
use App\Enum\Scope;
use App\Scoring\ScoreContribution;
use App\Scoring\ScoringContext;
use App\Scoring\ScoringRuleInterface;

final class ScopeRule implements ScoringRuleInterface
{
    private const LOCAL_POINTS = 10;

    public function evaluate(Company $company, ScoringContext $context): array
    {
        return Scope::Local === $company->getScope()
            ? [new ScoreContribution(self::LOCAL_POINTS, 'Zone locale (Besançon et environs)')]
            : [];
    }
}
