<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Support\LiveSms;

/**
 * Whether an SMS reaches a handset here (production with `SMS_LIVE` on),
 * for other domains, which may not read Notifications' support classes
 * (rule 3). COMMERCE_PARITY_PLAN P1 asks it before offering a code.
 */
class LiveSmsAllowedAction
{
    public function execute(): bool
    {
        return LiveSms::allowed();
    }
}
