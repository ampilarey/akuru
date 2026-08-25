<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\Receipt;

class NextReceiptNumberAction
{
    public function execute(): string
    {
        $date = now('Indian/Maldives')->format('Ymd');
        $count = Receipt::query()->whereDate('created_at', now('Indian/Maldives')->toDateString())->count() + 1;

        return 'RCPT-'.$date.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
