<?php

namespace App\Tests\Scoring\Rule;

use App\Entity\Company;
use App\Entity\Interaction;
use App\Entity\Signal;
use App\Enum\InteractionChannel;
use App\Enum\InteractionOutcome;
use App\Enum\SignalType;
use App\Scoring\ScoreContribution;
use App\Scoring\ScoringContext;
use App\Scoring\ScoringRuleInterface;
use PHPUnit\Framework\TestCase;

abstract class RuleTestCase extends TestCase
{
    protected const NOW = '2026-10-13 12:00:00';

    /**
     * @param list<Interaction> $interactions
     * @param list<Signal>      $signals
     */
    protected static function context(array $interactions = [], array $signals = []): ScoringContext
    {
        return new ScoringContext($interactions, $signals, new \DateTimeImmutable(self::NOW));
    }

    protected static function signal(string $detectedAt): Signal
    {
        return (new Signal(SignalType::JobPosting, 'test', hash('sha256', $detectedAt.random_bytes(4))))->setDetectedAt(new \DateTimeImmutable($detectedAt));
    }

    protected static function interaction(Company $company, InteractionChannel $channel = InteractionChannel::Phone, InteractionOutcome $outcome = InteractionOutcome::Other, ?string $origin = null): Interaction
    {
        return (new Interaction($company, $channel, new \DateTimeImmutable('2026-10-01')))->setOutcome($outcome)->setOrigin($origin);
    }

    /**
     * @return list<array{int, string}> [points, libellé] de chaque contribution
     */
    protected static function summary(ScoringRuleInterface $rule, Company $company, ?ScoringContext $context = null): array
    {
        return array_map(
            static fn (ScoreContribution $c): array => [$c->points, $c->label],
            $rule->evaluate($company, $context ?? self::context()),
        );
    }
}
