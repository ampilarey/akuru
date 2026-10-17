<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\Blog\PresentBlogDesignerAction;
use App\Domains\Library\Actions\Blog\SaveBlogPageDraftAction;
use App\Domains\Library\Actions\Blog\SubmitBlogPageAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BLOG_PLAN BL3a (§5): a blogger designs their page — a draft, sent to the
 * office, live once approved. Their own page only; every rule is the
 * actions'.
 */
class BlogDesignController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $designer = app(PresentBlogDesignerAction::class)->execute((int) $request->user()->id);
        if ($designer === null) {
            return redirect()->route('my-blog');
        }

        return Inertia::render('Library/BlogDesign', [...$designer, 't' => Phrases::once('blog')]);
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $data = $request->validate([
            'theme' => 'required|string|max:40',
            'accent' => 'nullable|string|max:7',
            'font' => 'nullable|string|max:40',
            'tagline' => 'nullable|string|max:120',
            'about' => 'nullable|string|max:1500',
            'languages' => 'array',
            'languages.*' => 'string|in:en,dv,ar',
            'sections' => 'array',
            'sections.*.key' => 'required|string|max:20',
            'sections.*.on' => 'boolean',
        ]);
        app(SaveBlogPageDraftAction::class)->execute((int) $request->user()->id, $data);

        return redirect()->route('my-blog.design')->with('success', __('blog.flash_page_saved'));
    }

    public function submit(Request $request): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        app(SubmitBlogPageAction::class)->execute((int) $request->user()->id);

        return redirect()->route('my-blog.design')->with('success', __('blog.flash_page_sent'));
    }
}
