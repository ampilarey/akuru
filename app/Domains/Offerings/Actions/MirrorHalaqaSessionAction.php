<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Models\OfferingHalaqaLink;
use App\Domains\Offerings\Models\OfferingHalaqaSessionLink;
use App\Support\Contracts\HalaqaReferenceReader;

class MirrorHalaqaSessionAction
{
    /**
     * @return array{created: bool, session_id: int|null}
     */
    public function execute(int $hifzSessionId, ?int $offeringId = null): array
    {
        $hifzSession = app(HalaqaReferenceReader::class)->findSession($hifzSessionId);
        if ($hifzSession === null) {
            return ['created' => false, 'session_id' => null];
        }

        $link = OfferingHalaqaLink::query()
            ->where('hifz_program_id', $hifzSession['hifz_program_id'])
            ->where('dual_write', true)
            ->when($offeringId, fn ($query) => $query->where('course_offering_id', $offeringId))
            ->first();
        if ($link === null) {
            return ['created' => false, 'session_id' => null];
        }

        $existing = OfferingHalaqaSessionLink::query()
            ->where('hifz_session_id', $hifzSessionId)
            ->first();
        if ($existing !== null) {
            return ['created' => false, 'session_id' => (int) $existing->course_offering_session_id];
        }

        $starts = $this->startsAt($hifzSession);
        $session = app(SaveOfferingSessionAction::class)->execute([
            'course_offering_id' => $link->course_offering_id,
            'title' => $hifzSession['title'] ?: ('Halaqa '.$hifzSession['session_date']),
            'session_type' => 'face_to_face',
            'starts_at' => $starts,
            'ends_at' => $this->endsAt($hifzSession, $starts),
        ]);

        app(SaveOfferingHalaqaSessionLinkAction::class)->execute([
            'course_offering_session_id' => $session->id,
            'hifz_session_id' => $hifzSessionId,
        ]);

        return ['created' => true, 'session_id' => $session->id];
    }

    /**
     * @param  array<string, mixed>  $hifzSession
     */
    private function startsAt(array $hifzSession): string
    {
        $date = (string) ($hifzSession['session_date'] ?? now()->toDateString());
        $time = $this->clock($hifzSession['start_time'] ?? null) ?? '08:00:00';

        return $date.' '.$time;
    }

    /**
     * @param  array<string, mixed>  $hifzSession
     */
    private function endsAt(array $hifzSession, string $starts): ?string
    {
        $time = $this->clock($hifzSession['end_time'] ?? null);
        if ($time === null) {
            return null;
        }

        $date = (string) ($hifzSession['session_date'] ?? now()->toDateString());

        return $date.' '.$time;
    }

    private function clock(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }

        return (string) $value;
    }
}
