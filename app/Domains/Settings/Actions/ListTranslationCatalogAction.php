<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Models\TranslationOverride;
use Illuminate\Support\Arr;
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
            throw ValidationException::withMessages(['locale' => __('admin.error_translation_locale')]);
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
            foreach (self::flatten($en) as $key => $reference) {
                $total++;
                $override = $groupOverrides->get($key);
                if ($override !== null) {
                    $overrideCount++;
                }
                $file = Arr::get($fileStrings, $key);
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
     * One page of the editor: the group summaries (name, how many, how many
     * suspect), and 25 rows of the active group after the search and the
     * suspect filter (docs/ADMIN_PANEL.md §7 P5, STATUS §5no). The whole
     * catalog — 743 rows, 132 KB — used to travel on every visit and the
     * active group's 185 rows drew at once, each a textarea. The filtering
     * moved here from the page so the URL carries it (`?group=&q=&suspect=`)
     * and a page is one request. `execute()` still serves the CSV.
     *
     * @return array{groups: list<array{group: string, count: int, suspect: int}>, items: list<array<string, mixed>>, active_group: string, pagination: array{current_page: int, last_page: int, total: int, prev: ?string, next: ?string}, filters: array{q: string, suspect: bool}, override_count: int, total: int, locale: string, locales: list<string>}
     */
    public function page(string $locale, ?string $group = null, string $q = '', bool $suspectOnly = false, int $page = 1, int $perPage = 25): array
    {
        $catalog = $this->execute($locale);
        $group = in_array($group, self::groups(), true) ? $group : self::groups()[0];
        $q = trim($q);
        $needle = mb_strtolower($q);

        $summaries = [];
        $items = [];
        foreach ($catalog['groups'] as $entry) {
            $summaries[] = [
                'group' => $entry['group'],
                'count' => count($entry['items']),
                'suspect' => count(array_filter($entry['items'], fn (array $item) => $item['suspect'])),
            ];
            if ($entry['group'] === $group) {
                $items = $entry['items'];
            }
        }

        $items = array_values(array_filter($items, function (array $item) use ($q, $needle, $suspectOnly): bool {
            if ($suspectOnly && ! $item['suspect']) {
                return false;
            }
            if ($q === '') {
                return true;
            }

            return str_contains(mb_strtolower($item['key']), $needle)
                || str_contains(mb_strtolower($item['en']), $needle)
                || str_contains((string) ($item['file_value'] ?? ''), $q)
                || str_contains((string) ($item['override'] ?? ''), $q);
        }));

        $total = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $url = fn (int $target): string => route('admin.translations.index', array_filter([
            'locale' => $locale,
            'group' => $group,
            'q' => $q !== '' ? $q : null,
            'suspect' => $suspectOnly ? 1 : null,
            'page' => $target > 1 ? $target : null,
        ]));

        return [
            'groups' => $summaries,
            'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'active_group' => $group,
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'total' => $total,
                'prev' => $page > 1 ? $url($page - 1) : null,
                'next' => $page < $lastPage ? $url($page + 1) : null,
            ],
            'filters' => ['q' => $q, 'suspect' => $suspectOnly],
            'override_count' => $catalog['override_count'],
            'total' => $catalog['total'],
            'locale' => $locale,
            'locales' => $catalog['locales'],
        ];
    }

    /**
     * Flatten nested groups to dotted keys, so a nested line is an editable
     * row like any other.
     *
     * This was `if (! is_string($reference)) continue;`, which silently
     * dropped every nested line. `notifications.php` and `documents.php` are
     * **entirely** nested, so both groups rendered as "(0)" and were editable
     * in neither language — including the notification texts that get sent to
     * families. Found by opening the screen, not by a test: the suite asserted
     * the catalog renders, never that it contained anything.
     *
     * Nothing downstream needed changing. `SaveTranslationOverrideAction`
     * validates with `Lang::get($group.'.'.$key)`, and `DatabaseOverrideLoader`
     * writes with `Arr::set()` — both already speak dot notation.
     *
     * @param  array<string, mixed>  $lines
     * @return array<string, string>
     */
    private static function flatten(array $lines, string $prefix = ''): array
    {
        $flat = [];
        foreach ($lines as $key => $value) {
            $dotted = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $flat += self::flatten($value, $dotted);

                continue;
            }
            if (is_string($value)) {
                $flat[$dotted] = $value;
            }
        }

        return $flat;
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
