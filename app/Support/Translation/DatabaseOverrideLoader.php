<?php

namespace App\Support\Translation;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\FileLoader;

/**
 * File strings with DB overrides merged on top — the override wins,
 * the file is the fallback, so clearing an override restores the
 * shipped string. Cached per locale+group; the save action forgets the
 * cache key. Resilient by design: before the table exists (fresh
 * migrate, early console boot) it silently serves file strings only.
 */
class DatabaseOverrideLoader extends FileLoader
{
    public static function cacheKey(string $locale, string $group): string
    {
        return "translation-overrides:{$locale}:{$group}";
    }

    /**
     * @param  string  $locale
     * @param  string  $group
     * @param  string|null  $namespace
     * @return array<string, mixed>
     */
    public function load($locale, $group, $namespace = null)
    {
        $lines = parent::load($locale, $group, $namespace);

        if ($namespace !== null && $namespace !== '*') {
            return $lines;
        }

        foreach ($this->overrides($locale, $group) as $key => $value) {
            Arr::set($lines, $key, $value);
        }

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    private function overrides(string $locale, string $group): array
    {
        try {
            return Cache::remember(
                self::cacheKey($locale, $group),
                now()->addDay(),
                fn () => DB::table('translation_overrides')
                    ->where('locale', $locale)
                    ->where('group', $group)
                    ->pluck('value', 'key')
                    ->all(),
            );
        } catch (\Throwable) {
            return [];
        }
    }
}
