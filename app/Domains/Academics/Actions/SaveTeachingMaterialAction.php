<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\TeachingMaterial;
use Illuminate\Validation\ValidationException;

/**
 * Create or edit a reusable material.
 *
 * Only the author may edit. Materials are visible to every member of staff — a
 * library one teacher can see is a notebook — but shared visibility must not
 * mean somebody else can rewrite your wording under your name.
 */
class SaveTeachingMaterialAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, int $authorId, ?TeachingMaterial $material = null): TeachingMaterial
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'A material needs a title.']);
        }

        $attributes = [
            'title' => $title,
            'body' => $this->nullableString($data['body'] ?? null),
            'subject_id' => ($data['subject_id'] ?? null) ?: null,
            'tags' => $this->tags($data['tags'] ?? null),
        ];

        if ($material !== null) {
            if ((int) $material->created_by !== $authorId) {
                throw ValidationException::withMessages([
                    'title' => 'You can only edit materials you wrote.',
                ]);
            }

            $material->update($attributes);

            return $material->refresh();
        }

        return TeachingMaterial::query()->create([...$attributes, 'created_by' => $authorId]);
    }

    /**
     * Tags arrive either as a list or as the comma-separated string the form
     * actually sends. Normalised here so search never has to guess.
     *
     * @return list<string>|null
     */
    private function tags(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $items = is_array($value) ? $value : explode(',', (string) $value);

        $clean = array_values(array_unique(array_filter(
            array_map(fn ($tag): string => mb_strtolower(trim((string) $tag)), $items),
            fn (string $tag): bool => $tag !== '',
        )));

        return $clean === [] ? null : $clean;
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
