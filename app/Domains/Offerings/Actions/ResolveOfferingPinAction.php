<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Models\CourseOffering;

class ResolveOfferingPinAction
{
    /**
     * @return array{id: int, course_id: int, pin_mode: string, pinned_revision_json: array<int, int>}
     */
    public function execute(int $offeringId): array
    {
        $offering = CourseOffering::query()->findOrFail($offeringId);
        $pins = is_array($offering->pinned_revision_json) ? $offering->pinned_revision_json : [];

        return [
            'id' => $offering->id,
            'course_id' => $offering->course_id,
            'pin_mode' => $offering->pin_mode,
            'seat_limit' => $offering->seat_limit,
            'pinned_revision_json' => $pins,
        ];
    }

    public function revisionIdForLesson(int $offeringId, int $lessonId): ?int
    {
        $offering = $this->execute($offeringId);
        if ($offering['pin_mode'] !== 'pinned') {
            return null;
        }

        $revision = $offering['pinned_revision_json'][$lessonId] ?? $offering['pinned_revision_json'][(string) $lessonId] ?? null;

        return $revision ? (int) $revision : null;
    }
}
