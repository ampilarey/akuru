<?php

namespace App\Domains\Courses\Actions;

class NormalizeTextAnswerAction
{
    /**
     * SPEC §18 names two modes alongside the individual switches:
     *
     *   > Trim whitespace · Normalize repeated spaces · Remove punctuation ·
     *   > Case-insensitive comparison · Accept multiple correct answers ·
     *   > **Strict mode** · **Lenient mode**
     *
     * Neither existed. The switches did, each defaulting on its own, and the
     * defaults are lenient — `trim`, `collapse_space` and `case_insensitive`
     * are all on unless a caller says otherwise. §18's own example of a
     * question that needs the opposite is "A formula answer may use strict
     * matching", and there was no way to ask for it short of listing every
     * flag as false by hand and knowing which flags exist.
     *
     * A mode is now a starting point that individual flags still override, so
     * "strict, but tolerate trailing whitespace" is one setting rather than a
     * full enumeration.
     *
     * @var array<string, array<string, bool>>
     */
    private const MODES = [
        // Compare exactly as typed. Nothing is forgiven — the mode §18 asks
        // for when the characters themselves are the answer.
        'strict' => [
            'trim' => false,
            'collapse_space' => false,
            'strip_punctuation' => false,
            'case_insensitive' => false,
        ],
        // Forgive everything that is not part of the answer's meaning in a
        // Latin-script question. Arabic switches stay off: §18 is explicit
        // that "Arabic normalization must not be global", so lenient mode must
        // not quietly turn it on.
        'lenient' => [
            'trim' => true,
            'collapse_space' => true,
            'strip_punctuation' => true,
            'case_insensitive' => true,
        ],
    ];

    /**
     * The behaviour of a question saved before modes existed, and of one saved
     * with no normalization settings at all. Kept as its own named default
     * rather than scattered `?? true`s so that what an unconfigured question
     * does is written down in one place.
     *
     * @var array<string, bool>
     */
    private const DEFAULTS = [
        'trim' => true,
        'collapse_space' => true,
        'strip_punctuation' => false,
        'case_insensitive' => true,
        'strip_tashkeel' => false,
        'normalize_alef' => false,
        'normalize_hamza' => false,
        'taa_marbuta' => false,
    ];

    /**
     * @param  array<string, mixed>  $settings
     */
    public function execute(string $value, array $settings = []): string
    {
        $on = $this->resolve($settings);
        $text = $value;

        if ($on['trim']) {
            $text = trim($text);
        }
        if ($on['collapse_space']) {
            $text = (string) preg_replace('/\s+/u', ' ', $text);
        }
        if ($on['strip_punctuation']) {
            // `\p{M}` — combining marks — is in the keep-set deliberately.
            // Without it this removed Arabic tashkeel, because a haraka is
            // neither a letter nor a number nor a space. So "remove
            // punctuation", a switch §18 lists under *general* normalization,
            // silently performed the Arabic normalization §18 says "must not
            // be global": an Arabic haraka question that ticked it accepted
            // an undiacriticized answer as correct, which is the one thing
            // that question exists to reject.
            $text = (string) preg_replace('/[^\p{L}\p{N}\p{M}\s]+/u', '', $text);
        }
        if ($on['case_insensitive']) {
            $text = mb_strtolower($text);
        }
        if ($on['strip_tashkeel']) {
            $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);
        }
        if ($on['normalize_alef']) {
            $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
        }
        if ($on['normalize_hamza']) {
            $text = str_replace(['ؤ', 'ئ'], 'ء', $text);
        }
        if ($on['taa_marbuta']) {
            $text = str_replace('ة', 'ه', $text);
        }

        return $text;
    }

    /**
     * Defaults, then the mode's overrides, then the caller's explicit flags.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, bool>
     */
    private function resolve(array $settings): array
    {
        $mode = is_string($settings['mode'] ?? null) ? $settings['mode'] : null;
        $resolved = self::DEFAULTS;

        if ($mode !== null && isset(self::MODES[$mode])) {
            $resolved = array_merge($resolved, self::MODES[$mode]);
        }

        foreach (array_keys(self::DEFAULTS) as $key) {
            if (array_key_exists($key, $settings)) {
                $resolved[$key] = (bool) $settings[$key];
            }
        }

        return $resolved;
    }

    /**
     * The switch names §18 defines, for validation and for the builder UI.
     *
     * @return list<string>
     */
    public static function flags(): array
    {
        return array_keys(self::DEFAULTS);
    }

    /**
     * @return list<string>
     */
    public static function modes(): array
    {
        return array_keys(self::MODES);
    }
}
