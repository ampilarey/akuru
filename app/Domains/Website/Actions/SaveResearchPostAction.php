<?php

namespace App\Domains\Website\Actions;

use App\Domains\Media\Actions\StorePublicMediaAction;
use App\Domains\Website\Enums\PostType;
use App\Domains\Website\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveResearchPostAction
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(array $input, ?Post $existing, int $authorId, ?UploadedFile $pdf = null): Post
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Title is required.']);
        }

        $slugSource = trim((string) ($input['slug'] ?? ''));
        $slug = Str::slug($slugSource !== '' ? $slugSource : $title);
        if ($slug === '') {
            throw ValidationException::withMessages(['slug' => 'Slug is required.']);
        }

        $duplicate = Post::query()
            ->where('slug', $slug)
            ->when($existing !== null, fn ($query) => $query->whereKeyNot($existing->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['slug' => 'That slug is already in use.']);
        }

        $abstract = trim((string) ($input['abstract'] ?? ''));
        $abstract = $abstract !== '' ? $abstract : null;
        $body = (string) ($input['body'] ?? '');
        $citation = trim((string) ($input['citation_note'] ?? ''));
        $citation = $citation !== '' ? $citation : null;
        $summary = $abstract ?? Str::limit(strip_tags($body), 200);
        if ($summary === '') {
            $summary = $title;
        }

        $pdfId = $existing?->pdf_document_id;
        if ($pdf !== null) {
            $stored = app(StorePublicMediaAction::class)->execute(
                $pdf,
                $authorId,
                ['application/pdf'],
                ['alt' => $title.' PDF'],
                'research-pdfs',
            );
            $pdfId = $stored['id'];
        }

        $isPublished = $this->boolish($input['is_published'] ?? false);
        $publishedAt = $this->parsePublishedAt($input['published_at'] ?? null, $isPublished, $existing?->published_at);

        $payload = [
            'type' => PostType::Research->value,
            'title' => $title,
            'slug' => $slug,
            'summary' => $summary,
            'abstract' => $abstract,
            'body' => $body,
            'citation_note' => $citation,
            'authors' => $this->normalizeAuthors($input),
            'pdf_document_id' => $pdfId,
            'is_published' => $isPublished,
            'published_at' => $publishedAt,
        ];

        if ($existing === null) {
            $payload['author_id'] = $authorId;

            return Post::query()->create($payload);
        }

        $existing->update($payload);

        return $existing->refresh();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{instructor_id: int}|array{name: string}>
     */
    private function normalizeAuthors(array $input): array
    {
        $out = [];
        $seenInstructors = [];
        $ids = $input['instructor_ids'] ?? [];
        if (! is_array($ids)) {
            $ids = [$ids];
        }
        foreach ($ids as $id) {
            $intId = (int) $id;
            if ($intId <= 0 || isset($seenInstructors[$intId])) {
                continue;
            }
            $seenInstructors[$intId] = true;
            $out[] = ['instructor_id' => $intId];
        }

        $names = $input['external_names'] ?? [];
        if (is_string($names)) {
            $names = preg_split('/\r\n|\r|\n/', $names) ?: [];
        }
        if (! is_array($names)) {
            $names = [];
        }
        foreach ($names as $name) {
            $trimmed = trim((string) $name);
            if ($trimmed === '') {
                continue;
            }
            $out[] = ['name' => $trimmed];
        }

        return $out;
    }

    private function boolish(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }

    private function parsePublishedAt(mixed $value, bool $isPublished, mixed $existing): mixed
    {
        $raw = is_string($value) ? trim($value) : $value;
        if (is_string($raw) && $raw !== '') {
            try {
                return \Carbon\Carbon::parse($raw, config('app.timezone'));
            } catch (\Throwable) {
                throw ValidationException::withMessages(['published_at' => 'Published at is not a valid date.']);
            }
        }
        if ($isPublished) {
            return $existing ?? now();
        }

        return $existing;
    }
}
