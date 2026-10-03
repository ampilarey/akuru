<?php

namespace App\Domains\Website\Actions;

use Illuminate\Support\Facades\Cache;

/**
 * The home page caches its courses, posts, events and stats for ten minutes
 * per language (`HomeController`). Publishing a course from the CMS (C16
 * slice N2) forgets it, so the course is on the home page at once rather
 * than ten minutes later — the owner, on the live site: "in the website it
 * doesnt show any course".
 */
class ForgetHomePageCacheAction
{
    public const VERSION = 'v8';

    public static function key(string $locale): string
    {
        return 'homepage_data_'.self::VERSION.'_'.$locale;
    }

    public function execute(): void
    {
        foreach (array_keys((array) config('laravellocalization.supportedLocales', ['en' => [], 'dv' => [], 'ar' => []])) as $locale) {
            Cache::forget(self::key($locale));
        }
    }
}
