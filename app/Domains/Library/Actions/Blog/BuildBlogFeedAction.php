<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\WriterProfile;

/**
 * BLOG_PLAN BL4 (§3.2): RSS 2.0 for one blog, or for all of them — the
 * latest published posts only, newest first. Every value is escaped for XML;
 * the description is a plain-text excerpt, never the post's HTML.
 */
class BuildBlogFeedAction
{
    public const LIMIT = 30;

    /** Null when there is no such active blog. */
    public function forWriter(string $slug): ?string
    {
        $writer = WriterProfile::query()->where('slug', $slug)->where('status', 'active')->first();
        if ($writer === null) {
            return null;
        }

        return $this->rss(
            $writer->blog_title ?: (string) $writer->display_name,
            route('public.library.author', $writer->slug),
            (string) ($writer->tagline ?: __('public.blogs_by', ['name' => $writer->display_name])),
            route('public.library.author.feed', $writer->slug),
            $this->posts(fn ($query) => $query->where('writer_id', $writer->id)),
        );
    }

    public function forAll(): string
    {
        return $this->rss(
            __('public.blogs_title').' — '.__('public.Akuru Digital Library'),
            route('public.library.blogs'),
            __('public.blogs_intro'),
            route('public.library.blogs.feed'),
            $this->posts(fn ($query) => $query->whereHas('writer', fn ($writer) => $writer->where('status', 'active'))),
        );
    }

    /** @return \Illuminate\Support\Collection<int, LibraryItem> */
    private function posts(callable $scope)
    {
        return LibraryItem::query()
            ->with('writer')
            ->where('content_type', LibraryContentType::Post->value)
            ->where('status', LibraryItemStatus::Published->value)
            ->tap($scope)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();
    }

    /** @param  \Illuminate\Support\Collection<int, LibraryItem>  $posts */
    private function rss(string $title, string $link, string $description, string $self, $posts): string
    {
        $x = fn (?string $value) => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $excerpt = app(ListBlogsAction::class);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'
            .'<title>'.$x($title).'</title><link>'.$x($link).'</link><description>'.$x($description).'</description>'
            .'<language>'.$x(app()->getLocale()).'</language>'
            .'<atom:link href="'.$x($self).'" rel="self" type="application/rss+xml"/>';
        foreach ($posts as $post) {
            $url = route('public.library.show', $post->slug);
            $xml .= '<item><title>'.$x($post->title).'</title><link>'.$x($url).'</link><guid isPermaLink="true">'.$x($url).'</guid>'
                .($post->published_at ? '<pubDate>'.$x($post->published_at->toRfc2822String()).'</pubDate>' : '')
                .'<dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/">'.$x($post->writer?->display_name).'</dc:creator>'
                .'<description>'.$x($excerpt->excerpt((string) $post->body, 300)).'</description></item>';
        }

        return $xml.'</channel></rss>';
    }
}
