<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Courses\Actions\ListPublishedLessonPinsAction;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Offerings\Models\OfferingRepinEvent;
use Illuminate\Support\Facades\DB;

class PinOfferingContentAction
{
    /**
     * SPEC §28.4: re-pinning must be deliberate, and when it happens the
     * system must record the old version, the new version, who changed it,
     * when, and an optional reason.
     *
     * This used to overwrite `pinned_revision_json` in place. The offering
     * kept `pinned_by`/`pinned_at` for the latest pin only, so the previous
     * version vanished the moment it was replaced — and re-pinning changes
     * what enrolled students see mid-offering, which is the whole reason
     * §28.4 asks for it to be deliberate and traceable.
     *
     * The event is written in the same transaction as the pin, so the audit
     * trail cannot fall out of step with the thing it describes.
     */
    public function execute(int $offeringId, ?int $pinnedBy = null, ?string $reason = null): CourseOffering
    {
        return DB::transaction(function () use ($offeringId, $pinnedBy, $reason): CourseOffering {
            $offering = CourseOffering::query()->lockForUpdate()->findOrFail($offeringId);
            $pins = app(ListPublishedLessonPinsAction::class)->execute($offering->course_id);

            $oldPins = $offering->pinned_revision_json;
            $oldMode = $offering->pin_mode;

            $offering->pin_mode = 'pinned';
            $offering->pinned_revision_json = $pins;
            $offering->pinned_at = now();
            $offering->pinned_by = $pinnedBy;
            $offering->save();

            OfferingRepinEvent::query()->create([
                'course_offering_id' => $offering->id,
                // Rule 10: a re-pin happens in time. The offering's own year is
                // the right one — not today's — because that is the year the
                // affected teaching belongs to.
                'academic_year_id' => $offering->academic_year_id ?? null,
                'old_pinned_revision_json' => $oldPins,
                'new_pinned_revision_json' => $pins,
                'old_pin_mode' => $oldMode,
                'new_pin_mode' => 'pinned',
                'changed_by' => $pinnedBy,
                'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            ]);

            return $offering->refresh();
        });
    }
}
