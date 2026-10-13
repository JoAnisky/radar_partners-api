<?php

namespace App\Referential;

use App\Entity\Company;

final readonly class ReferentialImportResult
{
    public function __construct(
        public ReferentialImportOutcome $outcome,
        public ReferentialCompany $source,
        public ?Company $company = null,
        public ?string $note = null,
    ) {
    }
}
