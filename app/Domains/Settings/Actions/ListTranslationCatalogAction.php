<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Models\TranslationOverride;
use Illuminate\Support\Facades\Lang;

/**
 * The full UI-string catalog for the admin translation editor: every
 * English key with its file Dhivehi and any DB override. English is the
 * reference language — its key set defines "each and every part".
 * "Suspect" flags Dhivehi that is empty or identical to the English
 * (the machine-made leftovers a native speaker should look at first).
 */
class ListTranslationCatalogAction
{
    public const LOCALE = 'dv';

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return ['common', 'public', 'learn', 'notifications', 'documents'];
    }

    /**
     * @return array{groups: list<array{group: string, items: list<array<string, mixed>>}>, override_count: int, total: int, locale: string}
     */
    public function execute(): array
    {
        $overrides = TranslationOverride::query()
            ->where('locale', self::LOCALE)
            ->get()
            ->groupBy('group');

        $groups = [];
        $total = 0;
        $overrideCount = 0;

        foreach (self::groups() as $group) {
            /** @var array<string, mixed> $en */
            $en = Lang::get($group, [], 'en');
            $en = is_array($en) ? $en : [];
            $fileDv = $this->fileStrings($group);
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
                $file = $fileDv[$key] ?? null;
                $items[] = [
                    'key' => $key,
                    'en' => $reference,
                    'file_dv' => is_string($file) ? $file : null,
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
            'locale' => self::LOCALE,
        ];
    }

    /**
     * The dv strings as shipped in the lang FILE — bypassing the
     * override loader so the editor can show file vs override honestly.
     *
     * @return array<string, mixed>
     */
    private function fileStrings(string $group): array
    {
        $path = lang_path(self::LOCALE.'/'.$group.'.php');
        if (! is_file($path)) {
            return [];
        }
        $strings = require $path;

        return is_array($strings) ? $strings : [];
    }
}
