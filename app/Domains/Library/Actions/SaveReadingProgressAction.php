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

    /**
     * §9.1 "reading time": the reader's beacon, on leaving a page, reports
     * how long that page was in front of them. Time only — the page they are
     * on is the *next* request's business, and a beacon that also moved the
     * page could land after it and move the reader backwards (STATUS §5ju).
     * No row means no reading has been recorded, so there is nothing to add
     * to; a preview never has a row (a sample is not reading, §9.4).
     */
    public function addSeconds(int $userId, int $itemId, int $seconds): ?LibraryReadingProgress
    {
        if ($seconds <= 0) {
            return null;
        }

        $progress = LibraryReadingProgress::query()
            ->where('user_id', $userId)
            ->where('library_item_id', $itemId)
            ->first();
        if ($progress === null) {
            return null;
        }

        $progress->total_reading_seconds = (int) $progress->total_reading_seconds + min($seconds, 3600);
        $progress->last_read_at = now();
        $progress->save();

        return $progress->refresh();
    }
}
