<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryCopyrightReport;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BP3 (BACKLOG B11): the office decides a copyright report, always with a
 * note. Upheld, the work is archived — off the shelf and out of search —
 * and its writer is told why; dismissed, the work stays and the report is
 * kept with the reason. A decided report is not decided again.
 */
class DecideCopyrightReportAction
{
    public function execute(LibraryCopyrightReport $report, string $outcome, string $note, int $officeUserId): LibraryCopyrightReport
    {
        if (! in_array($outcome, ['upheld', 'dismissed'], true)) {
            throw ValidationException::withMessages(['outcome' => __('library_copyright.error_outcome')]);
        }
        if (trim($note) === '') {
            throw ValidationException::withMessages(['note' => __('library_copyright.error_note')]);
        }

        return DB::transaction(function () use ($report, $outcome, $note, $officeUserId) {
            $report = LibraryCopyrightReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            if ($report->status !== 'open') {
                throw ValidationException::withMessages(['outcome' => __('library_copyright.error_decided')]);
            }
            $report->update(['status' => $outcome, 'decided_by' => $officeUserId, 'decided_at' => now(), 'decision_note' => mb_substr(trim($note), 0, 1000)]);

            $item = $report->item;
            if ($outcome === 'upheld' && $item !== null) {
                $item->update(['status' => LibraryItemStatus::Archived]);
                $writerUserId = $item->writer_id === null ? null : WriterProfile::query()->whereKey($item->writer_id)->value('user_id');
                if ($writerUserId !== null) {
                    app(NotifyLibraryUserAction::class)->execute(
                        (int) $writerUserId,
                        __('library_copyright.notice_upheld_title'),
                        __('library_copyright.notice_upheld_body', ['title' => (string) $item->title, 'note' => trim($note)]),
                        '/write',
                    );
                }
            }

            return $report->refresh();
        });
    }
}
