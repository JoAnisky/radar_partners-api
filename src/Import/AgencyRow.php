<?php

namespace App\Import;

use App\Enum\CompanyType;
use App\Enum\Scope;
use App\Enum\Zone;

final readonly class AgencyRow
{
    public function __construct(
        public string $name,
        public ?string $city,
        public Zone $zone,
        public Scope $scope,
        public CompanyType $type,
        public ?string $notes,
        /** @var list<string> */
        public array $warnings = [],
    ) {
    }
}
