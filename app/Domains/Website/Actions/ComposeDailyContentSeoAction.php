<?php

namespace App\Domains\Website\Actions;

class ComposeDailyContentSeoAction
{
    /**
     * Article JSON-LD + Open Graph. Fixture gloss is never named as a published mushaf translation (ADR-023).
     *
     * @param  array<string, mixed>  $item
     * @return array{title: string, description: string, og: array<string, string>, json_ld: array<string, mixed>, share: array{whatsapp: string, twitter: string}}
     */
    public function execute(array $item, string $canonicalUrl): array
    {
        $type = (string) ($item['content_type'] ?? '');
        $date = (string) ($item['publish_date'] ?? '');
        $title = $this->headline($item, $type, $date);
        $description = $this->description($item, $type);
        $image = $this->imageUrl($item);

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $title,
            'description' => $description,
            'datePublished' => $date,
            'url' => $canonicalUrl,
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => 'Akuru Institute',
            ],
        ];
        if ($image !== '') {
            $jsonLd['image'] = $image;
        }

        $shareText = $title.' '.$canonicalUrl;

        return [
            'title' => $title,
            'description' => $description,
            'og' => [
                'title' => $title,
                'description' => $description,
                'image' => $image !== '' ? $image : asset('images/og-default.jpg'),
                'type' => 'article',
            ],
            'json_ld' => $jsonLd,
            'share' => [
                'whatsapp' => 'https://wa.me/?text='.rawurlencode($shareText),
                'twitter' => 'https://twitter.com/intent/tweet?url='.rawurlencode($canonicalUrl).'&text='.rawurlencode($title),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function headline(array $item, string $type, string $date): string
    {
        if ($type === 'ayah') {
            $surah = is_array($item['ayah']['surah'] ?? null) ? $item['ayah']['surah'] : [];
            $name = (string) ($surah['english_name'] ?? 'Ayah');
            $surahNumber = (int) ($item['ayah']['surah_number'] ?? 0);
            $ayahNumber = (int) ($item['ayah']['ayah_number'] ?? 0);
            if ($surahNumber > 0 && $ayahNumber > 0) {
                return 'Daily ayah · '.$name.' '.$surahNumber.':'.$ayahNumber;
            }

            return 'Daily ayah · '.$date;
        }

        if ($type === 'hadith') {
            $collection = trim((string) ($item['hadith_collection'] ?? ''));
            $number = trim((string) ($item['hadith_number'] ?? ''));
            if ($collection !== '' && $number !== '') {
                return 'Daily hadith · '.$collection.' '.$number;
            }

            return 'Daily hadith · '.$date;
        }

        $label = $type === 'saying' ? 'Daily saying' : 'Daily reminder';

        return $label.' · '.$date;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function description(array $item, string $type): string
    {
        if ($type === 'ayah') {
            $en = trim((string) ($item['ayah']['meanings']['en'] ?? ''));

            return $en !== '' ? $en : 'Today\'s ayah from Akuru Institute.';
        }
        if ($type === 'hadith') {
            $en = trim((string) ($item['hadith_text_en'] ?? ''));

            return $en !== '' ? $en : 'Today\'s hadith from Akuru Institute.';
        }

        $en = trim((string) ($item['text_en'] ?? ''));

        return $en !== '' ? $en : 'A daily note from Akuru Institute.';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function imageUrl(array $item): string
    {
        $url = trim((string) ($item['share_card_url'] ?? ''));
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }
}
