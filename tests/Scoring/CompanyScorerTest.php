<?php

namespace App\Tests\Scoring;

use App\Entity\Company;
use App\Repository\InteractionRepository;
use App\Repository\SignalRepository;
use App\Scoring\CompanyScorer;
use App\Scoring\ScoreContribution;
use App\Scoring\ScoringContext;
use App\Scoring\ScoringRuleInterface;
use PHPUnit\Framework\TestCase;

final class CompanyScorerTest extends TestCase
{
    /** @param list<ScoreContribution> $contributions */
    private static function rule(array $contributions): ScoringRuleInterface
    {
        return new readonly class($contributions) implements ScoringRuleInterface {
            /** @param list<ScoreContribution> $contributions */
            public function __construct(private array $contributions)
            {
            }

            public function evaluate(Company $company, ScoringContext $context): array
            {
                return $this->contributions;
            }
        };
    }

    /** @param list<ScoringRuleInterface> $rules */
    private function scorer(array $rules): CompanyScorer
    {
        $interactions = $this->createStub(InteractionRepository::class);
        $interactions->method('findByCompany')->willReturn([]);
        $signals = $this->createStub(SignalRepository::class);
        $signals->method('findByCompany')->willReturn([]);

        return new CompanyScorer($rules, $interactions, $signals);
    }

    private static function context(): ScoringContext
    {
        return new ScoringContext([], [], new \DateTimeImmutable('2026-10-13'));
    }

    public function testSumsContributionsAndSortsThemByPoints(): void
    {
        $scorer = $this->scorer([
            self::rule([new ScoreContribution(-10, 'Malus'), new ScoreContribution(5, 'Petit')]),
            self::rule([new ScoreContribution(30, 'Gros')]),
        ]);

        $result = $scorer->score(new Company('A'), self::context());

        self::assertSame(25, $result->score);
        self::assertSame(['Gros', 'Petit', 'Malus'], array_map(static fn (ScoreContribution $c): string => $c->label, $result->contributions));
        self::assertSame("+30 Gros\n+5 Petit\n-10 Malus\nTotal : 25", $result->explanation());
    }

    public function testScoreIsClampedBetweenZeroAndOneHundred(): void
    {
        $company = new Company('A');

        self::assertSame(100, $this->scorer([self::rule([new ScoreContribution(70, 'a'), new ScoreContribution(60, 'b')])])->score($company, self::context())->score);
        self::assertSame(0, $this->scorer([self::rule([new ScoreContribution(-40, 'a')])])->score($company, self::context())->score);
    }

    public function testEliminatoryRuleForcesZeroAndKeepsOnlyTheReason(): void
    {
        $scorer = $this->scorer([
            self::rule([new ScoreContribution(50, 'Bien')]),
            self::rule([new ScoreContribution(0, 'Client final', true)]),
        ]);

        $result = $scorer->score(new Company('A'), self::context());

        self::assertSame(0, $result->score);
        self::assertSame("Éliminée : Client final\nTotal : 0", $result->explanation());
    }

    public function testRescoreStoresScoreAndExplanationOnTheCompany(): void
    {
        $company = new Company('A');

        $this->scorer([self::rule([new ScoreContribution(20, 'Cible')])])->rescore($company);

        self::assertSame(20, $company->getScore());
        self::assertSame("+20 Cible\nTotal : 20", $company->getScoreExplanation());
    }
}
