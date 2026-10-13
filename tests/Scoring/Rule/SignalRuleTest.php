<?php

namespace App\Tests\Scoring\Rule;

use App\Entity\Company;
use App\Scoring\Rule\SignalRule;

final class SignalRuleTest extends RuleTestCase
{
    public function testNoRecentSignalScoresNothing(): void
    {
        $context = self::context([], [self::signal('2026-05-01')]);

        self::assertSame([], self::summary(new SignalRule(), new Company('A'), $context));
    }

    public function testOneRecentSignal(): void
    {
        $context = self::context([], [self::signal('2026-09-01')]);

        self::assertSame([[25, '1 offre(s) dans la stack (90 jours)']], self::summary(new SignalRule(), new Company('A'), $context));
    }

    public function testFreshSignalAddsABonus(): void
    {
        $context = self::context([], [self::signal('2026-10-10')]);

        $result = self::summary(new SignalRule(), new Company('A'), $context);

        self::assertSame([25, 10], array_column($result, 0));
        self::assertSame('Offre de moins de 14 jours', $result[1][1]);
    }

    public function testSeveralSignalsAreCapped(): void
    {
        $signals = array_map(static fn (int $i): \App\Entity\Signal => self::signal(sprintf('2026-09-%02d', $i)), range(1, 8));

        self::assertSame(40, self::summary(new SignalRule(), new Company('A'), self::context([], $signals))[0][0]);
    }
}
