<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\SmsReceipt;

class AbsenceWasNotifiedAction
{
    public function execute(int $studentId, string $date): bool
    {
        return SmsReceipt::query()
            ->where('type', 'attendance')
            ->where('reference', 'attendance_'.$date.'_'.$studentId)
            ->where('success', true)
            ->exists();
    }
}
