<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Domains\Courses\Components\Quran\Models\QuranSessionRecord;

/**
 * F5-P1 supervisor step, mirroring the legacy review(): a staff reviewer
 * stamps the record and may leave a supervisor note; the
 * requires_supervisor_review flag clears.
 */
class ReviewQuranSessionRecordAction
{
    public function execute(int $recordId, int $reviewedBy, ?string $supervisorNote = null): QuranSessionRecord
    {
        $record = QuranSessionRecord::query()->findOrFail($recordId);
        $record->fill([
            'supervisor_note' => $supervisorNote ?? $record->supervisor_note,
            'requires_supervisor_review' => false,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ]);
        $record->save();

        return $record->refresh();
    }
}
