<?php

namespace App\Domains\Academics\Enums;

enum PromotionOutcome: string
{
    case Promote = 'promote';
    case Repeat = 'repeat';
    case Leave = 'leave';
    case Graduate = 'graduate';
}
