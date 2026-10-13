<?php

namespace App\Scoring\Rule;

use App\Entity\Company;
use App\Scoring\ScoreContribution;
use App\Scoring\ScoringContext;
use App\Scoring\ScoringRuleInterface;

final class FlagsRule implements ScoringRuleInterface
{
    private const PENALTIES = [
        'IN_DIFFICULTY' => [-15, 'En difficulté'],
        'MAYBE_INACTIVE' => [-15, 'Peut-être inactive'],
    ];

    public function evaluate(Company $company, ScoringContext $context): array
    {
        $contributions = [];
        foreach (self::PENALTIES as $flag => [$points, $label]) {
            if (\in_array($flag, $company->getFlags(), true)) {
                $contributions[] = new ScoreContribution($points, $label);
            }
        }

        return $contributions;
    }
}
