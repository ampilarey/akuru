<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\Blog\ListBlogPostsAction;
use App\Domains\Library\Actions\Blog\TeacherReviewBlogPostAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BLOG_PLAN BL2a (§6): a class teacher's queue of their students' posts.
 * A teacher sees only the posts sent to them; the action refuses any other.
 */
class TeacherBlogPostController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Library/TeacherBlogPosts', [
            'posts' => app(ListBlogPostsAction::class)->forTeacher((int) $request->user()->id),
            't' => Phrases::once('blog'),
        ]);
    }

    public function decide(Request $request, int $post): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $data = $request->validate([
            'decision' => 'required|in:'.implode(',', TeacherReviewBlogPostAction::DECISIONS),
            'comment' => 'nullable|string|max:2000',
        ]);
        app(TeacherReviewBlogPostAction::class)->execute($post, (int) $request->user()->id, $data['decision'], $data['comment'] ?? null);

        return back()->with('success', __('blog.flash_teacher_'.$data['decision']));
    }
}
