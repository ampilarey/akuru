<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Enums\PostType;
use App\Domains\Website\Models\Post;
use Illuminate\Support\Carbon;

/**
 * RESEARCH_ARTICLES_PLAN R2: the website's research posts, as plain data,
 * for the Digital Library's one-time import (`library:import-website-research`)
 * and for the redirect from an old `/research/{slug}` address. The Library
 * never reads the `posts` table itself.
 *
 * The rows stay in `posts` (rule 9); after R2 nothing writes new ones — the
 * research CMS is retired and research is written in the library.
 */
class ListResearchPostsForImportAction
{
    /**
     * @return list<array{id: int, title: string, slug: string, abstract: ?string, body: ?string, citation_note: ?string, published_at: ?Carbon, is_live: bool, authors: list<array{instructor_id: ?int, name: ?string}>, pdf_media_id: ?int}>
     */
    public function execute(): array
    {
        return Post::query()
            ->where('type', PostType::Research->value)
            ->orderBy('id')
            ->get()
            ->map(fn (Post $post) => $this->row($post))
            ->values()
            ->all();
    }

    /** The post an old `/research/{slug}` address named, or null. */
    public function idForSlug(string $slug): ?int
    {
        $id = Post::query()->where('type', PostType::Research->value)->where('slug', $slug)->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Post $post): array
    {
        $authors = [];
        foreach (is_array($post->authors) ? $post->authors : [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $instructorId = (int) ($entry['instructor_id'] ?? 0);
            $name = trim((string) ($entry['name'] ?? ''));
            if ($instructorId > 0) {
                $authors[] = ['instructor_id' => $instructorId, 'name' => null];
            } elseif ($name !== '') {
                $authors[] = ['instructor_id' => null, 'name' => $name];
            }
        }

        return [
            'id' => (int) $post->id,
            'title' => (string) $post->title,
            'slug' => (string) $post->slug,
            'abstract' => $post->abstract,
            'body' => $post->body,
            'citation_note' => $post->citation_note,
            'published_at' => $post->published_at,
            'is_live' => (bool) $post->is_published && $post->published_at !== null && $post->published_at->lte(now()),
            'authors' => $authors,
            'pdf_media_id' => $post->pdf_document_id ? (int) $post->pdf_document_id : null,
        ];
    }
}
