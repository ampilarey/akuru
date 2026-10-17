<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\Blog\ListCommentsAction;
use App\Domains\Library\Actions\Blog\ModerateCommentAction;
use App\Domains\Library\Models\LibraryComment;
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
 * BLOG_PLAN BL5 (§6): the Library office's comments list — waiting and
 * reported first — with publish, refuse (with a note) and hide, and its CSV.
 * Behind the Library office's gate (`role:super_admin`, `can:library.manage`).
 */
class AdminBlogCommentController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');

        return Inertia::render('Library/BlogComments', [
            'comments' => app(ListCommentsAction::class)->office(is_string($status) ? $status : null),
            'statuses' => LibraryComment::STATUSES,
            'status' => is_string($status) ? $status : '',
            't' => Phrases::once('blog'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $status = $request->query('status');
        $rows = app(ListCommentsAction::class)->office(is_string($status) ? $status : null, 5000);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'post', 'blog', 'commenter', 'reply', 'body', 'status', 'reports', 'note', 'created_at']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['id'], $r['post'], $r['blog'], $r['commenter'], $r['reply'] ? 'yes' : 'no', $r['body'], $r['status'], $r['report_count'], $r['decision_note'], $r['created_at']]);
            }
            fclose($out);
        }, 'blog-comments.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function decide(Request $request, int $comment): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(ModerateCommentAction::DECISIONS)], 'note' => 'nullable|string|max:500']);
        app(ModerateCommentAction::class)->execute($comment, (int) $request->user()->id, $data['decision'], $data['note'] ?? null);

        return back()->with('success', __('blog.flash_comment_'.$data['decision']));
    }
}
