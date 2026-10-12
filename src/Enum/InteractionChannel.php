<?php

namespace App\Enum;

enum InteractionChannel: string
{
    case Phone = 'PHONE';
    case Email = 'EMAIL';
    case InPerson = 'IN_PERSON';
    case Linkedin = 'LINKEDIN';
    case WebForm = 'WEB_FORM';
}
