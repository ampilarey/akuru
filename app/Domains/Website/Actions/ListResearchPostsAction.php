<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Models\Post;
use Illuminate\Support\Collection;

class ListResearchPostsAction
{
    /**
     * @param  array{year?: int|string, instructor_id?: int|string, q?: string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = [], bool $publishedOnly = true): Collection
    {
        $query = Post::query()->research()->orderByDesc('published_at')->orderByDesc('id');
        if ($publishedOnly) {
            $query->published();
        }
        if (! empty($filters['year'])) {
            $query->whereYear('published_at', (int) $filters['year']);
        }
        if (! empty($filters['instructor_id'])) {
            $query->whereJsonContains('authors', ['instructor_id' => (int) $filters['instructor_id']]);
        }
        if (! empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            if ($term !== '') {
                $query->where(function ($inner) use ($term) {
                    $inner->where('title', 'like', '%'.$term.'%')
                        ->orWhere('abstract', 'like', '%'.$term.'%')
                        ->orWhere('body', 'like', '%'.$term.'%');
                });
            }
        }

        $presenter = app(PresentResearchPostAction::class);

        return $query->get()->map(fn (Post $post) => $presenter->present($post))->values();
    }

    /**
     * @return list<int>
     */
    public function years(bool $publishedOnly = true): array
    {
        $query = Post::query()->research()->whereNotNull('published_at');
        if ($publishedOnly) {
            $query->published();
        }

        return $query->get()
            ->map(fn (Post $post) => (int) $post->published_at->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }
}
