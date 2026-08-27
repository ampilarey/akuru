<?php

namespace App\Domains\Website\Actions;

class ComposeDailySubscriptionMessageAction
{
    /**
     * SMS is short labels + permalinks only — never full Arabic.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array{sms: string, subject: string, items: list<array<string, mixed>>, unsubscribe_url: string}
     */
    public function execute(array $items, string $language, string $unsubscribeToken): array
    {
        $locale = $language === 'dv' ? 'dv' : 'en';
        $unsubscribeUrl = $this->publicUrl($locale, 'daily/unsubscribe/'.$unsubscribeToken);
        $lines = [];

        foreach ($items as $item) {
            $type = (string) ($item['content_type'] ?? '');
            $date = (string) ($item['publish_date'] ?? '');
            $path = (string) ($item['permalink_path'] ?? ('daily/'.$type.'/'.$date));
            $url = $this->publicUrl($locale, $path);
            $lines[] = $this->shortLabel($item).' '.$url;
        }

        $header = $locale === 'dv' ? 'Akuru Institute daily:' : 'Akuru Institute daily:';
        $stop = 'Reply STOP to unsubscribe';
        $sms = $header."\n".implode("\n", $lines)."\n".$stop;
        $sms = $this->stripArabic($sms);

        return [
            'sms' => $sms,
            'subject' => 'Akuru Institute daily · '.((string) ($items[0]['publish_date'] ?? '')),
            'items' => array_map(fn (array $item) => $this->emailItem($item, $locale), $items),
            'unsubscribe_url' => $unsubscribeUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function shortLabel(array $item): string
    {
        $type = (string) ($item['content_type'] ?? '');
        if ($type === 'ayah') {
            $surah = is_array($item['ayah']['surah'] ?? null) ? $item['ayah']['surah'] : [];
            $name = (string) ($surah['english_name'] ?? 'Ayah');
            $surahNumber = (int) ($item['ayah']['surah_number'] ?? 0);
            $ayahNumber = (int) ($item['ayah']['ayah_number'] ?? 0);
            if ($surahNumber > 0 && $ayahNumber > 0) {
                return 'Ayah '.$name.' '.$surahNumber.':'.$ayahNumber;
            }

            return 'Ayah';
        }

        if ($type === 'hadith') {
            $collection = trim((string) ($item['hadith_collection'] ?? 'Hadith'));
            $number = trim((string) ($item['hadith_number'] ?? ''));

            return trim('Hadith '.$collection.' '.$number);
        }

        $attribution = trim((string) ($item['attribution'] ?? ''));
        $label = $type === 'saying' ? 'Saying' : 'Reminder';

        return $attribution !== '' ? $label.' · '.$this->stripArabic($attribution) : $label;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function emailItem(array $item, string $locale): array
    {
        $type = (string) ($item['content_type'] ?? '');
        $date = (string) ($item['publish_date'] ?? '');
        $path = (string) ($item['permalink_path'] ?? ('daily/'.$type.'/'.$date));
        $body = '';
        if ($type === 'ayah') {
            $meanings = is_array($item['ayah']['meanings'] ?? null) ? $item['ayah']['meanings'] : [];
            $body = (string) ($meanings[$locale] ?? $meanings['en'] ?? '');
        } elseif ($type === 'hadith') {
            $body = (string) ($item['hadith_text_'.$locale] ?? $item['hadith_text_en'] ?? '');
        } else {
            $body = (string) ($item['text_'.$locale] ?? $item['text_en'] ?? '');
        }

        return [
            'label' => $this->shortLabel($item),
            'body' => $body,
            'url' => $this->publicUrl($locale, $path),
        ];
    }

    private function publicUrl(string $locale, string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.$locale.'/'.ltrim($path, '/');
    }

    private function stripArabic(string $text): string
    {
        return trim((string) preg_replace('/\p{Arabic}+/u', '', $text));
    }
}
