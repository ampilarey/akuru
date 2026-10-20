<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\ReportCopyrightAction;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** BP3 (BACKLOG B11): a copyright report from a published work's page — signed in or not. */
class LibraryCopyrightController extends Controller
{
    public function store(Request $request, string $slug): RedirectResponse
    {
        $item = LibraryItem::query()->where('slug', $slug)->where('status', LibraryItemStatus::Published)->firstOrFail();
        // Back to the form, not the top of the page, either way; its own
        // error bag, so the page's comment form does not show its refusals.
        $back = url()->previous().'#copyright-report';
        try {
            $data = $request->validate([
                'name' => 'required|string|max:120',
                'email' => 'required|email|max:190',
                'work_claimed' => 'required|string|max:300',
                'statement' => 'required|string|max:5000',
                'good_faith' => 'accepted',
            ], ['good_faith.accepted' => __('library_copyright.error_good_faith')], [
                'name' => __('library_copyright.field_name'), 'email' => __('library_copyright.field_email'),
                'work_claimed' => __('library_copyright.field_work'), 'statement' => __('library_copyright.field_statement'),
            ]);
            app(ReportCopyrightAction::class)->execute($item, $data, $request->user()?->id);
        } catch (ValidationException $e) {
            throw $e->errorBag('copyright')->redirectTo($back);
        }

        return redirect()->to($back)->with('copyright_sent', __('library_copyright.flash_sent'));
    }
}
