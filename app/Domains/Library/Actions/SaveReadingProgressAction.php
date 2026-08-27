<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryReadingProgress;

/**
 * §35.3 upsert. current_page follows where the reader is; completed_at is
 * set once when the last page is reached and never cleared by re-reading.
 * Reading seconds accumulate when the beacon reports them.
 */
class SaveReadingProgressAction
{
    public function execute(int $userId, int $itemId, int $page, int $totalPages, int $addSeconds = 0): LibraryReadingProgress
    {
        $progress = LibraryReadingProgress::query()->firstOrNew([
            'user_id' => $userId,
            'library_item_id' => $itemId,
        ]);
        $progress->current_page = $page;
        $progress->progress_percent = $totalPages > 0 ? (int) round($page / $totalPages * 100) : 0;
        $progress->last_read_at = now();
        $progress->total_reading_seconds = (int) $progress->total_reading_seconds + max(0, $addSeconds);
        if ($totalPages > 0 && $page >= $totalPages && $progress->completed_at === null) {
            $progress->completed_at = now();
        }
        $progress->save();

        return $progress->refresh();
    }
}
