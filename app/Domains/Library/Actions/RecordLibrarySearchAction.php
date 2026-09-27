<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibrarySearchLog;

/**
 * B14 (§29): remember what the shelf was asked for. The term is trimmed,
 * lower-cased and capped, so "Tafsir" and "tafsir " count as one question.
 */
class RecordLibrarySearchAction
{
    public function execute(string $term, int $hits, ?int $userId = null): void
    {
        $term = mb_strtolower(trim(mb_substr($term, 0, 100)));
        if ($term === '') {
            return;
        }

        LibrarySearchLog::query()->create([
            'term' => $term,
            'hits' => max(0, $hits),
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }
}
