<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\{Course, Post, Event};
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim($request->get('q', ''));

        $courses = collect();
        $posts   = collect();
        $events  = collect();

        if (strlen($q) >= 2) {
            $courses = Course::whereIn('status', ['open', 'upcoming'])
                ->where(function ($query) use ($q) {
                    $query->where('title', 'like', "%{$q}%")
                          ->orWhere('short_desc', 'like', "%{$q}%")
                          ->orWhere('body', 'like', "%{$q}%");
                })
                ->with('category')
                ->orderBy('sort_order')
                ->take(10)
                ->get();

            $posts = Post::published()
                ->where(function ($query) use ($q) {
                    $query->where('title', 'like', "%{$q}%")
                          ->orWhere('summary', 'like', "%{$q}%")
                          ->orWhere('body', 'like', "%{$q}%");
                })
                ->with('category')
                ->latest('published_at')
                ->take(10)
                ->get();

            $events = Event::published()->public()
                ->where(function ($query) use ($q) {
                    $query->where('title', 'like', "%{$q}%")
                          ->orWhere('short_description', 'like', "%{$q}%")
                          ->orWhere('description', 'like', "%{$q}%");
                })
                ->orderBy('start_date')
                ->take(10)
                ->get();
        }

        $total = $courses->count() + $posts->count() + $events->count();

        return view('public.search', compact('q', 'courses', 'posts', 'events', 'total'));
    }
}
