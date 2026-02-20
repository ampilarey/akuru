<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\{Post, PostCategory};
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $query = Post::published()->with(['author', 'category']);
        
        // Filter by category
        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }
        
        // Filter by featured
        if ($request->filled('featured')) {
            $query->featured();
        }
        
        // Filter by pinned
        if ($request->filled('pinned')) {
            $query->pinned();
        }
        
        // Filter by tag
        if ($request->filled('tag')) {
            $query->byTag($request->tag);
        }
        
        // Search functionality
        if ($request->filled('search')) {
            $query->search($request->search);
        }
        
        // Sort options
        $sortBy = $request->get('sort', 'recent');
        switch ($sortBy) {
            case 'popular':
                $query->popular();
                break;
            case 'title':
                $query->orderBy('title');
                break;
            case 'featured':
                $query->orderBy('is_featured', 'desc')->orderBy('published_at', 'desc');
                break;
            case 'pinned':
                $query->orderBy('is_pinned', 'desc')->orderBy('published_at', 'desc');
                break;
            default:
                $query->recent();
        }
        
        $posts = $query->paginate(12)->withQueryString();
        
        // Get categories for filter
        $categories = PostCategory::active()
                                 ->ordered()
                                 ->withCount('publishedPosts')
                                 ->get();
        
        // Get featured posts for sidebar
        $featuredPosts = Post::published()
                             ->featured()
                             ->with('author', 'category')
                             ->take(5)
                             ->get();
        
        // Get recent posts for sidebar
        $recentPosts = Post::published()
                           ->with('author', 'category')
                           ->recent()
                           ->take(5)
                           ->get();
        
        // Get popular tags
        $popularTags = Post::published()
                           ->whereNotNull('tags')
                           ->get()
                           ->pluck('tags')
                           ->flatten()
                           ->countBy()
                           ->sortDesc()
                           ->take(10)
                           ->keys();
        
        return view('public.news.index', compact('posts', 'categories', 'featuredPosts', 'recentPosts', 'popularTags'));
    }

    public function newsIndex(Request $request)
    {
        return $this->indexByType($request, 'news', 'public.news.index');
    }

    public function articlesIndex(Request $request)
    {
        return $this->indexByType($request, 'article', 'public.articles.index');
    }

    private function indexByType(Request $request, string $type, string $view)
    {
        $query = Post::published()->where('type', $type)->with(['author', 'category']);

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }
        if ($request->filled('tag')) {
            $query->byTag($request->tag);
        }
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->get('sort', 'recent');
        match ($sortBy) {
            'popular'  => $query->popular(),
            'title'    => $query->orderBy('title'),
            'featured' => $query->orderBy('is_featured', 'desc')->orderBy('published_at', 'desc'),
            default    => $query->recent(),
        };

        $posts      = $query->paginate(12)->withQueryString();
        $categories = PostCategory::active()->ordered()->withCount('publishedPosts')->get();
        $featuredPosts = Post::published()->where('type', $type)->featured()->with('author', 'category')->take(5)->get();
        $recentPosts   = Post::published()->where('type', $type)->with('author', 'category')->recent()->take(5)->get();
        $popularTags   = Post::published()->where('type', $type)->whereNotNull('tags')
            ->get()->pluck('tags')->flatten()->countBy()->sortDesc()->take(10)->keys();

        return view($view, compact('posts', 'categories', 'featuredPosts', 'recentPosts', 'popularTags'));
    }

    public function show(Post $post)
    {
        // Ensure the post is published
        if (!$post->is_published || $post->published_at > now()) {
            abort(404);
        }
        
        // Load relationships
        $post->load(['author', 'category']);
        
        // Increment view count
        $post->incrementViewCount();
        
        // Get related posts
        $relatedPosts = $post->getRelatedPosts(3);
        
        // Get featured posts for sidebar
        $featuredPosts = Post::published()
                             ->featured()
                             ->where('id', '!=', $post->id)
                             ->with('author', 'category')
                             ->take(3)
                             ->get();
        
        // Get recent posts for sidebar
        $recentPosts = Post::published()
                           ->where('id', '!=', $post->id)
                           ->with('author', 'category')
                           ->recent()
                           ->take(5)
                           ->get();
        
        // Get categories for sidebar
        $categories = PostCategory::active()
                                 ->ordered()
                                 ->withCount('publishedPosts')
                                 ->get();
        
        $viewName = $post->type === 'article' ? 'public.articles.show' : 'public.news.show';
        return view($viewName, compact('post', 'relatedPosts', 'featuredPosts', 'recentPosts', 'categories'));
    }

    public function category(PostCategory $category)
    {
        $posts = Post::published()
                     ->byCategory($category->id)
                     ->with(['author', 'category'])
                     ->recent()
                     ->paginate(12);
        
        // Get featured posts for sidebar
        $featuredPosts = Post::published()
                             ->featured()
                             ->with('author', 'category')
                             ->take(5)
                             ->get();
        
        // Get recent posts for sidebar
        $recentPosts = Post::published()
                           ->with('author', 'category')
                           ->recent()
                           ->take(5)
                           ->get();
        
        // Get categories for sidebar
        $categories = PostCategory::active()
                                 ->ordered()
                                 ->withCount('publishedPosts')
                                 ->get();
        
        return view('public.news.category', compact('category', 'posts', 'featuredPosts', 'recentPosts', 'categories'));
    }

    public function tag($tag)
    {
        $posts = Post::published()
                     ->byTag($tag)
                     ->with(['author', 'category'])
                     ->recent()
                     ->paginate(12);
        
        // Get featured posts for sidebar
        $featuredPosts = Post::published()
                             ->featured()
                             ->with('author', 'category')
                             ->take(5)
                             ->get();
        
        // Get recent posts for sidebar
        $recentPosts = Post::published()
                           ->with('author', 'category')
                           ->recent()
                           ->take(5)
                           ->get();
        
        // Get categories for sidebar
        $categories = PostCategory::active()
                                 ->ordered()
                                 ->withCount('publishedPosts')
                                 ->get();
        
        return view('public.news.tag', compact('tag', 'posts', 'featuredPosts', 'recentPosts', 'categories'));
    }

    public function like(Post $post)
    {
        $post->incrementLikeCount();
        
        return response()->json([
            'success' => true,
            'like_count' => $post->like_count
        ]);
    }

    public function share(Post $post)
    {
        $post->incrementShareCount();
        
        return response()->json([
            'success' => true,
            'share_count' => $post->share_count
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->get('q');
        
        if (!$query) {
            return redirect()->route('public.news.index');
        }
        
        $posts = Post::published()
                     ->search($query)
                     ->with(['author', 'category'])
                     ->recent()
                     ->paginate(12)
                     ->withQueryString();
        
        // Get featured posts for sidebar
        $featuredPosts = Post::published()
                             ->featured()
                             ->with('author', 'category')
                             ->take(5)
                             ->get();
        
        // Get recent posts for sidebar
        $recentPosts = Post::published()
                           ->with('author', 'category')
                           ->recent()
                           ->take(5)
                           ->get();
        
        // Get categories for sidebar
        $categories = PostCategory::active()
                                 ->ordered()
                                 ->withCount('publishedPosts')
                                 ->get();
        
        return view('public.news.search', compact('query', 'posts', 'featuredPosts', 'recentPosts', 'categories'));
    }
}