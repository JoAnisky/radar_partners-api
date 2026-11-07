<?php

namespace App\Scoring\Rule;

use App\Entity\Company;
use App\Enum\CompanyType;
use App\Scoring\ScoreContribution;
use App\Scoring\ScoringContext;
use App\Scoring\ScoringRuleInterface;

/** Les ESN et agences sont la cible ; client final, particulier et inconnu ne rapportent rien. */
final class CompanyTypeRule implements ScoringRuleInterface
{
    private const TARGET_POINTS = 20;

    public function evaluate(Company $company, ScoringContext $context): array
    {
        return match ($company->getType()) {
            CompanyType::Esn => [new ScoreContribution(self::TARGET_POINTS, 'ESN')],
            CompanyType::WebAgency => [new ScoreContribution(self::TARGET_POINTS, 'Agence web')],
            CompanyType::ComAgency => [new ScoreContribution(self::TARGET_POINTS, 'Agence de communication')],
            CompanyType::EndClient,
            CompanyType::Individual,
            CompanyType::Unknown => [],
        };
    }
}
