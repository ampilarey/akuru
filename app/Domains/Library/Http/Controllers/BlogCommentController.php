<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\Blog\ModerateCommentAction;
use App\Domains\Library\Actions\Blog\PostCommentAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * BLOG_PLAN BL5: a signed-in reader comments on a post, the owner replies,
 * anyone signed in reports a published comment. A guest is sent to sign in.
 */
class BlogCommentController extends Controller
{
    public function store(Request $request, int $item): RedirectResponse
    {
        if ($request->user() === null) {
            return redirect()->guest(route('login'));
        }
        abort_unless($request->user() !== null, 403);
        $data = $request->validate(['body' => 'required|string|max:2000', 'parent_id' => 'nullable|integer']);
        app(PostCommentAction::class)->execute((int) $request->user()->id, $item, $data['body'], isset($data['parent_id']) ? (int) $data['parent_id'] : null);

        return back()->with('success', __('blog.flash_comment_sent'));
    }

    public function report(Request $request, int $comment): RedirectResponse
    {
        if ($request->user() === null) {
            return redirect()->guest(route('login'));
        }
        abort_unless($request->user() !== null, 403);
        app(ModerateCommentAction::class)->report((int) $request->user()->id, $comment);

        return back()->with('success', __('blog.flash_comment_reported'));
    }
}
