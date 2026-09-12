<?php

namespace App\Domains\Library\Actions;

use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use App\Domains\Library\Enums\LibraryReadingSignal;
use App\Domains\Library\Models\LibraryReadingAlert;
use App\Domains\Library\Models\LibraryReadingEvent;

/**
 * LIBRARY_PLAN §9.2 / §30.3 — the three patterns, evaluated against the event
 * log after a page is served.
 *
 * Two things this deliberately does not do.
 *
 * **It does not accuse.** Each signal is a question for a person: a reader
 * skimming a reference book legitimately turns pages fast, and a family sharing
 * one account across a phone, a tablet and a laptop is not a book being resold.
 * The alert says what was observed against what threshold, and a human decides.
 *
 * **It does not block, unless an operator turns enforcement on.** §9.2 does say
 * "limit simultaneous sessions", and `shouldBlock()` implements exactly that —
 * but it is off by default, because refusing a page locks a paying reader out
 * of a book they own on the strength of a number nobody has yet checked against
 * a real reader.
 *
 * Alerts de-duplicate while open: a reader who flips pages fast for ten minutes
 * is one concern, not six hundred rows.
 */
class DetectLibraryReadingAbuseAction
{
    /**
     * @return list<LibraryReadingAlert>
     */
    public function execute(int $userId, ?int $libraryItemId = null): array
    {
        $config = (array) config('library.abuse', []);
        $raised = [];

        $rapid = $this->countRapidPages($userId, $libraryItemId, (int) ($config['rapid_pages_window_seconds'] ?? 60));
        $rapidLimit = (int) ($config['rapid_pages_threshold'] ?? 40);
        if ($rapidLimit > 0 && $rapid > $rapidLimit) {
            $raised[] = $this->raise(
                $userId,
                $libraryItemId,
                LibraryReadingSignal::RapidPages,
                $rapid,
                $rapidLimit,
                sprintf('%d pages in %d seconds.', $rapid, (int) ($config['rapid_pages_window_seconds'] ?? 60)),
            );
        }

        $devices = $this->countDistinct($userId, 'device_hash', now('Indian/Maldives')
            ->subHours(max(1, (int) ($config['device_window_hours'] ?? 24))));
        $deviceLimit = (int) ($config['device_threshold'] ?? 5);
        if ($deviceLimit > 0 && $devices > $deviceLimit) {
            $raised[] = $this->raise(
                $userId,
                null,
                LibraryReadingSignal::ManyDevices,
                $devices,
                $deviceLimit,
                sprintf('%d distinct devices in %d hours.', $devices, (int) ($config['device_window_hours'] ?? 24)),
            );
        }

        $sessions = $this->countDistinct($userId, 'session_hash', now('Indian/Maldives')
            ->subMinutes(max(1, (int) ($config['session_window_minutes'] ?? 10))));
        $sessionLimit = (int) ($config['session_threshold'] ?? 3);
        if ($sessionLimit > 0 && $sessions > $sessionLimit) {
            $raised[] = $this->raise(
                $userId,
                null,
                LibraryReadingSignal::ConcurrentSessions,
                $sessions,
                $sessionLimit,
                sprintf('%d sessions active in %d minutes.', $sessions, (int) ($config['session_window_minutes'] ?? 10)),
            );
        }

        return array_values(array_filter($raised));
    }

    /**
     * §9.2's "limit simultaneous sessions", and the only method here that can
     * refuse a reader. Off unless an operator has turned it on.
     */
    public function shouldBlock(int $userId): bool
    {
        $config = (array) config('library.abuse', []);
        if (! ($config['enforce'] ?? false)) {
            return false;
        }

        $limit = (int) ($config['session_threshold'] ?? 3);
        if ($limit <= 0) {
            return false;
        }

        return $this->countDistinct(
            $userId,
            'session_hash',
            now('Indian/Maldives')->subMinutes(max(1, (int) ($config['session_window_minutes'] ?? 10))),
        ) > $limit;
    }

    private function countRapidPages(int $userId, ?int $libraryItemId, int $windowSeconds): int
    {
        return LibraryReadingEvent::query()
            ->where('user_id', $userId)
            ->when($libraryItemId, fn ($q) => $q->where('library_item_id', $libraryItemId))
            ->where('occurred_at', '>=', now('Indian/Maldives')->subSeconds(max(1, $windowSeconds)))
            ->count();
    }

    private function countDistinct(int $userId, string $column, mixed $since): int
    {
        return LibraryReadingEvent::query()
            ->where('user_id', $userId)
            ->whereNotNull($column)
            ->where('occurred_at', '>=', $since)
            ->distinct()
            ->count($column);
    }

    private function raise(
        int $userId,
        ?int $libraryItemId,
        LibraryReadingSignal $signal,
        int $observed,
        int $threshold,
        string $detail,
    ): ?LibraryReadingAlert {
        $open = LibraryReadingAlert::query()
            ->where('user_id', $userId)
            ->where('signal', $signal->value)
            ->whereNull('reviewed_at')
            ->first();

        if ($open !== null) {
            // Keep the worst reading rather than the latest: what a reviewer
            // needs to judge is how far past the line this went.
            if ($observed > (int) $open->observed) {
                $open->update(['observed' => $observed, 'detail' => $detail]);
            }

            return null;
        }

        $year = app(ResolveAcademicYearForDateAction::class)->execute();

        return LibraryReadingAlert::query()->create([
            'user_id' => $userId,
            'library_item_id' => $libraryItemId,
            'signal' => $signal->value,
            'observed' => $observed,
            'threshold' => $threshold,
            'detail' => $detail,
            'academic_year_id' => $year === null ? null : (int) $year['id'],
        ]);
    }
}
