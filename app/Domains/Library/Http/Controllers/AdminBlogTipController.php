<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\Blog\ListTipsAction;
use App\Domains\Library\Actions\Blog\ModerateTipNoteAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BLOG_PLAN BL6a: the Library office's tips — every paid tip and reversal,
 * the notes waiting to be checked, and a CSV. Behind the Library office's
 * gate (`role:super_admin`, `can:library.manage`).
 */
class AdminBlogTipController extends Controller
{
    public function index(Request $request): Response
    {
        $notes = $request->boolean('notes');

        return Inertia::render('Library/BlogTips', [
            'tips' => app(ListTipsAction::class)->office($notes),
            'notes' => $notes,
            't' => Phrases::once('blog'),
        ]);
    }

    public function export(): StreamedResponse
    {
        $rows = app(ListTipsAction::class)->office(false, 5000);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'at', 'from', 'blog', 'post', 'amount', 'method', 'status', 'refund_of', 'writer_share', 'share_status', 'note', 'note_status']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['id'], $r['at'], $r['from'], $r['blog'], $r['post'], number_format($r['amount'], 2, '.', ''), $r['method'], $r['status'], $r['refund_of'], $r['writer_share'] !== null ? number_format($r['writer_share'], 2, '.', '') : '', $r['earning_status'], $r['note'], $r['note_status']]);
            }
            fclose($out);
        }, 'blog-tips.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function note(Request $request, int $tip): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['publish', 'refuse'])]]);
        app(ModerateTipNoteAction::class)->execute($tip, (int) $request->user()->id, $data['decision'] === 'publish');

        return back()->with('success', __($data['decision'] === 'publish' ? 'blog.flash_tip_note_published' : 'blog.flash_tip_note_refused'));
    }
}
