<?php

namespace App\Import;

use App\Enum\InteractionChannel;
use App\Enum\InteractionOutcome;

final readonly class ProspectionInteraction
{
    public function __construct(
        public \DateTimeImmutable $occurredAt,
        public InteractionChannel $channel,
        public InteractionOutcome $outcome,
        public ?string $origin,
        public ?string $notes,
    ) {
    }
}
