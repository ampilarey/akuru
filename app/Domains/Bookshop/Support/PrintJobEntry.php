<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintSubjectSet;
use Illuminate\Validation\ValidationException;

/**
 * NAME_TAGS_PLAN §3.5 (NT3): what a customer types into a job, and the tags
 * it makes.
 *
 *  - **same**: one child's details on every tag, as many sheets as asked.
 *  - **list**: one row per tag — pasted lines, tab- or comma-separated in
 *    the design's field order — as many sheets as the list needs.
 *  - **subjects**: a grade's subject set, ticked and added to, crossed with
 *    the rows above: every subject with the child's name on it, printed in
 *    the language the customer chose for the tags (not the page's).
 *
 * `normalise()` keeps only what the design asks for and refuses what cannot
 * print; `rows()` expands an entry into one field map per tag; `warnings()`
 * says what printed oddly (empty lines skipped, a value too long for its box).
 */
final class PrintJobEntry
{
    public const MAX_VALUE = 80;

    public const LANGUAGES = ['en', 'dv', 'ar'];

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $fieldKeys  the design's fields
     * @return array{mode: string, row: array<string, string>, list: list<array<string, string>>, subjects: array{set_id: ?int, items: list<array{en: string, dv: string, ar: string}>, language: string}}
     */
    public static function normalise(array $raw, array $fieldKeys, int $maxRows): array
    {
        $mode = ($raw['mode'] ?? 'same') === 'list' ? 'list' : 'same';
        $row = self::row((array) ($raw['row'] ?? []), $fieldKeys);

        $list = [];
        $source = $raw['list'] ?? [];
        if (is_string($source)) {
            $source = self::parse($source, $fieldKeys);
        }
        foreach ((array) $source as $line) {
            if (is_array($line)) {
                $list[] = self::row($line, $fieldKeys);
            }
        }
        if (count($list) > $maxRows) {
            throw ValidationException::withMessages(['list' => __('print.error_job_too_many_rows', ['max' => $maxRows])]);
        }

        $subjects = ['set_id' => null, 'items' => [], 'language' => 'en'];
        $rawSubjects = (array) ($raw['subjects'] ?? []);
        $language = (string) ($rawSubjects['language'] ?? 'en');
        $subjects['language'] = in_array($language, self::LANGUAGES, true) ? $language : 'en';
        $setId = isset($rawSubjects['set_id']) && $rawSubjects['set_id'] !== '' && $rawSubjects['set_id'] !== null ? (int) $rawSubjects['set_id'] : null;
        if ($setId !== null) {
            if (! in_array('subject', $fieldKeys, true)) {
                throw ValidationException::withMessages(['subjects' => __('print.error_job_no_subject_field')]);
            }
            if (! PrintSubjectSet::query()->whereKey($setId)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['subjects' => __('print.error_job_subject_set')]);
            }
            $subjects['set_id'] = $setId;
            foreach ((array) ($rawSubjects['items'] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $clean = [];
                foreach (self::LANGUAGES as $l) {
                    $clean[$l] = mb_substr(trim((string) ($item[$l] ?? '')), 0, self::MAX_VALUE);
                }
                if ($clean['en'] === '' && $clean['dv'] === '' && $clean['ar'] === '') {
                    continue;
                }
                $subjects['items'][] = $clean;
                if (count($subjects['items']) > 40) {
                    break;
                }
            }
        }

        $entry = ['mode' => $mode, 'row' => $row, 'list' => $list, 'subjects' => $subjects];
        // Something on the tag must be the customer's: the required fields of the row, or of at least one line.
        $base = self::baseRows($entry);
        if ($base === []) {
            throw ValidationException::withMessages([$mode === 'list' ? 'list' : 'row' => __('print.error_job_empty')]);
        }

        return $entry;
    }

    /** The tags an entry makes, one field map each. @return list<array<string, string>> */
    public static function rows(array $entry, PrintLayout $layout, int $sheets): array
    {
        $base = self::baseRows($entry);
        $items = (array) ($entry['subjects']['items'] ?? []);
        if ($items !== []) {
            $language = (string) ($entry['subjects']['language'] ?? 'en');
            $rows = [];
            foreach ($base as $row) {
                foreach ($items as $item) {
                    $rows[] = $row + ['subject' => (string) (($item[$language] ?? '') !== '' ? $item[$language] : ($item['en'] ?? ''))];
                }
            }

            return $rows;
        }
        if (($entry['mode'] ?? 'same') === 'same') {
            return array_fill(0, max(1, $layout->tagsPerSheet()) * max(1, $sheets), $base[0] ?? []);
        }

        return $base;
    }

    /** Sheets the entry needs: what was typed for the same tag, else as many as the tags fill. */
    public static function sheets(array $entry, PrintLayout $layout, int $typed): int
    {
        if (($entry['mode'] ?? 'same') === 'same' && ($entry['subjects']['items'] ?? []) === []) {
            return max(1, $typed);
        }

        return max(1, (int) ceil(count(self::rows($entry, $layout, 1)) / max(1, $layout->tagsPerSheet())));
    }

    /**
     * What will print oddly, for the customer to see before the proof.
     *
     * @param  array<string, mixed>  $design
     * @return list<array{key: string, vars: array<string, string|int>}>
     */
    public static function warnings(array $entry, array $design): array
    {
        $warnings = [];
        if (($entry['mode'] ?? 'same') === 'list') {
            $skipped = count(array_filter((array) $entry['list'], fn (array $r) => implode('', $r) === ''));
            if ($skipped > 0) {
                $warnings[] = ['key' => 'print.warn_empty_rows', 'vars' => ['count' => $skipped]];
            }
            $required = array_values(array_map(fn (array $f) => $f['key'], array_filter((array) ($design['fields'] ?? []), fn (array $f) => ! empty($f['required']))));
            foreach ($required as $key) {
                $missing = count(array_filter((array) $entry['list'], fn (array $r) => implode('', $r) !== '' && ($r[$key] ?? '') === ''));
                if ($missing > 0) {
                    $warnings[] = ['key' => 'print.warn_missing_field', 'vars' => ['count' => $missing, 'field' => $key]];
                }
            }
        }
        // A rough measure: about half an em per character. A value whose width
        // at its size passes its box's width will shrink or wrap.
        $seen = [];
        foreach (self::baseRows($entry) as $row) {
            foreach ((array) ($design['elements'] ?? []) as $el) {
                if (($el['type'] ?? null) !== 'text' || empty($el['field'])) {
                    continue;
                }
                $value = (string) ($row[$el['field']] ?? '');
                $text = str_replace('{'.$el['field'].'}', $value, (string) $el['text']);
                $width = mb_strlen($text) * (float) $el['size'] * 0.352778 * 0.55;
                if ($value !== '' && $width > (float) $el['w'] * 1.05 && ! isset($seen[$value])) {
                    $seen[$value] = true;
                    $warnings[] = ['key' => 'print.warn_too_long', 'vars' => ['value' => mb_substr($value, 0, 40)]];
                }
            }
            if (count($seen) >= 5) {
                break;
            }
        }

        return $warnings;
    }

    /** The rows that carry something: the one row, or the list without its empty lines. @return list<array<string, string>> */
    private static function baseRows(array $entry): array
    {
        if (($entry['mode'] ?? 'same') === 'list') {
            return array_values(array_filter((array) ($entry['list'] ?? []), fn (array $r) => implode('', $r) !== ''));
        }
        $row = (array) ($entry['row'] ?? []);

        return implode('', $row) === '' ? [] : [$row];
    }

    /** @param  list<string>  $fieldKeys  @return array<string, string> */
    private static function row(array $raw, array $fieldKeys): array
    {
        $row = [];
        foreach ($fieldKeys as $key) {
            if ($key === 'subject') {
                continue;
            }
            $row[$key] = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($raw[$key] ?? ''))), 0, self::MAX_VALUE);
        }

        return $row;
    }

    /**
     * Pasted lines, one tag each: tab-separated where a tab appears, else
     * comma-separated, in the design's field order (the subject left out —
     * the subject set fills it).
     *
     * @param  list<string>  $fieldKeys
     * @return list<array<string, string>>
     */
    public static function parse(string $text, array $fieldKeys): array
    {
        $keys = array_values(array_filter($fieldKeys, fn (string $k) => $k !== 'subject'));
        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $cells = str_contains($line, "\t") ? explode("\t", $line) : str_getcsv($line, ',', '"', '');
            $row = [];
            foreach ($keys as $i => $key) {
                $row[$key] = trim((string) ($cells[$i] ?? ''));
            }
            $rows[] = $row;
        }
        // Trailing blank lines are the paste's, not the customer's.
        while ($rows !== [] && implode('', end($rows)) === '') {
            array_pop($rows);
        }

        return $rows;
    }
}
