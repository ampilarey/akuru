<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\PickupWindow;

/**
 * Step 1: staff open or close pick-up for a date.
 *
 * The window is what makes this a protocol rather than a button. Without it a
 * guardian could request at any hour and the office would have no way to say
 * "not yet".
 *
 * Idempotent: opening an already-open day reopens it rather than erroring,
 * because the office clicking twice is not a mistake worth blocking.
 */
class OpenPickupWindowAction
{
    public function open(int $userId, ?string $date = null): PickupWindow
    {
        $date ??= now()->toDateString();

        return PickupWindow::query()->updateOrCreate(
            ['date' => $date],
            ['opened_by' => $userId, 'opened_at' => now(), 'closed_at' => null],
        );
    }

    public function close(?string $date = null): bool
    {
        $date ??= now()->toDateString();

        return PickupWindow::query()
            ->whereDate('date', $date)
            ->whereNull('closed_at')
            ->update(['closed_at' => now()]) > 0;
    }

    public function isOpen(?string $date = null): bool
    {
        $date ??= now()->toDateString();

        return PickupWindow::query()->whereDate('date', $date)->whereNull('closed_at')->exists();
    }
}
