<?php

namespace App\Domains\Academics\Enums;

enum BehaviorType: string
{
    case Compliment = 'compliment';
    case Notice = 'notice';
    case Warning = 'warning';
    case Incident = 'incident';
}
