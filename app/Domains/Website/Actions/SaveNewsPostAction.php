<?php

namespace App\Domains\Website\Actions;

use App\Domains\Media\Actions\StorePublicMediaAction;
use App\Domains\Website\Enums\PostType;
use App\Domains\Website\Models\Post;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * RESEARCH_ARTICLES_PLAN R4 (the owner's decision D3): the office writes the
 * website's news. News stays a website post (`posts.type = news`), so the
 * public `/news` pages and the home page's latest-news row read it as they
 * always have.
 *
 * The ONE writer of `posts.body` since R2 retired the research CMS: the body
 * is sanitised with PROFILE_CMS here, because `public/news/show.blade.php`
 * renders it raw (tests/Architecture/Baselines/raw_html_renders.php).
 * The cover is public media, stored by path so `x-public.picture` can find
 * its WebP.
 */
class SaveNewsPostAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Post $post, int $authorId, ?UploadedFile $cover = null): Post
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'A news item needs a title.']);
        }
        $body = app(HtmlSanitizer::class)->clean((string) ($data['body'] ?? ''), HtmlSanitizer::PROFILE_CMS);
        if (trim(strip_tags($body)) === '') {
            throw ValidationException::withMessages(['body' => 'Write the news before saving it.']);
        }

        $slug = Str::slug(trim((string) ($data['slug'] ?? '')) ?: $title);
        if ($slug === '') {
            $slug = 'news-'.Str::lower(Str::random(6));
        }
        $taken = Post::query()->where('slug', $slug)->when($post, fn ($q) => $q->whereKeyNot($post->id))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['slug' => 'Another post already uses that address. Choose a different slug.']);
        }

        $summary = trim((string) ($data['summary'] ?? ''));
        $publish = filter_var($data['is_published'] ?? false, FILTER_VALIDATE_BOOL);
        $publishedAt = null;
        if ($publish) {
            $publishedAt = ! empty($data['published_at'])
                ? Carbon::parse((string) $data['published_at'], 'Indian/Maldives')
                : ($post?->published_at ?? now());
        }
        $tags = is_array($data['tags'] ?? null)
            ? $data['tags']
            : explode(',', (string) ($data['tags'] ?? ''));
        $tags = array_values(array_unique(array_filter(array_map(fn ($tag) => trim((string) $tag), $tags))));

        $payload = [
            'type' => PostType::News->value,
            'title' => $title,
            'slug' => $slug,
            // The list and the home page show the summary; a blank one reads
            // the start of the news instead.
            'summary' => $summary !== '' ? $summary : Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($body))), 200),
            'body' => $body,
            'post_category_id' => ! empty($data['post_category_id']) ? (int) $data['post_category_id'] : null,
            'tags' => $tags === [] ? null : $tags,
            'is_featured' => filter_var($data['is_featured'] ?? false, FILTER_VALIDATE_BOOL),
            'is_pinned' => filter_var($data['is_pinned'] ?? false, FILTER_VALIDATE_BOOL),
            'is_published' => $publish,
            'published_at' => $publishedAt,
            'meta_description' => ($meta = trim((string) ($data['meta_description'] ?? ''))) !== '' ? $meta : null,
        ];
        if ($cover !== null) {
            $payload['cover_image'] = app(StorePublicMediaAction::class)->execute(
                $cover,
                $authorId,
                ['image/jpeg', 'image/png', 'image/webp'],
                ['alt' => $title],
                'news-covers',
            )['path'];
        }

        if ($post === null) {
            $post = Post::query()->create($payload + ['author_id' => $authorId]);
        } else {
            $post->fill($payload)->save();
        }

        return $post->refresh();
    }
}
