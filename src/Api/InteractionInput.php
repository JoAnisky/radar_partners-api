<?php

namespace App\Api;

use App\Enum\InteractionChannel;
use App\Enum\InteractionOutcome;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class InteractionInput
{
    public function __construct(
        public InteractionChannel $channel,
        public ?\DateTimeImmutable $occurredAt = null,
        public InteractionOutcome $outcome = InteractionOutcome::Other,
        public ?int $contactId = null,
        #[Assert\Length(max: 255)]
        public ?string $origin = null,
        #[Assert\Length(max: 10000)]
        public ?string $notes = null,
        public ?\DateTimeImmutable $nextActionAt = null,
    ) {
    }
}
