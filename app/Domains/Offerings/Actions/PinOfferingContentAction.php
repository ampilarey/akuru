<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Courses\Actions\ListPublishedLessonPinsAction;
use App\Domains\Offerings\Models\CourseOffering;

class PinOfferingContentAction
{
    public function execute(int $offeringId, ?int $pinnedBy = null): CourseOffering
    {
        $offering = CourseOffering::query()->findOrFail($offeringId);
        $pins = app(ListPublishedLessonPinsAction::class)->execute($offering->course_id);

        $offering->pin_mode = 'pinned';
        $offering->pinned_revision_json = $pins;
        $offering->pinned_at = now();
        $offering->pinned_by = $pinnedBy;
        $offering->save();

        return $offering->refresh();
    }
}
