<?php

namespace App\Enum;

enum InteractionOutcome: string
{
    case NoAnswer = 'NO_ANSWER';
    case Voicemail = 'VOICEMAIL';
    case NobodyOnSite = 'NOBODY_ON_SITE';
    case NoNeed = 'NO_NEED';
    case NoFreelancePolicy = 'NO_FREELANCE_POLICY';
    case JustHired = 'JUST_HIRED';
    case MeetingScheduled = 'MEETING_SCHEDULED';
    case Positive = 'POSITIVE';
    case Other = 'OTHER';
}
