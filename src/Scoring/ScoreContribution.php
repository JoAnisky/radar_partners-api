<?php

namespace App\Scoring;

final readonly class ScoreContribution
{
    public function __construct(
        public int $points,
        public string $label,
        public bool $eliminatory = false,
    ) {
    }
}
