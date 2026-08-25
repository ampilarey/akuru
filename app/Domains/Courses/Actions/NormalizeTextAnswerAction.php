<?php

namespace App\Domains\Courses\Actions;

class NormalizeTextAnswerAction
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function execute(string $value, array $settings = []): string
    {
        $text = $value;
        if ($settings['trim'] ?? true) {
            $text = trim($text);
        }
        if ($settings['collapse_space'] ?? true) {
            $text = (string) preg_replace('/\s+/u', ' ', $text);
        }
        if ($settings['strip_punctuation'] ?? false) {
            $text = (string) preg_replace('/[^\p{L}\p{N}\s]+/u', '', $text);
        }
        if ($settings['case_insensitive'] ?? true) {
            $text = mb_strtolower($text);
        }
        if ($settings['strip_tashkeel'] ?? false) {
            $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);
        }
        if ($settings['normalize_alef'] ?? false) {
            $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
        }
        if ($settings['normalize_hamza'] ?? false) {
            $text = str_replace(['ؤ', 'ئ'], 'ء', $text);
        }
        if ($settings['taa_marbuta'] ?? false) {
            $text = str_replace('ة', 'ه', $text);
        }

        return $text;
    }
}
