<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\ArabicLetter;
use Illuminate\Validation\ValidationException;

class SaveArabicLetterAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?ArabicLetter $letter = null): ArabicLetter
    {
        $key = trim((string) ($data['key_name'] ?? ''));
        $character = trim((string) ($data['arabic_character'] ?? ''));
        $name = trim((string) ($data['display_name'] ?? ''));
        if ($key === '' || $character === '' || $name === '') {
            throw ValidationException::withMessages([
                'key_name' => 'Key, character, and display name are required.',
            ]);
        }

        $payload = [
            'key_name' => $key,
            'arabic_character' => $character,
            'display_name' => $name,
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($letter === null) {
            return ArabicLetter::query()->create($payload);
        }

        $letter->fill($payload);
        $letter->save();

        return $letter->fresh();
    }
}
