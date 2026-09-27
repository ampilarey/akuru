<?php

namespace App\Domains\Library\Actions;

/**
 * B8 (LIBRARY_PLAN §10, STATUS §5io): a child's library as their parent
 * may see it.
 *
 * Reading progress and purchases only. Bookmarks and notes are the
 * reader's private words (§9.1: "writers never see private reader notes",
 * and neither does anyone else), so they are not in this view even for a
 * parent; the child opens those on their own `/my-library`. Whether the
 * caller *is* the child's verified guardian is the Portal's question, asked
 * of People before this is called.
 */
class ListReaderLibraryForFamilyAction
{
    /**
     * @return array{continue: list<array<string, mixed>>, purchases: list<array<string, mixed>>}
     */
    public function execute(int $readerUserId): array
    {
        $library = app(ListMyLibraryAction::class)->execute($readerUserId);

        return [
            'continue' => $library['continue'],
            'purchases' => $library['purchases'],
        ];
    }
}
