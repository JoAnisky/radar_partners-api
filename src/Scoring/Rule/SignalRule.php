<?php

namespace App\Scoring\Rule;

use App\Entity\Company;
use App\Scoring\ScoreContribution;
use App\Scoring\ScoringContext;
use App\Scoring\ScoringRuleInterface;

/** Les signaux rattachés ont déjà passé le filtre de stack : chaque offre récente est un besoin potentiel. */
final class SignalRule implements ScoringRuleInterface
{
    private const WINDOW = '-90 days';
    private const FRESH_WINDOW = '-14 days';
    private const FIRST_POINTS = 25;
    private const EXTRA_POINTS = 5;
    private const MAX_POINTS = 40;
    private const FRESH_POINTS = 10;

    public function evaluate(Company $company, ScoringContext $context): array
    {
        $since = $context->now->modify(self::WINDOW);
        $freshSince = $context->now->modify(self::FRESH_WINDOW);

        $recent = array_filter($context->signals, static fn ($s): bool => $s->getDetectedAt() >= $since);
        if ([] === $recent) {
            return [];
        }

        $count = \count($recent);
        $contributions = [new ScoreContribution(
            min(self::MAX_POINTS, self::FIRST_POINTS + self::EXTRA_POINTS * ($count - 1)),
            sprintf('%d offre(s) dans la stack (90 jours)', $count),
        )];

        if ([] !== array_filter($recent, static fn ($s): bool => $s->getDetectedAt() >= $freshSince)) {
            $contributions[] = new ScoreContribution(self::FRESH_POINTS, 'Offre de moins de 14 jours');
        }

        return $contributions;
    }
}
