<?php

namespace App\Signal;

enum IngestOutcome
{
    case Created;
    case Duplicate;
    case Filtered;
}
