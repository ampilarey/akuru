<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Models\CourseOfferingSession;
use App\Domains\Offerings\Models\OfferingHalaqaLink;
use App\Domains\Offerings\Models\OfferingHalaqaSessionLink;
use App\Support\Contracts\HalaqaReferenceReader;

/**
 * F1 (Qur'an A.4b) verification gate — rule 9 "switch reads" step for the
 * halaqa mapping. Engine offering sessions become the authoritative read for
 * halaqa-linked offerings; this proves the mirror is complete first.
 *
 * Read-only by default. mirrorMissing() heals via the existing idempotent
 * MirrorHalaqaSessionAction — additive only, never deletes.
 */
class VerifyHalaqaMirrorAction
{
    /**
     * @return array{
     *     links: int,
     *     legacy_sessions: int,
     *     mirrored: int,
     *     missing: list<array{hifz_program_id: int, hifz_session_id: int, title: string}>,
     *     orphan_links: list<array{hifz_session_id: int, course_offering_session_id: int}>,
     *     ok: bool
     * }
     */
    public function execute(): array
    {
        $reader = app(HalaqaReferenceReader::class);
        // Only dual_write links claim a complete mirror; a mapped-but-not-yet-
        // dual-writing link is allowed to lag and must not fail the gate.
        $links = OfferingHalaqaLink::query()->where('dual_write', true)->get();

        $legacyTotal = 0;
        $mirrored = 0;
        $missing = [];

        foreach ($links as $link) {
            $programId = (int) $link->hifz_program_id;
            foreach ($reader->listSessions($programId) as $session) {
                $legacyTotal++;
                $sessionId = (int) ($session['id'] ?? 0);
                $exists = OfferingHalaqaSessionLink::query()
                    ->where('hifz_session_id', $sessionId)
                    ->exists();
                if ($exists) {
                    $mirrored++;
                } else {
                    $missing[] = [
                        'hifz_program_id' => $programId,
                        'hifz_session_id' => $sessionId,
                        'title' => (string) ($session['title'] ?? ''),
                    ];
                }
            }
        }

        // A link whose engine session vanished is drift in the other direction.
        $orphans = OfferingHalaqaSessionLink::query()
            ->get()
            ->filter(fn (OfferingHalaqaSessionLink $link) => ! CourseOfferingSession::query()
                ->whereKey($link->course_offering_session_id)
                ->exists())
            ->map(fn (OfferingHalaqaSessionLink $link) => [
                'hifz_session_id' => (int) $link->hifz_session_id,
                'course_offering_session_id' => (int) $link->course_offering_session_id,
            ])
            ->values()
            ->all();

        return [
            'links' => $links->count(),
            'legacy_sessions' => $legacyTotal,
            'mirrored' => $mirrored,
            'missing' => $missing,
            'orphan_links' => $orphans,
            'ok' => $missing === [] && $orphans === [],
        ];
    }

    /**
     * Heal missing mirrors (additive, idempotent). Returns sessions created.
     */
    public function mirrorMissing(): int
    {
        $created = 0;
        foreach ($this->execute()['missing'] as $row) {
            $result = app(MirrorHalaqaSessionAction::class)->execute($row['hifz_session_id']);
            if ($result['created']) {
                $created++;
            }
        }

        return $created;
    }
}
