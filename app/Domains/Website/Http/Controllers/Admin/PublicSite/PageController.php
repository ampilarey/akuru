<?php

namespace App\Domains\Website\Http\Controllers\Admin\PublicSite;

use App\Domains\Website\Models\Page;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Html\HtmlSanitizer;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manage Pages (the website CMS). Inertia since C9 slice 10 (STATUS §5jl),
 * with its strings keyed for Dhivehi and Arabic. `role:super_admin` on the
 * route group. The body is authored HTML, sanitised here on every write so
 * every reader of `pages.body` — the public page, the office preview — is
 * safe without remembering to.
 */
class PageController extends Controller
{
    private const RULES = [
        'title' => 'required|string|max:255',
        'excerpt' => 'nullable|string',
        'body' => 'required|string',
        'cover_image' => 'nullable|string|max:255',
        'is_published' => 'boolean',
        'published_at' => 'nullable|date',
    ];

    public function index(): Response
    {
        $pages = Page::orderBy('title')->paginate(15)->withQueryString();

        return Inertia::render('Website/Pages', [
            'pages' => collect($pages->items())->map(fn (Page $page) => $this->present($page, false))->values()->all(),
            'pagination' => ['current_page' => $pages->currentPage(), 'last_page' => $pages->lastPage(), 'prev' => $pages->previousPageUrl(), 'next' => $pages->nextPageUrl()],
            'total' => $pages->total(),
            't' => Phrases::once('admin'),
        ]);
    }

    /** "Every listing gets CSV export" (admin-panel audit, STATUS §5hs). */
    public function export(): StreamedResponse
    {
        $rows = Page::orderBy('title')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'title', 'slug', 'published', 'published_at', 'updated_at']);
            foreach ($rows as $row) {
                Csv::put($out, [$row->id, $row->title, $row->slug, $row->is_published ? 'yes' : 'no', $row->published_at?->toDateTimeString(), $row->updated_at?->toDateTimeString()]);
            }
            fclose($out);
        }, 'pages.csv', ['Content-Type' => 'text/csv']);
    }

    public function create(): Response
    {
        return Inertia::render('Website/PageForm', ['page' => null, 't' => Phrases::once('admin')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request, ['slug' => 'required|string|max:255|unique:pages']);
        Page::create($validated);

        return redirect()->route('admin.pages.index')->with('success', trans('admin.pages_flash_created'));
    }

    public function show(Page $page): Response
    {
        return Inertia::render('Website/PagePreview', ['page' => $this->present($page, true), 't' => Phrases::once('admin')]);
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('Website/PageForm', ['page' => $this->present($page, true), 't' => Phrases::once('admin')]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $validated = $this->validated($request, ['slug' => 'required|string|max:255|unique:pages,slug,'.$page->id]);
        $page->update($validated);

        return redirect()->route('admin.pages.index')->with('success', trans('admin.pages_flash_updated'));
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return redirect()->route('admin.pages.index')->with('success', trans('admin.pages_flash_deleted'));
    }

    /**
     * Validate, sanitise the body (PROFILE_CMS: the public site renders it
     * raw) and settle the publish flag and date the way the form sends them.
     *
     * @param  array<string, string>  $slugRule
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $slugRule): array
    {
        $validated = $request->validate(self::RULES + $slugRule);
        $validated['body'] = app(HtmlSanitizer::class)->clean($validated['body'], HtmlSanitizer::PROFILE_CMS);
        $validated['is_published'] = $request->has('is_published');
        $validated['published_at'] = $validated['is_published'] ? ($validated['published_at'] ?? now()) : null;

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Page $page, bool $full): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'excerpt' => $full ? $page->excerpt : ($page->excerpt ? Str::limit($page->excerpt, 60) : null),
            'body' => $full ? $page->body : null,
            'cover_image' => $full ? $page->cover_image : null,
            'is_published' => (bool) $page->is_published,
            'updated_at' => $page->updated_at?->format('M d, Y'),
            'public_url' => route('public.page.show', $page->slug),
        ];
    }
}
