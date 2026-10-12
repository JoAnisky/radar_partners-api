<?php

namespace App\Import;

final readonly class ProspectionRow
{
    /**
     * @param list<ProspectionInteraction> $interactions
     * @param list<string>                 $flags
     * @param list<string>                 $warnings
     */
    public function __construct(
        public string $name,
        public ?string $website,
        public ?string $address,
        public ?string $postalCode,
        public ?string $city,
        public ?string $phone,
        public ?string $email,
        public ?string $contactName,
        public ?string $contactPhone,
        public array $interactions,
        public ?string $notes,
        public array $flags,
        public array $warnings,
    ) {
    }
}
