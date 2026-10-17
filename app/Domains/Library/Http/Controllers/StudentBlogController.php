<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\Blog\PresentStudentBlogAction;
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
        return Inertia::render('Library/MyBlog', [
            ...app(PresentStudentBlogAction::class)->execute((int) $request->user()->id),
            't' => Phrases::once('blog'),
        ]);
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
