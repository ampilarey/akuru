<?php

namespace App\Domains\Website\Actions;

use App\Domains\HR\Actions\ReadPublicInstructorProfileAction;
use App\Domains\Media\Actions\ListPublicMediaFilesAction;
use App\Domains\Website\Enums\PostType;
use App\Domains\Website\Models\Post;

class PresentResearchPostAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(Post $post): ?array
    {
        $type = $post->type instanceof PostType ? $post->type->value : (string) $post->type;
        if ($type !== PostType::Research->value) {
            return null;
        }

        return $this->present($post);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Post $post): array
    {
        $authors = $this->presentAuthors(is_array($post->authors) ? $post->authors : []);
        $pdf = null;
        if ($post->pdf_document_id) {
            $files = app(ListPublicMediaFilesAction::class)->execute([(int) $post->pdf_document_id]);
            if ($files !== []) {
                $pdf = [
                    'id' => $files[0]['id'],
                    'url' => $files[0]['url'],
                    'name' => $files[0]['alt'],
                ];
            }
        }

        $published = $post->published_at;
        $isLive = (bool) $post->is_published && $published !== null && $published->lte(now());

        $instructorIds = [];
        $externalNames = [];
        foreach ($authors as $author) {
            if ($author['type'] === 'instructor' && $author['id'] !== null) {
                $instructorIds[] = $author['id'];
            }
            if ($author['type'] === 'external') {
                $externalNames[] = $author['name'];
            }
        }

        return [
            'id' => (int) $post->id,
            'title' => (string) $post->title,
            'slug' => (string) $post->slug,
            'abstract' => $post->abstract,
            'body' => $post->body,
            'citation_note' => $post->citation_note,
            'published_at' => $published?->timezone(config('app.timezone'))->toDateTimeString(),
            'published_at_local' => $published?->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
            'year' => $published?->year,
            'is_published' => $isLive,
            'is_published_flag' => (bool) $post->is_published,
            'authors' => $authors,
            'authors_label' => implode(', ', array_map(fn (array $author) => $author['name'], $authors)),
            'instructor_ids' => $instructorIds,
            'external_names_text' => implode("\n", $externalNames),
            'pdf' => $pdf,
            'pdf_document_id' => $post->pdf_document_id ? (int) $post->pdf_document_id : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $raw
     * @return list<array{type: string, id: ?int, slug: ?string, name: string, url: ?string}>
     */
    private function presentAuthors(array $raw): array
    {
        $reader = app(ReadPublicInstructorProfileAction::class);
        $out = [];
        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $instructorId = (int) ($entry['instructor_id'] ?? 0);
            if ($instructorId > 0) {
                $profile = $reader->execute($instructorId, null, false);
                $name = $profile['name'] ?? ('Instructor #'.$instructorId);
                $active = (bool) ($profile['is_active'] ?? false);
                $slug = $profile['slug'] ?? null;
                $out[] = [
                    'type' => 'instructor',
                    'id' => $instructorId,
                    'slug' => $slug,
                    'name' => $name,
                    'url' => ($active && $slug) ? route('public.instructors.show', $slug) : null,
                ];

                continue;
            }
            $name = trim((string) ($entry['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'type' => 'external',
                'id' => null,
                'slug' => null,
                'name' => $name,
                'url' => null,
            ];
        }

        return $out;
    }
}
