<?php

namespace App\Enum;

enum SignalType: string
{
    case JobPosting = 'JOB_POSTING';
    case FreelanceMission = 'FREELANCE_MISSION';
    case StackDetected = 'STACK_DETECTED';
    case CompanyCreated = 'COMPANY_CREATED';
    case RecentHire = 'RECENT_HIRE';
}
