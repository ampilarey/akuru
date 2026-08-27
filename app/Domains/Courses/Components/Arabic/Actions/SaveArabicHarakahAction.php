<?php

namespace App\Domains\Courses\Components\Arabic\Actions;

use App\Domains\Courses\Components\Arabic\Models\ArabicHarakah;
use Illuminate\Validation\ValidationException;

class SaveArabicHarakahAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?ArabicHarakah $harakah = null): ArabicHarakah
    {
        $key = trim((string) ($data['key_name'] ?? ''));
        $symbol = trim((string) ($data['symbol'] ?? ''));
        $name = trim((string) ($data['display_name'] ?? ''));
        if ($key === '' || $symbol === '' || $name === '') {
            throw ValidationException::withMessages([
                'key_name' => 'Key, symbol, and display name are required.',
            ]);
        }

        $payload = [
            'key_name' => $key,
            'symbol' => $symbol,
            'display_name' => $name,
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($harakah === null) {
            return ArabicHarakah::query()->create($payload);
        }

        $harakah->fill($payload);
        $harakah->save();

        return $harakah->fresh();
    }
}
