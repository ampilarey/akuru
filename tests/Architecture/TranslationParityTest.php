<?php

use Tests\Architecture\Support\ViolationScanner;

/**
 * CLAUDE.md Conventions: "All screens trilingual-ready (EN/DV/AR)".
 *
 * The RTL slice made every screen *mirror*; it did not make any screen speak
 * Dhivehi or Arabic. This is the other half, and it is a ratchet rather than a
 * fix: 557 English keys ship against 357 in each of Dhivehi and Arabic, and I
 * am not the right author for the missing 200. Inventing school and religious
 * terminology for a Maldivian institute is a job for someone who speaks the
 * language, and a machine-made string that reads *almost* right is worse than
 * an obviously English one, because nobody goes back to check it.
 *
 * So this test translates nothing. It stops the gap growing, names every
 * string in the gap, and makes closing one a visible commit.
 *
 * What the 200 actually are, measured rather than assumed:
 *
 *   - 152 are referenced from Blade or JSX, so a Dhivehi or Arabic visitor is
 *     reading English on a live page today. Every one of them is `public.*` —
 *     the marketing site, admissions and checkout, which is the most
 *     language-sensitive surface this project has.
 *   - 48 have no reference anywhere: 30 more `public.*`, plus all 18
 *     snake_case `common.*` dashboard keys. Those are dead and are left in the
 *     baseline rather than deleted — proving a key unreachable needs a sweep
 *     for dynamic lookups, which is its own slice.
 *
 * A missing key is not a crash. `__('public.Enroll')` falls back to the key,
 * and since the `public` group is keyed by its English sentence the visitor
 * sees English. The snake_case `common.*` keys have no such luck — an
 * untranslated one would render the literal `common.avg_accuracy` on screen.
 * None of those 18 is reachable today; if one is ever wired up, this baseline
 * is where somebody will find out it needs a translation first.
 *
 * Dhivehi also has a DB override layer (`translation_overrides`) and an admin
 * editor, so a deployment can fill gaps without a commit. That is deliberately
 * not consulted here: this test measures what the repository *ships*, which is
 * the only thing CI can hold. Arabic has no editor at all — recorded in STATUS
 * as a gap, not fixed here.
 */
function translationKeys(string $locale): array
{
    $flatten = function (array $lines, string $prefix) use (&$flatten): array {
        $keys = [];
        foreach ($lines as $key => $value) {
            $dotted = $prefix.'.'.$key;
            if (is_array($value)) {
                $keys = array_merge($keys, $flatten($value, $dotted));

                continue;
            }
            $keys[] = $dotted;
        }

        return $keys;
    };

    $keys = [];
    foreach (glob(lang_path($locale.'/*.php')) ?: [] as $file) {
        $lines = require $file;
        $keys = array_merge($keys, $flatten(is_array($lines) ? $lines : [], basename($file, '.php')));
    }
    sort($keys);

    return $keys;
}

it('translates Dhivehi and Arabic together', function () {
    // No baseline: this one is already true and must stay true. Translating a
    // string into one language and not the other leaves a screen half in
    // English for exactly one audience, which is the failure that is hardest
    // to notice from an English-speaking desk.
    $dv = translationKeys('dv');
    $ar = translationKeys('ar');

    expect(array_values(array_diff($dv, $ar)))->toBeEmpty(
        'Keys present in Dhivehi but missing from Arabic — translate both or neither.'
    );
    expect(array_values(array_diff($ar, $dv)))->toBeEmpty(
        'Keys present in Arabic but missing from Dhivehi — translate both or neither.'
    );
});

it('adds no new untranslated English string', function () {
    $baseline = require __DIR__.'/Baselines/untranslated_ui_strings.php';

    $en = translationKeys('en');
    $current = array_values(array_intersect(
        array_diff($en, translationKeys('dv')),
        array_diff($en, translationKeys('ar')),
    ));
    sort($current);

    $diff = ViolationScanner::diffBaseline($current, $baseline);

    expect($diff['added'])->toBeEmpty(
        "New English strings with no Dhivehi or Arabic (baseline may only shrink).\n"
        ."Add the same key to resources/lang/dv and resources/lang/ar:\n"
        .implode("\n", $diff['added'])
    );

    expect($diff['removed'])->toBeEmpty(
        "Translated — remove these from tests/Architecture/Baselines/untranslated_ui_strings.php\n"
        ."and correct its 'Baseline count' comment:\n"
        .implode("\n", $diff['removed'])
    );
});

it('keys every language line by a string', function () {
    // The defect that prompted this test. resources/lang/en/public.php had
    //
    //     'Join thousands of students in their journey to learn Islam',
    //
    // with the `=> '...'` left off, so PHP indexed it as 0. English rendered
    // correctly by accident — the key it fell back to *was* the sentence — and
    // the file quietly grew a `public.0` that no locale could ever match. It
    // survived long enough for both Dhivehi and Arabic to translate a key
    // English did not have.
    $offenders = [];

    foreach (['en', 'dv', 'ar'] as $locale) {
        foreach (translationKeys($locale) as $key) {
            [$group, $line] = explode('.', $key, 2);
            if (preg_match('/(^|\.)\d+$/', $line)) {
                $offenders[] = $locale.'/'.$group.'.php -> '.$line;
            }
        }
    }

    expect($offenders)->toBeEmpty(
        "Numerically indexed language lines — a missing `=> '...'` on the line above:\n"
        .implode("\n", $offenders)
    );
});
