<?php

namespace App\Enum;

enum CompanyType: string
{
    case Esn = 'ESN';
    case WebAgency = 'WEB_AGENCY';
    case ComAgency = 'COM_AGENCY';
    case EndClient = 'END_CLIENT';
    case Unknown = 'UNKNOWN';
}
