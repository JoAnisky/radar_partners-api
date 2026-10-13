<?php

namespace App\Scoring;

use App\Entity\Interaction;
use App\Entity\Signal;

/** Données liées à une entreprise, chargées une fois pour toutes les règles. */
final readonly class ScoringContext
{
    /**
     * @param list<Interaction> $interactions
     * @param list<Signal>      $signals
     */
    public function __construct(
        public array $interactions,
        public array $signals,
        public \DateTimeImmutable $now,
    ) {
    }
}
