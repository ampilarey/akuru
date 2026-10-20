<?php

namespace App\Domains\Library\Actions;

use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryCopyrightReport;
use App\Domains\Library\Models\LibraryItem;
use Illuminate\Validation\ValidationException;

/**
 * BP3 (BACKLOG B11): someone tells the Library a published work copies
 * their own. Signed in or not — a rights holder need not have an account.
 * The office hears of it in the app; the work stays on the shelf until the
 * office decides. One open report per work and address: sending again
 * while one is open is refused, so a flood is one report.
 */
class ReportCopyrightAction
{
    /** @param  array{name: string, email: string, work_claimed: string, statement: string}  $data */
    public function execute(LibraryItem $item, array $data, ?int $userId): LibraryCopyrightReport
    {
        if ($item->status !== LibraryItemStatus::Published) {
            throw ValidationException::withMessages(['statement' => __('library_copyright.error_not_published')]);
        }
        $email = mb_strtolower(trim($data['email']));
        if (LibraryCopyrightReport::query()->where('library_item_id', $item->id)->where('email', $email)->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['statement' => __('library_copyright.error_already_open')]);
        }
        $year = app(ResolveAcademicYearForDateAction::class)->execute();
        $report = LibraryCopyrightReport::query()->create([
            'library_item_id' => $item->id,
            'reporter_user_id' => $userId,
            'name' => trim($data['name']),
            'email' => $email,
            'work_claimed' => trim($data['work_claimed']),
            'statement' => trim($data['statement']),
            'status' => 'open',
            'academic_year_id' => $year === null ? null : (int) $year['id'],
        ]);

        app(NotifyLibraryUserAction::class)->office(
            __('library_copyright.notice_new_title'),
            __('library_copyright.notice_new_body', ['title' => (string) $item->title, 'name' => $report->name]),
            '/admin/library/copyright',
        );

        return $report;
    }
}
