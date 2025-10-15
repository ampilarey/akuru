<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::published()
            ->with('author')
            ->latest('published_at')
            ->paginate(9);

        return view('public.news.index', compact('posts'));
    }

    public function show(Post $post)
    {
        // Ensure the post is published
        if (!$post->is_published || $post->published_at > now()) {
            abort(404);
        }

        return view('public.news.show', compact('post'));
    }
}