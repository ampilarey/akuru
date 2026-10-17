<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\Blog\PresentBlogDesignerAction;
use App\Domains\Library\Actions\Blog\ReadBlogPageImageAction;
use App\Domains\Library\Actions\Blog\SaveBlogPageDraftAction;
use App\Domains\Library\Actions\Blog\SubmitBlogPageAction;
use App\Domains\Library\Models\WriterProfile;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
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

    /** BL3b: the waiting banner or picture, for the writer's own preview. */
    public function image(Request $request, string $kind): HttpResponse
    {
        $profile = WriterProfile::query()->where('user_id', (int) $request->user()->id)->first();
        $image = $profile ? app(ReadBlogPageImageAction::class)->execute($profile, $kind) : null;
        abort_if($image === null, 404);

        return response($image['contents'], 200, ['Content-Type' => $image['mime'], 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
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
            // BL3b: the banner and the picture, private until approved; the links.
            'banner' => 'nullable|image|mimes:jpeg,png,webp|max:4096',
            'photo' => 'nullable|image|mimes:jpeg,png,webp|max:4096',
            'banner_remove' => 'boolean',
            'photo_remove' => 'boolean',
            'links' => 'array',
            'links.*' => 'nullable|string|max:255',
        ]);
        app(SaveBlogPageDraftAction::class)->execute((int) $request->user()->id, $data, $request->file('banner'), $request->file('photo'));

        return redirect()->route('my-blog.design')->with('success', __('blog.flash_page_saved'));
    }

    public function submit(Request $request): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        app(SubmitBlogPageAction::class)->execute((int) $request->user()->id);

        return redirect()->route('my-blog.design')->with('success', __('blog.flash_page_sent'));
    }
}
