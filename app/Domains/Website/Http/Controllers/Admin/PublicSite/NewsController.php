<?php

namespace App\Domains\Website\Http\Controllers\Admin\PublicSite;

use App\Domains\Website\Actions\ListNewsPostsAction;
use App\Domains\Website\Actions\SaveNewsCategoryAction;
use App\Domains\Website\Actions\SaveNewsPostAction;
use App\Domains\Website\Enums\PostType;
use App\Domains\Website\Models\Post;
use App\Domains\Website\Models\PostCategory;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * RESEARCH_ARTICLES_PLAN R4: the news editor (Website CMS → News). Gated
 * with the rest of the website CMS (`role:super_admin` on the route group).
 * News stays a website post; `SaveNewsPostAction` is its one writer.
 */
class NewsController extends Controller
{
    private const RULES = [
        'title' => 'required|string|max:255',
        'slug' => 'nullable|string|max:255',
        'summary' => 'nullable|string|max:500',
        'body' => 'required|string',
        'post_category_id' => 'nullable|integer|exists:post_categories,id',
        'tags' => 'nullable|string|max:500',
        'is_featured' => 'nullable|boolean',
        'is_pinned' => 'nullable|boolean',
        'is_published' => 'nullable|boolean',
        'published_at' => 'nullable|date',
        'meta_description' => 'nullable|string|max:300',
        'cover' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
    ];

    public function index(): Response
    {
        return Inertia::render('Website/News', [
            'posts' => app(ListNewsPostsAction::class)->execute(),
            't' => trans('admin'),
        ]);
    }

    public function export(): StreamedResponse
    {
        $rows = app(ListNewsPostsAction::class)->execute();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'title', 'slug', 'state', 'published_at', 'category', 'featured', 'pinned']);
            foreach ($rows as $row) {
                Csv::put($out, [$row['id'], $row['title'], $row['slug'], $row['state'], $row['published_at'], $row['category'], $row['is_featured'] ? 'yes' : 'no', $row['is_pinned'] ? 'yes' : 'no']);
            }
            fclose($out);
        }, 'news.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(): Response
    {
        return Inertia::render('Website/NewsForm', ['post' => null, 'categories' => $this->categories(), 't' => trans('admin')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $post = app(SaveNewsPostAction::class)->execute($request->validate(self::RULES), null, (int) $request->user()->id, $request->file('cover'));

        return redirect()->route('admin.news.show', $post->id)->with('success', trans('admin.news_flash_saved'));
    }

    public function show(int $post): Response
    {
        return Inertia::render('Website/NewsPreview', ['post' => app(ListNewsPostsAction::class)->present($this->news($post)), 't' => trans('admin')]);
    }

    public function edit(int $post): Response
    {
        return Inertia::render('Website/NewsForm', [
            'post' => app(ListNewsPostsAction::class)->present($this->news($post)),
            'categories' => $this->categories(),
            't' => trans('admin'),
        ]);
    }

    public function update(Request $request, int $post): RedirectResponse
    {
        $saved = app(SaveNewsPostAction::class)->execute($request->validate(self::RULES), $this->news($post), (int) $request->user()->id, $request->file('cover'));

        return redirect()->route('admin.news.show', $saved->id)->with('success', trans('admin.news_flash_saved'));
    }

    public function categoriesIndex(): Response
    {
        return Inertia::render('Website/NewsCategories', [
            'categories' => PostCategory::query()->ordered()->withCount('posts')->get()
                ->map(fn (PostCategory $c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'sort_order' => (int) $c->sort_order, 'is_active' => (bool) $c->is_active, 'posts' => (int) $c->posts_count])
                ->values()->all(),
            't' => trans('admin'),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        app(SaveNewsCategoryAction::class)->execute($request->validate($this->categoryRules()));

        return back()->with('success', trans('admin.news_flash_category_saved'));
    }

    public function updateCategory(Request $request, PostCategory $category): RedirectResponse
    {
        app(SaveNewsCategoryAction::class)->execute($request->validate($this->categoryRules()), $category);

        return back()->with('success', trans('admin.news_flash_category_saved'));
    }

    /** @return array<string, string> */
    private function categoryRules(): array
    {
        return ['name' => 'required|string|max:120', 'slug' => 'nullable|string|max:120', 'sort_order' => 'nullable|integer|min:0|max:1000', 'is_active' => 'nullable|boolean'];
    }

    private function news(int $id): Post
    {
        return Post::query()->where('type', PostType::News->value)->findOrFail($id);
    }

    /** @return list<array{id: int, name: string}> */
    private function categories(): array
    {
        return PostCategory::query()->ordered()->get(['id', 'name'])->map(fn ($c) => ['id' => (int) $c->id, 'name' => $c->name])->values()->all();
    }
}
