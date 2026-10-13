<?php

namespace App\Scoring;

use App\Entity\Company;
use App\Repository\InteractionRepository;
use App\Repository\SignalRepository;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class CompanyScorer
{
    /** @param iterable<ScoringRuleInterface> $rules */
    public function __construct(
        #[AutowireIterator('app.scoring_rule')]
        private iterable $rules,
        private InteractionRepository $interactions,
        private SignalRepository $signals,
    ) {
    }

    public function score(Company $company, ScoringContext $context): ScoreResult
    {
        $contributions = [];
        foreach ($this->rules as $rule) {
            array_push($contributions, ...$rule->evaluate($company, $context));
        }

        $eliminatory = array_values(array_filter($contributions, static fn (ScoreContribution $c): bool => $c->eliminatory));
        if ([] !== $eliminatory) {
            return new ScoreResult(0, $eliminatory);
        }

        usort($contributions, static fn (ScoreContribution $a, ScoreContribution $b): int => $b->points <=> $a->points);
        $total = array_sum(array_map(static fn (ScoreContribution $c): int => $c->points, $contributions));

        return new ScoreResult(max(0, min(100, $total)), $contributions);
    }

    /** Recalcule et enregistre le score de l'entreprise (sans flush). */
    public function rescore(Company $company, ?\DateTimeImmutable $now = null): ScoreResult
    {
        $result = $this->score($company, new ScoringContext(
            $this->interactions->findByCompany($company),
            $this->signals->findByCompany($company),
            $now ?? new \DateTimeImmutable(),
        ));

        $company->setScore($result->score);
        $company->setScoreExplanation($result->explanation());

        return $result;
    }
}
