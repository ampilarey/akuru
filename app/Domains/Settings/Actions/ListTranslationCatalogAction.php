<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Models\TranslationOverride;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;

/**
 * The full UI-string catalog for the admin translation editor: every
 * English key with its file string in the edited locale, and any DB
 * override. English is the reference language — its key set defines
 * "each and every part". "Suspect" flags a translation that is empty or
 * identical to the English (the machine-made leftovers a native speaker
 * should look at first).
 *
 * Editable in Dhivehi *and* Arabic. It was `dv` only until now, while
 * CLAUDE.md asks for EN/DV/AR equally — so a Dhivehi gap could be filled
 * from this screen while an Arabic one needed a file edit and a deploy.
 * The `translation_overrides` table and `DatabaseOverrideLoader` were
 * already locale-generic; the migration that created the table says so
 * outright ("Schema supports any locale; the admin UI exposes dv only").
 * Only this constant stood in the way.
 */
class ListTranslationCatalogAction
{
    public const DEFAULT_LOCALE = 'dv';

    /**
     * English is the reference and is never edited here — correcting it
     * is a code change, not an override.
     *
     * @return list<string>
     */
    public static function locales(): array
    {
        return ['dv', 'ar'];
    }

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return ['common', 'public', 'learn', 'notifications', 'documents'];
    }

    /**
     * Shared by every action that takes a locale from a request, so an
     * unknown one is refused identically wherever it arrives.
     */
    public static function assertEditableLocale(string $locale): string
    {
        if (! in_array($locale, self::locales(), true)) {
            throw ValidationException::withMessages(['locale' => 'That language is not editable here.']);
        }

        return $locale;
    }

    /**
     * @return array{groups: list<array{group: string, items: list<array<string, mixed>>}>, override_count: int, total: int, locale: string, locales: list<string>}
     */
    public function execute(string $locale = self::DEFAULT_LOCALE): array
    {
        self::assertEditableLocale($locale);

        $overrides = TranslationOverride::query()
            ->where('locale', $locale)
            ->get()
            ->groupBy('group');

        $groups = [];
        $total = 0;
        $overrideCount = 0;

        foreach (self::groups() as $group) {
            /** @var array<string, mixed> $en */
            $en = Lang::get($group, [], 'en');
            $en = is_array($en) ? $en : [];
            $fileStrings = $this->fileStrings($locale, $group);
            $groupOverrides = ($overrides->get($group) ?? collect())->keyBy('key');

            $items = [];
            foreach ($en as $key => $reference) {
                if (! is_string($reference)) {
                    continue; // nested arrays are not editable rows
                }
                $total++;
                $override = $groupOverrides->get($key);
                if ($override !== null) {
                    $overrideCount++;
                }
                $file = $fileStrings[$key] ?? null;
                $items[] = [
                    'key' => $key,
                    'en' => $reference,
                    'file_value' => is_string($file) ? $file : null,
                    'override' => $override?->value,
                    'suspect' => $override === null && (! is_string($file) || trim($file) === '' || $file === $reference),
                ];
            }

            $groups[] = ['group' => $group, 'items' => $items];
        }

        return [
            'groups' => $groups,
            'override_count' => $overrideCount,
            'total' => $total,
            'locale' => $locale,
            'locales' => self::locales(),
        ];
    }

    /**
     * The strings as shipped in the lang FILE — bypassing the override
     * loader so the editor can show file vs override honestly.
     *
     * @return array<string, mixed>
     */
    private function fileStrings(string $locale, string $group): array
    {
        $path = lang_path($locale.'/'.$group.'.php');
        if (! is_file($path)) {
            return [];
        }
        $strings = require $path;

        return is_array($strings) ? $strings : [];
    }
}
