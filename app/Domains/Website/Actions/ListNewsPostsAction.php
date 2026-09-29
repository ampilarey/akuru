<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Enums\PostType;
use App\Domains\Website\Models\Post;

/**
 * RESEARCH_ARTICLES_PLAN R4: the office's list of news — drafts, scheduled
 * and published — newest first, for the screen and its CSV.
 */
class ListNewsPostsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(bool $full = false): array
    {
        return Post::query()
            ->with('category')
            ->where('type', PostType::News->value)
            ->orderByRaw('published_at IS NULL DESC')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Post $post) => $this->present($post, $full))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Post $post, bool $full = true): array
    {
        $live = (bool) $post->is_published && $post->published_at !== null && $post->published_at->lte(now());

        return [
            'id' => (int) $post->id,
            'title' => (string) $post->title,
            'slug' => (string) $post->slug,
            'summary' => $post->summary,
            'body' => $full ? $post->body : null,
            'cover_url' => $post->cover_image ? asset('storage/'.$post->cover_image) : null,
            'post_category_id' => $post->post_category_id,
            'category' => $post->category?->name,
            'tags' => is_array($post->tags) ? $post->tags : [],
            'is_featured' => (bool) $post->is_featured,
            'is_pinned' => (bool) $post->is_pinned,
            'is_published' => (bool) $post->is_published,
            // draft, scheduled (published with a date to come) or live.
            'state' => ! $post->is_published ? 'draft' : ($live ? 'live' : 'scheduled'),
            'published_at' => $post->published_at?->timezone('Indian/Maldives')->format('Y-m-d H:i'),
            'published_at_local' => $post->published_at?->timezone('Indian/Maldives')->format('Y-m-d\TH:i'),
            'meta_description' => $full ? $post->meta_description : null,
            'public_url' => route('public.news.show', $post->slug),
        ];
    }
}
