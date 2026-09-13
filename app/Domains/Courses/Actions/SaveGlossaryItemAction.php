<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\GlossaryItem;

class SaveGlossaryItemAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?GlossaryItem $item = null): GlossaryItem
    {
        $payload = [
            'subject_id' => $this->nullableInt($data['subject_id'] ?? null),
            'category_id' => $this->nullableInt($data['category_id'] ?? null),
            'term' => trim((string) $data['term']),
            'term_dv' => $this->nullableString($data['term_dv'] ?? null),
            'term_ar' => $this->nullableString($data['term_ar'] ?? null),
            'transliteration' => $this->nullableString($data['transliteration'] ?? null),
            'meaning_primary' => $this->nullableString($data['meaning_primary'] ?? null),
            'meaning_secondary' => $this->nullableString($data['meaning_secondary'] ?? null),
            'meaning_dv' => $this->nullableString($data['meaning_dv'] ?? null),
            'meaning_ar' => $this->nullableString($data['meaning_ar'] ?? null),
            'description' => $this->nullableString($data['description'] ?? null),
            'description_dv' => $this->nullableString($data['description_dv'] ?? null),
            'description_ar' => $this->nullableString($data['description_ar'] ?? null),
            'example_text' => $this->nullableString($data['example_text'] ?? null),
            'example_translation' => $this->nullableString($data['example_translation'] ?? null),
            'example_text_dv' => $this->nullableString($data['example_text_dv'] ?? null),
            'example_text_ar' => $this->nullableString($data['example_text_ar'] ?? null),
            'tags' => $this->tags($data['tags'] ?? null),
            'level_id' => $this->nullableInt($data['level_id'] ?? null),
        ];

        // SPEC §22 "Glossary Media". The four columns were fillable, validated
        // and sent to the player, and **nothing ever uploaded one** — the admin
        // form had no file input, so there was no way to obtain a media id to
        // put in them. The validation guarded a door nobody could reach.
        //
        // A slot the caller does not mention keeps whatever it had: an edit
        // that says nothing about the audio must not delete the audio. Clearing
        // is a separate signal, not an empty upload.
        $media = app(StoreGlossaryMediaAction::class);
        foreach (StoreGlossaryMediaAction::SLOTS as $slot => $kind) {
            if (array_key_exists($slot, $data)) {
                $payload[$slot] = $this->nullableInt($data[$slot]);
            }
        }
        $payload = array_merge(
            $payload,
            $media->cleared($data),
            $media->execute($data, $this->nullableInt($data['created_by'] ?? null) ?? $item?->created_by),
        );

        if ($item === null) {
            $payload['created_by'] = $this->nullableInt($data['created_by'] ?? null);

            return GlossaryItem::query()->create($payload);
        }

        $item->fill($payload);
        $item->save();

        return $item->refresh();
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return list<string>|null
     */
    private function tags(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return array_values(array_filter(array_map(fn ($tag) => trim((string) $tag), $value)));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }
}
