<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Models\Setting;

/**
 * The interface languages switched on (STATUS §5su). English is always on —
 * every phrase falls back to it — and Dhivehi and Arabic each have a switch
 * on System Settings, on until the office turns one off. A language that is
 * off leaves the language menus, the sitemap and the hreflang links, and
 * its addresses go to English (`RedirectDisabledLocale`). What a book, a
 * post or a certificate is written in is content, not this.
 */
class ListEnabledLocalesAction
{
    public const ALWAYS = 'en';

    /** The languages with a switch, in the menus' order. */
    public const SWITCHABLE = ['dv', 'ar'];

    public static function settingKey(string $locale): string
    {
        return 'locale.'.$locale.'_enabled';
    }

    /**
     * @return list<string> English first, then each switched-on language
     */
    public function execute(): array
    {
        return [self::ALWAYS, ...array_values(array_filter(self::SWITCHABLE, fn (string $locale) => $this->isEnabled($locale)))];
    }

    public function isEnabled(string $locale): bool
    {
        if ($locale === self::ALWAYS) {
            return true;
        }

        return in_array($locale, self::SWITCHABLE, true) && (bool) Setting::get(self::settingKey($locale), true);
    }

    /**
     * @return array<string, bool> each switchable language and whether it is on
     */
    public function states(): array
    {
        return collect(self::SWITCHABLE)->mapWithKeys(fn (string $locale) => [$locale => $this->isEnabled($locale)])->all();
    }
}
