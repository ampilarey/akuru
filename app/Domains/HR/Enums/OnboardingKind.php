<?php

namespace App\Domains\HR\Enums;

enum OnboardingKind: string
{
    case Onboarding = 'onboarding';
    case Offboarding = 'offboarding';
}
