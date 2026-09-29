<?php

namespace App\Domains\Website\Http\Controllers\PublicSite;

use App\Domains\Courses\Models\Course;
use App\Domains\Library\Actions\ListLibraryItemsAction;
use App\Domains\Website\Enums\PostType;
use App\Domains\Website\Models\Event;
use App\Domains\Website\Models\Post;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $searching = mb_strlen($q) >= 2;

        $courses = $searching ? $this->courses($q) : collect();
        $posts = $searching ? $this->news($q) : collect();
        $events = $searching ? $this->events($q) : collect();
        // R5 (RESEARCH_ARTICLES_PLAN): books, articles and research — the
        // Digital Library's published shelf, through its own action.
        $library = $searching
            ? collect(app(ListLibraryItemsAction::class)->execute(['q' => $q]))->take(10)
            : collect();

        $total = $courses->count() + $library->count() + $posts->count() + $events->count();

        return view('public.search', compact('q', 'courses', 'library', 'posts', 'events', 'total'));
    }

    private function courses(string $q): Collection
    {
        return Course::whereIn('status', ['open', 'upcoming'])
            ->where(fn ($query) => $query->where('title', 'like', "%{$q}%")
                ->orWhere('short_desc', 'like', "%{$q}%")
                ->orWhere('body', 'like', "%{$q}%"))
            ->with('category')
            ->orderBy('sort_order')
            ->take(10)
            ->get();
    }

    /** R2: articles and research live in the Digital Library; posts here are news. */
    private function news(string $q): Collection
    {
        return Post::published()
            ->where('type', PostType::News->value)
            ->where(fn ($query) => $query->where('title', 'like', "%{$q}%")
                ->orWhere('summary', 'like', "%{$q}%")
                ->orWhere('body', 'like', "%{$q}%"))
            ->with('category')
            ->latest('published_at')
            ->take(10)
            ->get();
    }

    private function events(string $q): Collection
    {
        return Event::published()->public()
            ->where(fn ($query) => $query->where('title', 'like', "%{$q}%")
                ->orWhere('short_description', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%"))
            ->orderBy('start_date')
            ->take(10)
            ->get();
    }
}
