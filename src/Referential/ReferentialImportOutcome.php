<?php

namespace App\Referential;

enum ReferentialImportOutcome
{
    case Created;
    case Enriched;
    case AlreadyKnown;
    case Ambiguous;
}
