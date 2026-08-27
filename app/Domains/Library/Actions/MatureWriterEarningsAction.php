<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\WriterEarning;

/**
 * L6 (§23): pending earnings whose refund window has passed become
 * available. Called lazily wherever earnings are read or paid out — no
 * scheduler needed.
 */
class MatureWriterEarningsAction
{
    public function execute(?int $writerId = null): int
    {
        return WriterEarning::query()
            ->where('status', 'pending')
            ->where('available_at', '<=', now())
            ->when($writerId !== null, fn ($query) => $query->where('writer_id', $writerId))
            ->update(['status' => 'available']);
    }
}
