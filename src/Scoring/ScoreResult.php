<?php

namespace App\Scoring;

final readonly class ScoreResult
{
    /** @param list<ScoreContribution> $contributions */
    public function __construct(
        public int $score,
        public array $contributions,
    ) {
    }

    /** Une ligne par contribution (« +20 Agence web »), puis le total. */
    public function explanation(): string
    {
        $lines = array_map(
            static fn (ScoreContribution $c): string => $c->eliminatory ? sprintf('Éliminée : %s', $c->label) : sprintf('%+d %s', $c->points, $c->label),
            $this->contributions,
        );
        $lines[] = sprintf('Total : %d', $this->score);

        return implode("\n", $lines);
    }
}
