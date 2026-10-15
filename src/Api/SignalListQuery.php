<?php

namespace App\Api;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SignalListQuery
{
    public function __construct(
        /** Seulement les signaux sans entreprise rattachée. */
        public bool $unresolved = false,
        /** Seulement les signaux écartés (sinon, ils sont exclus). */
        public bool $dismissed = false,
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $perPage = 25,
    ) {
    }
}
