<?php

namespace App\Domains\Library\Actions;

use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use App\Domains\Library\Models\LibraryReadingEvent;

/**
 * LIBRARY_PLAN §9.2 — "log every session". One row per page delivered.
 *
 * **Identity is hashed on the way in, and the raw value never reaches the
 * database.** Every question §30.3 asks — is this the same device? how many at
 * once? — is answerable from a hash, so storing the address itself would add
 * no capability and a great deal of liability. Some readers here are children.
 *
 * The hash is peppered with the application key, which is what stops it being
 * reversed: an IPv4 space is small enough to enumerate in seconds against a
 * bare SHA-256.
 */
class RecordLibraryReadingEventAction
{
    public function execute(
        ?int $userId,
        int $libraryItemId,
        int $pageNumber,
        ?string $sessionId = null,
        ?string $ip = null,
        ?string $userAgent = null,
    ): ?LibraryReadingEvent {
        // Anonymous reading of free content is not a session to police, and an
        // event with no reader answers none of §30.3's questions.
        if ($userId === null) {
            return null;
        }

        $now = now('Indian/Maldives');
        $year = app(ResolveAcademicYearForDateAction::class)->execute($now->toDateString());

        return LibraryReadingEvent::query()->create([
            'user_id' => $userId,
            'library_item_id' => $libraryItemId,
            'page_number' => $pageNumber,
            'session_hash' => $this->pepper($sessionId),
            // Device identity is the pair, not either half: the same browser on
            // a new IP is the same device, and two people behind one office NAT
            // are not.
            'device_hash' => $this->pepper(
                ($ip === null && $userAgent === null) ? null : $ip.'|'.$userAgent
            ),
            'academic_year_id' => $year === null ? null : (int) $year['id'],
            'occurred_at' => $now,
        ]);
    }

    private function pepper(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return hash('sha256', config('app.key').'|library-reading|'.$value);
    }
}
