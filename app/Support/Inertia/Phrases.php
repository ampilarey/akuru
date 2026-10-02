<?php

namespace App\Support\Inertia;

use Inertia\Inertia;
use Inertia\OnceProp;

/**
 * A page's phrase book, sent once per locale.
 *
 * Every Inertia admin page carries `t`, a whole language file — `admin` is
 * 44 KB of JSON, `shop` 93 KB — and Inertia resends page props on every
 * visit, so an administrator walking five screens downloaded the same
 * 44 KB five times (docs/ADMIN_PANEL.md §7 P2). A once-prop is sent on the
 * first load and remembered by the client (in its session storage) under a
 * key; later visits name the keys they hold in `X-Inertia-Except-Once-Props`
 * and the server leaves those props out.
 *
 * The key carries the file and the locale, so switching to Dhivehi fetches
 * the Dhivehi book, and the `shop` book is never mistaken for `admin`. The
 * TTL is the bound on how long a translation correction saved on
 * `/admin/translations` ("goes live immediately") takes to reach a tab that
 * is already open; a fresh page load always gets the current text.
 */
final class Phrases
{
    public const TTL_MINUTES = 30;

    public static function once(string $file): OnceProp
    {
        return Inertia::once(fn () => trans($file))
            ->as(self::key($file))
            ->until(now()->addMinutes(self::TTL_MINUTES));
    }

    public static function key(string $file, ?string $locale = null): string
    {
        return 't:'.$file.':'.($locale ?? app()->getLocale());
    }
}
