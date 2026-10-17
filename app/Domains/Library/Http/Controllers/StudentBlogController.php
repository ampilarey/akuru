<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\Blog\ListBlogPostsAction;
use App\Domains\Library\Actions\Blog\PresentStudentBlogAction;
use App\Domains\Library\Actions\Blog\SetBlogCommentsAction;
use App\Domains\Library\Actions\Blog\StartStudentBlogAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BLOG_PLAN BL1: *My blog* — a student asks to start a blog, follows the
 * request, and sees the blog once it is approved. The caller's own data
 * only; every rule is the actions'.
 */
class StudentBlogController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = (int) $request->user()->id;
        $blog = app(PresentStudentBlogAction::class)->execute($userId);

        return Inertia::render('Library/MyBlog', [
            ...$blog,
            // BL2a: a blogger's posts and series.
            ...(in_array($blog['state'], ['blog', 'writer'], true) ? app(ListBlogPostsAction::class)->mine($userId) : []),
            't' => Phrases::once('blog'),
        ]);
    }

    /** BL5: the writer turns comments on their blog on or off. */
    public function comments(Request $request): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $data = $request->validate(['enabled' => 'required|boolean']);
        app(SetBlogCommentsAction::class)->execute((int) $request->user()->id, (bool) $data['enabled']);

        return redirect()->route('my-blog')->with('success', __($data['enabled'] ? 'blog.flash_comments_on' : 'blog.flash_comments_off'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $data = $request->validate([
            'pen_name' => 'required|string|min:2|max:40',
            'blog_title' => 'required|string|min:3|max:120',
            'about' => 'required|string|min:10|max:300',
        ], [], ['pen_name' => __('blog.field_pen_name'), 'blog_title' => __('blog.field_blog_title'), 'about' => __('blog.field_about')]);

        $blogRequest = app(StartStudentBlogAction::class)->execute((int) $request->user()->id, $data);

        return redirect()->route('my-blog')->with('success', __($blogRequest->under_18 ? 'blog.flash_asked_parent' : 'blog.flash_asked_office'));
    }
}
