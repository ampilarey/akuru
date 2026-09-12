<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Models\CourseOffering;

/**
 * The offering's identity, for screens in other domains to show.
 *
 * SPEC §24 requires the Course Learning Page to show "Offering title/mode if
 * enrolled through an offering". The page held the offering id and never said
 * which offering it was, so a student enrolled in one of several batches could
 * not tell from that screen which one they were looking at.
 *
 * An Action rather than a model read, because rule 3 keeps `Courses` out of
 * `Offerings\Models`.
 */
class DescribeOfferingAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(int $offeringId): ?array
    {
        $offering = CourseOffering::query()->find($offeringId);

        if ($offering === null) {
            return null;
        }

        return [
            'id' => $offering->id,
            'title' => $offering->title,
            'delivery_mode' => $offering->delivery_mode instanceof \BackedEnum
                ? $offering->delivery_mode->value
                : $offering->delivery_mode,
            'status' => $offering->status instanceof \BackedEnum
                ? $offering->status->value
                : $offering->status,
        ];
    }
}
