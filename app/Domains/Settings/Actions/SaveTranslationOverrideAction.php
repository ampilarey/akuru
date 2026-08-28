<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Models\TranslationOverride;
use App\Support\Translation\DatabaseOverrideLoader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;

/**
 * Save or clear one Dhivehi override. Only keys that exist in the
 * English reference files are accepted — the editor corrects
 * translations, it does not invent strings. An empty value clears the
 * override so the shipped file string returns.
 */
class SaveTranslationOverrideAction
{
    /**
     * @return array{override: ?string}
     */
    public function execute(string $group, string $key, ?string $value, int $userId): array
    {
        if (! in_array($group, ListTranslationCatalogAction::groups(), true)) {
            throw ValidationException::withMessages(['group' => 'Unknown translation group.']);
        }

        $reference = Lang::get($group.'.'.$key, [], 'en');
        if (! is_string($reference) || $reference === $group.'.'.$key) {
            throw ValidationException::withMessages(['key' => 'Unknown translation key.']);
        }

        $locale = ListTranslationCatalogAction::LOCALE;
        $value = $value !== null ? trim($value) : '';

        if ($value === '') {
            TranslationOverride::query()
                ->where('locale', $locale)->where('group', $group)->where('key', $key)
                ->delete();
        } else {
            TranslationOverride::query()->updateOrCreate(
                ['locale' => $locale, 'group' => $group, 'key' => $key],
                ['value' => $value, 'updated_by' => $userId],
            );
        }

        Cache::forget(DatabaseOverrideLoader::cacheKey($locale, $group));

        return ['override' => $value === '' ? null : $value];
    }
}
