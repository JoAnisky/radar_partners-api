<?php

namespace App\Enum;

enum PipelineStatus: string
{
    case ToContact = 'TO_CONTACT';
    case Contacted = 'CONTACTED';
    case FollowUp = 'FOLLOW_UP';
    case Meeting = 'MEETING';
    case Partner = 'PARTNER';
    case OnHold = 'ON_HOLD';
    case Excluded = 'EXCLUDED';
}
