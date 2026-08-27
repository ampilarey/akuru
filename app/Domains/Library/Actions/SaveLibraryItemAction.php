<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Enums\LibraryAccessType;
use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryTag;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * L1 admin upload (LIBRARY_PLAN §39.1). The PDF original goes to PRIVATE
 * media (§36 / rule 6 of §43 — the original is never exposed); free reading
 * in L1 is the HTML body. Tags are synced by name; authors replaced as an
 * ordered list.
 */
class SaveLibraryItemAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?LibraryItem $item = null, ?UploadedFile $pdf = null): LibraryItem
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Title is required.']);
        }

        $contentType = LibraryContentType::tryFrom((string) ($data['content_type'] ?? ''));
        if ($contentType === null) {
            throw ValidationException::withMessages(['content_type' => 'Invalid content type.']);
        }

        $accessType = LibraryAccessType::tryFrom(
            (string) ($data['access_type'] ?? LibraryAccessType::FreePublic->value)
        );
        if ($accessType === null) {
            throw ValidationException::withMessages(['access_type' => 'Invalid access type.']);
        }

        $slug = (string) ($data['slug'] ?? Str::slug($title));
        $exists = LibraryItem::query()
            ->where('slug', $slug)
            ->when($item, fn ($query) => $query->whereKeyNot($item->id))
            ->exists();
        if ($exists) {
            $slug .= '-'.Str::lower(Str::random(6));
        }

        $pdfId = $item?->pdf_media_file_id;
        if ($pdf !== null) {
            $stored = app(StorePrivateMediaAction::class)->execute(
                $pdf,
                $data['created_by'] ?? null,
                ['application/pdf'],
            );
            $pdfId = $stored['id'];
        }

        $payload = [
            'title' => $title,
            'subtitle' => $data['subtitle'] ?? null,
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'abstract' => $data['abstract'] ?? null,
            'content_type' => $contentType,
            'access_type' => $accessType,
            'language' => $data['language'] ?? 'en',
            'library_category_id' => $data['library_category_id'] ?? null,
            'cover_image' => $data['cover_image'] ?? null,
            'body' => $data['body'] ?? null,
            'pdf_media_file_id' => $pdfId,
            'page_count' => $data['page_count'] ?? null,
            'reading_time' => $data['reading_time'] ?? null,
        ];

        // L5: price rides the same writer; writer_id only when the caller
        // sets it (never nulled by an admin edit).
        if (array_key_exists('price', $data)) {
            $payload['price'] = $data['price'] !== null && $data['price'] !== ''
                ? round((float) $data['price'], 2)
                : null;
        }
        if (isset($data['writer_id'])) {
            $payload['writer_id'] = (int) $data['writer_id'];
        }

        if ($item === null) {
            $payload['created_by'] = $data['created_by'] ?? null;
            $item = LibraryItem::query()->create($payload);
        } else {
            $item->fill($payload);
            $item->save();
        }

        if (array_key_exists('tags', $data) && is_array($data['tags'])) {
            $tagIds = collect($data['tags'])
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->map(fn (string $name) => LibraryTag::query()->firstOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name],
                )->id)
                ->all();
            $item->tags()->sync($tagIds);
        }

        app(SyncLibraryItemPagesAction::class)->execute($item);

        if (array_key_exists('authors', $data) && is_array($data['authors'])) {
            $item->authors()->delete();
            foreach (array_values($data['authors']) as $index => $author) {
                $name = trim((string) ($author['name'] ?? $author));
                if ($name === '') {
                    continue;
                }
                $item->authors()->create([
                    'name' => $name,
                    'user_id' => is_array($author) ? ($author['user_id'] ?? null) : null,
                    'sort_order' => $index,
                ]);
            }
        }

        return $item->refresh();
    }
}
