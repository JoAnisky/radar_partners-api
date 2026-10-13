<?php

namespace App\Scoring;

use App\Entity\Company;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.scoring_rule')]
interface ScoringRuleInterface
{
    /** @return list<ScoreContribution> vide si la règle ne s'applique pas */
    public function evaluate(Company $company, ScoringContext $context): array;
}
