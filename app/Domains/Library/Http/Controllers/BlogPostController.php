<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\Blog\SaveBlogPostAction;
use App\Domains\Library\Actions\Blog\SubmitBlogPostAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * BLOG_PLAN BL2a: a blogger writes posts on *My blog* and sends them to be
 * checked. Their own posts only; every rule is the actions'.
 */
class BlogPostController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        app(SaveBlogPostAction::class)->execute((int) $request->user()->id, $this->validated($request), null, $request->file('cover'));

        return redirect()->route('my-blog')->with('success', __('blog.flash_post_saved'));
    }

    public function update(Request $request, int $post): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        app(SaveBlogPostAction::class)->execute((int) $request->user()->id, $this->validated($request), $post, $request->file('cover'));

        return redirect()->route('my-blog')->with('success', __('blog.flash_post_saved'));
    }

    public function submit(Request $request, int $post): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $item = app(SubmitBlogPostAction::class)->execute((int) $request->user()->id, $post);

        return redirect()->route('my-blog')->with('success', __($item->review_stage === 'teacher' ? 'blog.flash_post_to_teacher' : 'blog.flash_post_to_office'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|min:3|max:160',
            'body' => 'nullable|string|max:200000',
            'language' => 'nullable|in:en,dv,ar',
            'series_id' => 'nullable|integer',
            'new_series' => 'nullable|string|max:120',
            'pinned' => 'boolean',
            'own_work' => 'boolean',
            'cover' => 'nullable|image|max:4096',
        ], [], ['title' => __('blog.field_post_title'), 'body' => __('blog.field_post_body')]);
    }
}
