<?php

namespace App\Domains\Library\Actions;

use App\Domains\HR\Actions\ReadPublicInstructorProfileAction;
use App\Domains\Library\Enums\LibraryAccessType;
use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Enums\LibraryDelivery;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryTag;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use App\Domains\Media\Actions\StorePublicMediaAction;
use App\Support\Html\HtmlSanitizer;
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
    public function execute(array $data, ?LibraryItem $item = null, ?UploadedFile $pdf = null, ?UploadedFile $cover = null): LibraryItem
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

        // Checked before anything is written, so an unknown teacher leaves no
        // half-saved item behind.
        $authorRows = array_key_exists('authors', $data) && is_array($data['authors'])
            ? $this->authorRows($data['authors'])
            : null;

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

        // §36 cover: PUBLIC media, on purpose — it is shown on the shelf to
        // everyone. The PDF above is private; the two must never swap.
        $coverId = $item?->cover_media_file_id;
        if ($cover !== null) {
            $storedCover = app(StorePublicMediaAction::class)->execute(
                $cover,
                $data['created_by'] ?? $item?->created_by,
                ['image/jpeg', 'image/png', 'image/webp'],
                ['alt' => $title],
                'library-covers',
            );
            $coverId = $storedCover['id'];
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
            // The typed URL is the office's fallback; a form that does not
            // carry the field (the writer's) leaves it alone.
            'cover_image' => array_key_exists('cover_image', $data) ? ($data['cover_image'] ?: null) : $item?->cover_image,
            'cover_media_file_id' => $coverId,
            // Rendered raw at `public/library/show.blade.php`, and chunked by
            // `SyncLibraryItemPagesAction` into the pages the protected reader
            // serves — so sanitising here closes both surfaces at once.
            //
            // This is the lowest-privilege author of HTML in the application:
            // library items are written by approved **writers**, and any
            // authenticated user may apply to become one. Every other authored
            // HTML path requires a staff role.
            'body' => $this->cleanBody($data['body'] ?? null),
            'pdf_media_file_id' => $pdfId,
            'page_count' => $data['page_count'] ?? null,
            'reading_time' => $data['reading_time'] ?? null,
            // B5: one of three words or nothing; a form that does not carry
            // the field leaves it alone, as for the cover.
            'difficulty' => array_key_exists('difficulty', $data)
                ? (in_array($data['difficulty'], ListLibraryItemsAction::DIFFICULTIES, true) ? $data['difficulty'] : null)
                : $item?->difficulty,
        ];

        // R1 (D1): how readers get it — the author's choice for research and
        // articles, the protected reader for everything else. A form that
        // does not carry the field keeps what was chosen, unless the item has
        // become a type that may not choose.
        $payload['delivery'] = $this->delivery($data, $item, $contentType, $accessType);

        // §9.4 free preview. Written only when the caller says something about
        // it, so an edit form that does not carry these fields cannot silently
        // switch a preview off.
        if (array_key_exists('preview_enabled', $data)) {
            $payload['preview_enabled'] = (bool) $data['preview_enabled'];
        }
        if (array_key_exists('preview_pages', $data)) {
            $pages = (int) $data['preview_pages'];
            $payload['preview_pages'] = $pages > 0 ? $pages : null;
        }

        // L7: research citations ride the same writer.
        if (array_key_exists('citations', $data)) {
            $payload['citations'] = $data['citations'] !== '' ? $data['citations'] : null;
        }

        // §11.3 / §11.5: the rest of what a writer describes. Plain text,
        // escaped where shown. Written only when the form carries the key.
        foreach (['toc', 'affiliation', 'research_field', 'suggested_reviewer'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string) ($data[$field] ?? ''));
                $payload[$field] = $value === '' ? null : $value;
            }
        }
        if (array_key_exists('declarations', $data) && is_array($data['declarations'])) {
            $declared = [];
            foreach (['copyright', 'ai_use', 'originality', 'conflict_of_interest', 'ethics'] as $name) {
                if (filter_var($data['declarations'][$name] ?? false, FILTER_VALIDATE_BOOL)) {
                    $declared[$name] = true;
                }
            }
            $payload['declarations'] = $declared === [] ? null : $declared;
            // The copyright declaration is the one that gates submission;
            // the first time it is made is worth a timestamp.
            $payload['declared_at'] = isset($declared['copyright']) ? ($item?->declared_at ?? now()) : null;
        }
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

        if ($authorRows !== null) {
            $item->authors()->delete();
            foreach ($authorRows as $index => $row) {
                $item->authors()->create($row + ['sort_order' => $index]);
            }
        }

        return $item->refresh();
    }

    private function delivery(array $data, ?LibraryItem $item, LibraryContentType $contentType, LibraryAccessType $accessType): LibraryDelivery
    {
        if (! LibraryDelivery::offeredFor($contentType->value)) {
            return LibraryDelivery::Reader;
        }
        if (array_key_exists('delivery', $data) && $data['delivery'] !== null && $data['delivery'] !== '') {
            $chosen = LibraryDelivery::tryFrom((string) $data['delivery']);
            if ($chosen === null) {
                throw ValidationException::withMessages(['delivery' => 'Choose how readers get it: read online, download, or both.']);
            }

            return $chosen;
        }
        $current = $item?->delivery;
        if ($current instanceof LibraryDelivery && LibraryDelivery::offeredFor($item?->content_type?->value)) {
            return $current;
        }

        return LibraryDelivery::defaultFor($contentType->value, $accessType->value);
    }

    /**
     * R1: an author is a name (someone outside Akuru), a writer account
     * (`user_id`), or one of the institute's teachers (`instructor_profile_id`).
     * A teacher's name is taken from their profile — through HR's action, as
     * the Library never reads HR's tables itself — and kept on the row, so a
     * shelf or a citation needs nothing else.
     *
     * @param  array<int, mixed>  $authors
     * @return list<array{name: string, user_id: ?int, instructor_profile_id: ?int}>
     */
    private function authorRows(array $authors): array
    {
        $rows = [];
        $teachers = [];
        foreach (array_values($authors) as $author) {
            $instructorId = is_array($author) && is_numeric($author['instructor_profile_id'] ?? null)
                ? (int) $author['instructor_profile_id']
                : null;
            if ($instructorId !== null) {
                if (isset($teachers[$instructorId])) {
                    continue;
                }
                $profile = app(ReadPublicInstructorProfileAction::class)->execute($instructorId, null, false);
                if ($profile === null) {
                    throw ValidationException::withMessages(['authors' => 'That teacher is not on the website\'s teacher list.']);
                }
                $teachers[$instructorId] = true;
                $rows[] = ['name' => $profile['name'], 'user_id' => null, 'instructor_profile_id' => $instructorId];

                continue;
            }

            $name = trim((string) (is_array($author) ? ($author['name'] ?? '') : $author));
            if ($name === '') {
                continue;
            }
            $userId = is_array($author) && is_numeric($author['user_id'] ?? null) ? (int) $author['user_id'] : null;
            $rows[] = ['name' => $name, 'user_id' => $userId, 'instructor_profile_id' => null];
        }

        return $rows;
    }

    private function cleanBody(?string $body): ?string
    {
        if ($body === null || trim($body) === '') {
            return null;
        }

        // The sanitiser strips HTML comments, and `<!-- pagebreak -->` is one:
        // sanitising the body whole silently collapsed a three-page book into
        // one page. So the body is split on the marker first, each part is
        // sanitised, and the markers are put back.
        //
        // Done here rather than by teaching `HtmlSanitizer` about pagination:
        // the marker is the Library's convention (LIBRARY_PLAN §36), and a
        // shared sanitiser should not carry one domain's formatting rules.
        $sanitizer = app(HtmlSanitizer::class);

        // B3: the rich text editor cannot hold a comment, so its page break
        // is `<hr data-pagebreak>`. The client turns it back into the marker
        // before posting; accepting it here too means a body that arrives
        // straight from the editor's HTML still paginates.
        $body = (string) preg_replace('~<hr\b[^>]*\bdata-pagebreak\b[^>]*>~i', SyncLibraryItemPagesAction::PAGE_BREAK, $body);

        $parts = array_map(
            fn (string $part): string => $sanitizer->clean($part, HtmlSanitizer::PROFILE_CMS),
            explode(SyncLibraryItemPagesAction::PAGE_BREAK, $body),
        );

        return implode(SyncLibraryItemPagesAction::PAGE_BREAK, $parts);
    }
}
