<?php

namespace App\Signal;

use App\Enum\SignalType;

final readonly class RawSignal
{
    /** @param array<string, mixed> $extracted */
    public function __construct(
        public SignalType $type,
        public string $source,
        public string $fingerprint,
        public string $title,
        public ?string $url = null,
        public ?string $companyName = null,
        public ?string $rawContent = null,
        public array $extracted = [],
        public ?\DateTimeImmutable $publishedAt = null,
        public ?\DateTimeImmutable $observedAt = null,
    ) {
    }
}
